<?php

namespace Advanced_Media_Offloader\CLI;

use WP_CLI;
use Advanced_Media_Offloader\Factories\CloudProviderFactory;
use Advanced_Media_Offloader\Services\BulkMediaOffloader;

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
            $cloud_provider_key = advmo_get_cloud_provider_key();
            if ( empty( $cloud_provider_key ) ) {
                WP_CLI::error( 'No cloud provider configured. Please configure a provider in the plugin settings first.' );
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
            if ( empty( $settings['provider'] ) || empty( $settings['bucket'] ) ) {
                WP_CLI::error( 'Cloud provider settings are incomplete. Please configure the plugin settings first.' );
                return;
            }

            // Validate we can connect to the cloud provider
            try {
                $cloud_provider = CloudProviderFactory::create( $cloud_provider_key );
                $cloud_provider->validateConnection();
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
            $provider_name = $provider_names[ $cloud_provider_key ] ?? $cloud_provider_key;

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

            $cloud_provider = CloudProviderFactory::create( $cloud_provider_key );
            $bulk_offloader = new BulkMediaOffloader( $cloud_provider );
            $bulk_offloader->setSkipDeletion( true );

            WP_CLI::line( sprintf( 'Found %d files to offload', $total ) );
            $progress = \WP_CLI\Utils\make_progress_bar( 'Offloading files', $total );

            foreach ( $attachments as $attachment_id ) {
                try {
                    $file_path = get_attached_file( $attachment_id );
                    $file_meta = wp_get_attachment_metadata( $attachment_id );

                    if ( $verbose ) {
                        WP_CLI::line( "\n=== Debug Information ===" );
                        WP_CLI::line( "Attachment ID: {$attachment_id}" );
                        WP_CLI::line( "File path: {$file_path}" );
                        WP_CLI::line( 'File exists before upload: ' . ( file_exists( $file_path ) ? 'yes' : 'no' ) );
                        WP_CLI::line( "Delete Local Rule: {$deleteLocalRule}" );
                        WP_CLI::line( 'File permissions: ' . substr( sprintf( '%o', fileperms( $file_path ) ), -4 ) );
                        WP_CLI::line( 'PHP user: ' . exec( 'whoami' ) );
                        WP_CLI::line( 'Is symlink: ' . ( is_link( $file_path ) ? 'yes' : 'no' ) );
                        WP_CLI::line( 'Real path: ' . realpath( $file_path ) );
                        WP_CLI::line( '========================' );
                    }

                    // Skip if file doesn't exist
                    if ( ! file_exists( $file_path ) ) {
                        WP_CLI::warning( sprintf( '✗ Skipped: %s (File not found)', basename( $file_path ) ) );
                        continue;
                    }

                    // Upload without deletion
                    $bulk_offloader->task( $attachment_id );
                    WP_CLI::line( sprintf( '✓ Offloaded: %s', basename( $file_path ) ) );

                    // Ask about deletion
                    if ( $deleteLocalRule > 0 && ! $dry_run ) {
                        if ( $verbose ) {
                            WP_CLI::line( 'Asking for deletion confirmation...' );
                        }

                        $confirmed = false;
                        if ( $skip_confirm ) {
                            $confirmed = true;
                            if ( $verbose ) {
                                WP_CLI::line( 'Auto-confirming due to --yes flag' );
                            }
                        } else {
                            fwrite( STDOUT, 'Delete local file ' . basename( $file_path ) . '? [y/n]: ' );
                            $answer = strtolower( trim( fgets( STDIN ) ) );
                            $confirmed = ( $answer === 'y' || $answer === 'yes' );
                        }

                        if ( $verbose ) {
                            WP_CLI::line( 'Confirmation result: ' . ( $confirmed ? 'yes' : 'no' ) );
                        }

                        if ( $confirmed ) {
                            if ( $verbose ) {
                                WP_CLI::line( "\n=== Starting Deletion Process ===" );
                            }

                            $real_path = realpath( $file_path );

                            if ( $verbose ) {
                                WP_CLI::line( "Using direct file operations to delete: {$real_path}" );
                            }

                            if ( file_exists( $real_path ) ) {
                                // Direct file deletion
                                $result = @unlink( $real_path );

                                if ( $verbose ) {
                                    WP_CLI::line( 'Deletion result: ' . ( $result ? 'Success' : 'Failed' ) );
                                    if ( ! $result ) {
                                        WP_CLI::line( 'PHP error: ' . error_get_last()['message'] ?? 'No error message' );
                                    }
                                }

                                // Verify deletion
                                clearstatcache( true, $real_path );
                                if ( file_exists( $real_path ) ) {
                                    WP_CLI::error( "Failed to delete file: {$real_path}" );
                                } elseif ( $verbose ) {
                                        WP_CLI::success( 'File successfully deleted' );
                                }
                            } elseif ( $verbose ) {
                                    WP_CLI::warning( "File not found for deletion: {$real_path}" );
                            }
                        } elseif ( $verbose ) {
                                WP_CLI::line( 'Deletion skipped - user did not confirm' );
                        }
                    }
                } catch ( \Exception $e ) {
                    WP_CLI::warning( sprintf( '✗ Failed to process %s: %s', basename( $file_path ), $e->getMessage() ) );
                }
                $progress->tick();
            }

            $progress->finish();
            WP_CLI::success( 'Bulk offload completed.' );

        } catch ( \Exception $e ) {
            WP_CLI::error( 'Error during offload: ' . $e->getMessage() );
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
}
