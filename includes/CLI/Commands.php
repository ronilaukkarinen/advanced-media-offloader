<?php

namespace Advanced_Media_Offloader\CLI;

use WP_CLI;
use Advanced_Media_Offloader\Factories\CloudProviderFactory;
use Advanced_Media_Offloader\Services\BulkMediaOffloader;
use Advanced_Media_Offloader\Services\CloudAttachmentUploader;

class Commands {
    public function __construct() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'advmo', $this );
        }
    }

    /**
     * Bulk offload media files to cloud storage
     *
     * ## OPTIONS
     *
     * [--batch-size=<number>]
     * : Number of files to process per batch
     * ---
     * default: 50
     * ---
     *
     * [--all]
     * : Process all files instead of using batch size
     *
     * [--dry-run]
     * : Preview what files would be offloaded without actually offloading them
     *
     * [--yes]
     * : Answer yes to the deletion confirmation
     *
     * [--verbose]
     * : Show verbose information
     *
     * ## EXAMPLES
     *
     *     wp advmo offload
     *     wp advmo offload --batch-size=100
     *     wp advmo offload --all
     *     wp advmo offload --dry-run
     *
     * @when after_wp_load
     */
    public function offload( $args, $assoc_args ) {
        $process_all = isset( $assoc_args['all'] );
        $batch_size = $process_all ? -1 : (int) ( $assoc_args['batch-size'] ?? 50 );
        $dry_run = isset( $assoc_args['dry-run'] );
        $verbose = isset( $assoc_args['verbose'] );
        $skip_confirm = isset( $assoc_args['yes'] );

        if ( $verbose ) {
          WP_CLI::line( 'Verbose mode enabled' );
          WP_CLI::line( 'Arguments received:' );
          WP_CLI::line( print_r( $assoc_args, true ) );
        }

        try {
            $this->cloud_provider_key = advmo_get_cloud_provider_key();
            if ( empty( $this->cloud_provider_key ) ) {
              WP_CLI::error('No cloud provider configured. Please configure a provider in the plugin settings first.' );
              return;
            }

            // Validate WordPress upload directory is writable
            $upload_dir = wp_upload_dir();
            if ( is_wp_error( $upload_dir ) ) {
              WP_CLI::error( 'WordPress upload directory is not accessible: ' . $upload_dir->get_error_message() );
              return;
            }

            // Validate cloud provider settings
            $settings = get_option( 'advmo_settings', [] );
            if ( empty( $settings['cloud_provider'] ) ) {
              WP_CLI::error( 'Cloud provider settings are incomplete. Please configure the plugin settings first.' );
              return;
            }

            // Validate we can connect to the cloud provider
            try {
              $cloud_provider = CloudProviderFactory::create( $this->cloud_provider_key );
              $cloud_provider->checkConnection();
            } catch ( \Exception $e ) {
              WP_CLI::error( 'Could not connect to cloud provider: ' . $e->getMessage() );
              return;
            }

            // Get provider name for display
            $provider_names = [
              's3' => 'Amazon S3',
              'do' => 'DigitalOcean Spaces',
              'minio' => 'MinIO',
              'r2' => 'Cloudflare R2',
            ];
            $provider_name = $provider_names[ $this->cloud_provider_key ] ?? $this->cloud_provider_key;

            // Get deletion settings
            $advmo_settings = get_option( 'advmo_settings', array() );
            $deleteLocalRule = isset( $advmo_settings['retention_policy'] ) ? (int) $advmo_settings['retention_policy'] : 0;

            if ( $verbose ) {
              WP_CLI::line( 'Debug - Initial settings:' );
              WP_CLI::line( print_r( $advmo_settings, true ) );
              WP_CLI::line( sprintf( 'Debug - Delete Local Rule: %d', $deleteLocalRule ) );
            }

            $deletionMode = '';
            switch ( $deleteLocalRule ) {
                case 1:
                  $deletionMode = ' (Smart Local Cleanup enabled - will keep only originals)';
                  break;
                case 2:
                  $deletionMode = ' (Full Cloud Migration enabled - will remove all local files)';
                  break;
                default:
                  $deletionMode = ' (keeping local files)';
            }

            $attachments = $this->get_unoffloaded_attachments( $batch_size );
            $total = count( $attachments );

            if ( $total === 0 ) {
              WP_CLI::success( 'No files need to be offloaded.' );
              return;
            }

            // Display summary
            WP_CLI::line(sprintf('You have %d %s still stored on your server.',
                $total,
                _n( 'file', 'files', $total )
            ));
            WP_CLI::line( sprintf( '%d files to be uploaded to %s%s', $total, $provider_name, $deletionMode ) );
            WP_CLI::line( '' );

            if ( $dry_run ) {
              WP_CLI::line( sprintf( 'Found %d files that would be offloaded:', $total ) );
              $upload_dir = wp_upload_dir();

                foreach ( $attachments as $attachment_id ) {
                  $file_path = get_attached_file( $attachment_id );
                  $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );

                  if ( file_exists( $file_path ) ) {
                    $file_size = size_format( filesize( $file_path ) );
                  } else {
                    $file_size = 'File not found';
                  }

                    WP_CLI::line(sprintf(
                      ' - [ID: %d] %s (%s)',
                      $attachment_id,
                      $relative_path,
                      $file_size
                    ));
                }
                WP_CLI::success( 'Dry run completed. No files were offloaded.' );
                return;
            }

            $this->process_attachments( $attachments, $dry_run, $verbose, $skip_confirm );

        } catch ( \Exception $e ) {
            WP_CLI::error( 'Error during offload: ' . $e->getMessage() );
        }
    }

    /**
     * Upload a single media file to cloud storage
     *
     * ## OPTIONS
     *
     * <attachment_id>
     * : The ID of the attachment to offload
     *
     * [--dry-run]
     * : Preview what would be offloaded without actually offloading
     *
     * [--verbose]
     * : Show verbose information
     *
     * ## EXAMPLES
     *
     *     wp advmo upload 123
     *     wp advmo upload 123 --dry-run
     *     wp advmo upload 123 --verbose
     *
     * @when after_wp_load
     */
    public function upload( $args, $assoc_args ) {
        $attachment_id = (int) $args[0];
        $dry_run = isset( $assoc_args['dry-run'] );
        $verbose = isset( $assoc_args['verbose'] );

        if ( $verbose ) {
          WP_CLI::line( 'Verbose mode enabled' );
          WP_CLI::line( 'Arguments received:' );
          WP_CLI::line( print_r( $assoc_args, true ) );
        }

        try {
            // Validate the attachment exists
            if ( ! get_post( $attachment_id ) ) {
              WP_CLI::error( "Attachment with ID {$attachment_id} not found." );
              return;
            }

            // Check if already offloaded
            $is_offloaded = get_post_meta( $attachment_id, 'advmo_offloaded', true );
            if ( $is_offloaded ) {
              WP_CLI::warning( "Attachment {$attachment_id} is already offloaded." );
              return;
            }

            $cloud_provider_key = advmo_get_cloud_provider_key();
            if ( empty( $cloud_provider_key ) ) {
              WP_CLI::error( 'No cloud provider configured. Please configure a provider in the plugin settings first.' );
              return;
            }

            // Get file information
            $file_path = get_attached_file( $attachment_id );
            if ( ! file_exists( $file_path ) ) {
              WP_CLI::error( "File not found: {$file_path}" );
              return;
            }

            if ( $dry_run ) {
              WP_CLI::line(sprintf(
                  'Would offload: [ID: %d] %s (%s)',
                  $attachment_id,
                  basename( $file_path ),
                  size_format( filesize( $file_path ) )
              ));
              WP_CLI::success( 'Dry run completed. No files were offloaded.' );
              return;
            }

            // Upload the file
            $cloud_provider = CloudProviderFactory::create( $cloud_provider_key );
            $uploader = new CloudAttachmentUploader( $cloud_provider );

            if ( $verbose ) {
              WP_CLI::line( "\n=== Debug Information ===" );
              WP_CLI::line( "File path: {$file_path}" );
              WP_CLI::line( 'File exists: ' . ( file_exists( $file_path ) ? 'yes' : 'no' ) );
              WP_CLI::line( 'File size: ' . size_format( filesize( $file_path ) ) );
              WP_CLI::line( 'File permissions: ' . substr( sprintf( '%o', fileperms( $file_path ) ), -4 ) );
              WP_CLI::line( '========================' );
            }

            $result = $uploader->uploadAttachment( $attachment_id, true );

            if ( $result ) {
              WP_CLI::success( sprintf( 'Successfully offloaded: %s', basename( $file_path ) ) );
            } else {
              WP_CLI::error( sprintf( 'Failed to offload: %s', basename( $file_path ) ) );
            }
        } catch ( \Exception $e ) {
          WP_CLI::error( 'Error during upload: ' . $e->getMessage() );
        }
    }

    /**
     * Fix the URL structure for an offloaded media file
     *
     * ## OPTIONS
     *
     * <attachment_id>
     * : The ID of the attachment to fix
     *
     * [--dry-run]
     * : Preview changes without making them
     */
    public function fix_url( $args, $assoc_args ) {
        $attachment_id = (int) $args[0];
        $dry_run = isset( $assoc_args['dry-run'] );

        try {
          // Validate the attachment exists
          if ( ! get_post( $attachment_id ) ) {
            WP_CLI::error( "Attachment with ID {$attachment_id} not found." );
            return;
          }

          // Check if it's offloaded
          $is_offloaded = get_post_meta( $attachment_id, 'advmo_offloaded', true );
          if ( ! $is_offloaded ) {
            WP_CLI::error( "Attachment {$attachment_id} is not offloaded." );
            return;
          }

            // Get the file path from attachment metadata
            $file = get_post_meta( $attachment_id, '_wp_attached_file', true );

            // Try to extract the date directory from the existing path
            if ( preg_match( '/^(\d{12})\//', $file, $matches ) ) {
              $subdir = $matches[1] . '/';
            } else {
              // Fallback to using the attachment's post date
              $post = get_post( $attachment_id );
              $subdir = gmdate( 'YmdHis', strtotime( $post->post_date ) ) . '/';
            }

            if ( $dry_run ) {
              WP_CLI::line( "Would update path for attachment {$attachment_id} to: {$subdir}" );
              return;
            }

            // Update the path metadata
            update_post_meta( $attachment_id, 'advmo_path', $subdir );

            // Also update the attached file path
            $filename = basename( $file );
            update_post_meta( $attachment_id, '_wp_attached_file', $subdir . $filename );

            WP_CLI::success( "Updated URL structure for attachment {$attachment_id} to use {$subdir}" );

        } catch ( \Exception $e ) {
          WP_CLI::error( 'Error: ' . $e->getMessage() );
        }
    }

    private function get_unoffloaded_attachments( $batch_size ) {
        return get_posts([
          'post_type' => 'attachment',
          'post_status' => 'any',
          'posts_per_page' => $batch_size < 0 ? -1 : $batch_size,
          'fields' => 'ids',
          'orderby' => 'post_date',
          'order' => 'ASC',
          'meta_query' => [
              'relation' => 'OR',
              [
                'key' => 'advmo_offloaded',
                'compare' => 'NOT EXISTS',
              ],
              [
                'key' => 'advmo_offloaded',
                'compare' => '=',
                'value' => '',
              ],
          ],
        ]);
    }

    private function delete_sized_images( $attachment_id ) {
      $metadata = wp_get_attachment_metadata( $attachment_id );
      if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
        $upload_dir = wp_upload_dir();
        $path = trailingslashit( $upload_dir['path'] );
        foreach ( $metadata['sizes'] as $size => $sizeinfo ) {
          $sized_file = $path . $sizeinfo['file'];
          if ( file_exists( $sized_file ) ) {
              wp_delete_file( $sized_file );
          }
        }
      }
    }

    protected function process_attachments( $attachments, $dry_run, $verbose, $skip_deletion ) {
      $cloud_provider = CloudProviderFactory::create( $this->cloud_provider_key );
      $uploader = new CloudAttachmentUploader( $cloud_provider );

      foreach ( $attachments as $attachment_id ) {
        if ( $verbose ) {
          WP_CLI::line( sprintf( 'Processing attachment ID: %d', $attachment_id ) );
        }

        if ( $dry_run ) {
          WP_CLI::line( sprintf( 'Would offload: %s', basename( get_attached_file( $attachment_id ) ) ) );
          continue;
        }

        $result = $uploader->uploadAttachment( $attachment_id, $skip_deletion );

        if ( $result ) {
          WP_CLI::success( sprintf( 'Successfully offloaded: %s', basename( get_attached_file( $attachment_id ) ) ) );
        } else {
          WP_CLI::warning( sprintf( 'Failed to offload: %s', basename( get_attached_file( $attachment_id ) ) ) );
        }
      }
    }
}
