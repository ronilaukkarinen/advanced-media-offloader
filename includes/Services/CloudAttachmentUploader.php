<?php

namespace Advanced_Media_Offloader\Services;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Traits\OffloaderTrait;

class CloudAttachmentUploader {

  use OffloaderTrait;

  private S3_Provider $cloudProvider;

  public function __construct( S3_Provider $cloudProvider ) {
    $this->cloudProvider = $cloudProvider;

    // Add filters for URL modification
    add_filter( 'wp_get_attachment_url', [ $this, 'modifyAttachmentUrl' ], 10, 2 );
    add_filter( 'wp_calculate_image_srcset', [ $this, 'modifyImageSrcset' ], 10, 5 );
  }

  public function modifyAttachmentUrl( $url, $post_id ) {
    if ( $this->is_offloaded( $post_id ) ) {
      $subdir = get_post_meta( $post_id, 'advmo_path', true );
      $domain = rtrim( $this->cloudProvider->getDomain(), '/' );
      $subdir = ltrim( $subdir, '/' );
      return 'https://' . $domain . '/' . $subdir . basename( $url );
    }
    return $url;
  }

  public function modifyImageSrcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
    if ( $this->is_offloaded( $attachment_id ) ) {
      $domain = rtrim( $this->cloudProvider->getDomain(), '/' );
      $cdnUrl = 'https://' . $domain . '/';
      foreach ( $sources as $key => $source ) {
        $sources[ $key ]['url'] = str_replace(
          trailingslashit( wp_get_upload_dir()['baseurl'] ),
          $cdnUrl,
          $source['url']
        );
      }
    }
    return $sources;
  }

  public function uploadAttachment( int $attachment_id, bool $skipDeletion = false ): bool {
    try {
      // Get file path
      $file_path = get_attached_file( $attachment_id );
      if ( ! file_exists( $file_path ) ) {
        throw new \Exception( 'File does not exist' );
      }

      // Get upload directory info
      $upload_dir = wp_upload_dir();
      $base_dir = $upload_dir['basedir'];

      // Get the relative path maintaining WordPress's directory structure
      $relative_path = str_replace( $base_dir . '/', '', $file_path );
      $subdir = dirname( $relative_path );
      if ( $subdir === '.' ) {
        $subdir = '';
      } else {
        $subdir .= '/';
      }

      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'ADVMO Debug - subdir: ' . print_r( $subdir, true ) );
        error_log( 'ADVMO Debug - file_path: ' . print_r( $file_path, true ) );
      }

      // Upload file maintaining WordPress's directory structure
      $uploadResult = $this->cloudProvider->uploadFile( $file_path, $subdir . wp_basename( $file_path ) );

      if ( ! $uploadResult ) {
        update_post_meta( $attachment_id, 'advmo_offloaded', false );
        return false;
      }

      // Handle image sizes if this is an image
      $metadata = wp_get_attachment_metadata( $attachment_id );
      if ( ! empty( $metadata['sizes'] ) ) {
        foreach ( $metadata['sizes'] as $size => $data ) {
          $sized_file = str_replace( wp_basename( $file_path ), $data['file'], $file_path );
          if ( file_exists( $sized_file ) ) {
            $uploadResult = $this->cloudProvider->uploadFile( $sized_file, $subdir . wp_basename( $sized_file ) );
            if ( ! $uploadResult ) {
              update_post_meta( $attachment_id, 'advmo_offloaded', false );
              return false;
            }
          }
        }
      }

      // Update metadata to mark as offloaded
      update_post_meta( $attachment_id, 'advmo_path', $subdir );
      update_post_meta( $attachment_id, 'advmo_offloaded', true );
      update_post_meta( $attachment_id, 'advmo_offloaded_at', time() );
      update_post_meta( $attachment_id, 'advmo_provider', $this->cloudProvider->getProviderName() );
      update_post_meta( $attachment_id, 'advmo_bucket', $this->cloudProvider->getBucket() );

      // Update the attachment URL in WordPress
      $cdn_url = $this->getProviderUrl() . '/' . $subdir . wp_basename( $file_path );
      update_post_meta( $attachment_id, '_wp_attached_file', $subdir . wp_basename( $file_path ) );

      // Update the guid and attachment metadata
      global $wpdb;
      $wpdb->update(
        $wpdb->posts,
        [ 'guid' => $cdn_url ],
        [ 'ID' => $attachment_id ],
      );

      if ( ! empty( $metadata ) ) {
        $metadata['file'] = $subdir . wp_basename( $file_path );
        wp_update_attachment_metadata( $attachment_id, $metadata );
      }

      if ( ! $skipDeletion ) {
        $this->handleLocalFileDeletion( $attachment_id );
      }

      return true;

    } catch ( \Exception $e ) {
      error_log( 'Advanced Media Offloader - Upload Error: ' . $e->getMessage() );
      return false;
    }
  }

  public function uploadUpdatedAttachment( int $attachment_id, array $metadata ): bool {
    if ( $metadata ) {
      $file = get_attached_file( $attachment_id );
      $subdir = $this->get_attachment_subdir( $attachment_id );
      $uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );

      if ( ! $uploadResult ) {
        $this->logError( $attachment_id, 'Failed to upload resized main file to cloud storage.' );
        return false;
      }

      if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
        $metadata_sizes = $this->uniqueMetaDataSizes( $metadata['sizes'] );
        foreach ( $metadata_sizes as $size => $data ) {
          $pattern = '/\-e[0-9]+(?=\-)/';
          if ( ! preg_match( $pattern, $data['file'] ) ) {
            error_log( "{$data['file']} is not a valid size file name." );
            continue;
          }
          $file = get_attached_file( $attachment_id, true );
          $file = str_replace( wp_basename( $file ), $data['file'], $file );
          $uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );
          if ( ! $uploadResult ) {
            $this->logError( $attachment_id, "Failed to upload size '{$size}' to cloud storage." );
            return false;
          }
        }
      }
    }

    return false;
  }

  private function uploadToCloud( int $attachment_id ): bool {
    /**
     * Fires before the attachment is uploaded to the cloud.
     *
     * This action allows developers to perform tasks or logging before
     * the attachment is uploaded to the cloud.
     *
     * @param int $attachment_id
     */
    do_action( 'advmo_before_upload_to_cloud', $attachment_id );

    // remove error logs related to the attachment before starting the new upload process
    delete_post_meta( $attachment_id, 'advmo_error_log' );

    if ( ! $this->attachment_exists_on_disk( $attachment_id ) ) {
      return false;
    }

    $file = get_attached_file( $attachment_id );
    $subdir = $this->get_attachment_subdir( $attachment_id );
    $uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );

    if ( ! $uploadResult ) {
      $this->logError( $attachment_id, 'Failed to upload main file to cloud storage.' );
      return false;
    }

    $metadata = wp_get_attachment_metadata( $attachment_id );
    if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
      $metadata_sizes = $this->uniqueMetaDataSizes( $metadata['sizes'] );
      foreach ( $metadata_sizes as $size => $data ) {
        $file = get_attached_file( $attachment_id, true );
        $file = str_replace( wp_basename( $file ), $data['file'], $file );
        $uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );
        if ( ! $uploadResult ) {
          $this->logError( $attachment_id, "Failed to upload size '{$size}' to cloud storage." );
          return false;
        }
      }
    }

    $deleteLocalRule = $this->shouldDeleteLocal();
    if ( $deleteLocalRule !== 0 ) {
      $this->deleteLocalFile( $attachment_id, $deleteLocalRule );
    }

    /**
     * Fires after the attachment has been uploaded to the cloud.
     *
     * This action allows developers to perform additional tasks or logging after
     * the attachment has been uploaded to the cloud.
     *
     * @param int $attachment_id    The ID of the attachment that was processed.
     */
    do_action( 'advmo_after_upload_to_cloud', $attachment_id );

    return true;
  }

  private function logError( int $attachment_id, string $specificError ): void {
    $generalError = $specificError . ' Please review your Cloud provider credentials or connection settings. For more details, enable debug.log and check the logs.';

    $errorLog = get_post_meta( $attachment_id, 'advmo_error_log', true );
    if ( ! is_array( $errorLog ) ) {
      $errorLog = array();
    }

    $errorLog[] = $generalError;

    update_post_meta( $attachment_id, 'advmo_error_log', $errorLog );
    update_post_meta( $attachment_id, 'advmo_offloaded', false );
  }

  private function updateAttachmentMetadata( int $attachment_id ): void {
    update_post_meta( $attachment_id, 'advmo_path', $this->get_attachment_subdir( $attachment_id ) );
    update_post_meta( $attachment_id, 'advmo_offloaded', true );
    update_post_meta( $attachment_id, 'advmo_offloaded_at', time() );
    update_post_meta( $attachment_id, 'advmo_provider', $this->cloudProvider->getProviderName() );
    update_post_meta( $attachment_id, 'advmo_bucket', $this->cloudProvider->getBucket() );
  }

  private function deleteLocalFile( int $attachment_id, int $deleteLocalRule ): bool {
    /**
     * Fires before the local file(s) associated with an attachment are deleted.
     *
     * This action allows developers to perform tasks or logging before
     * the local files are removed following a successful cloud upload.
     *
     * @param int $attachment_id    The ID of the attachment to be processed.
     * @param int $deleteLocalRule  The rule to be applied for local file deletion:
     *                              1 - Delete only sized images, keep original.
     *                              2 - Delete all local files including the original.
     */
    do_action( 'advmo_before_delete_local_file', $attachment_id, $deleteLocalRule );

    $original_file = get_attached_file( $attachment_id, true );

    if ( ! file_exists( $original_file ) ) {
      error_log( "Advanced Media Offloader: Original file not found for deletion: $original_file" );
      return false;
    }

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

    if ( $deleteLocalRule === 2 ) {
      wp_delete_file( $original_file );
    }

    update_post_meta( $attachment_id, 'advmo_retention_policy', $deleteLocalRule );

    /**
     * Fires after the local file(s) associated with an attachment have been deleted.
     *
     * This action allows developers to perform additional tasks or logging after
     * the local files have been removed following a successful cloud upload.
     *
     * @param int $attachment_id    The ID of the attachment that was processed.
     * @param int $deleteLocalRule  The rule applied for local file deletion:
     *                              1 - Delete only sized images, keep original.
     *                              2 - Delete all local files including the original.
     */
    do_action( 'advmo_after_delete_local_file', $attachment_id, $deleteLocalRule );

    return true;
  }

  protected function attachment_exists_on_disk( $attachment_id ) {
    $errors = array();

    // Get the full path using WordPress upload directory
    $file_path = $this->get_wordpress_upload_path( $attachment_id );

    // Check if the main file exists
    if ( ! file_exists( $file_path ) ) {
      $errors[] = "Main file does not exist: {$file_path}";
    }

    // If it's an image, check all sizes
    if ( wp_attachment_is_image( $attachment_id ) ) {
      $metadata = wp_get_attachment_metadata( $attachment_id );
      if ( ! empty( $metadata['sizes'] ) ) {
        $file_dir = trailingslashit( dirname( $file_path ) );

        foreach ( $metadata['sizes'] as $size => $size_info ) {
          $size_file_path = $file_dir . $size_info['file'];
          if ( ! file_exists( $size_file_path ) ) {
            $errors[] = "Size '{$size}' does not exist: {$size_file_path}";
          }
        }
      }
    }

    // Save errors to post meta
    if ( ! empty( $errors ) ) {
      update_post_meta( $attachment_id, 'advmo_error_log', $errors );
    } else {
      delete_post_meta( $attachment_id, 'advmo_error_log' );
    }

    return empty( $errors );
  }

  protected function get_wordpress_upload_path( $attachment_id ) {
    // Get WordPress upload directory information
    $upload_dir = wp_upload_dir();

    // First try to get the file path directly
    $file_path = get_attached_file( $attachment_id );

    // Get the stored path from WordPress metadata
    $stored_path = get_post_meta( $attachment_id, '_wp_attached_file', true );

    // If the direct file path doesn't exist, try constructing it from the stored path
    if ( ! file_exists( $file_path ) && $stored_path ) {
      // Remove any potential absolute path components for security
      $stored_path = ltrim( $stored_path, '/' );
      $file_path = path_join( $upload_dir['basedir'], $stored_path );
    }

    // If still no file found, check if there's a custom upload path set
    if ( ! file_exists( $file_path ) ) {
      $custom_upload_path = get_option( 'upload_path' );
      if ( $custom_upload_path && $stored_path ) {
        $file_path = path_join( $custom_upload_path, $stored_path );
      }
    }

    return $file_path;
  }

  protected function getProviderUrl() {
    if ( method_exists( $this->cloudProvider, 'getBaseUrl' ) ) {
      return rtrim( $this->cloudProvider->getBaseUrl(), '/' );
    }
    return rtrim( $this->cloudProvider->getDomain(), '/' );
  }

  private function handleLocalFileDeletion( $attachment_id ): void {
    $settings = get_option('advmo_settings');
    $deleteLocalRule = isset($settings['retention_policy']) ? (int) $settings['retention_policy'] : 0;

    if ($deleteLocalRule === 0) {
      return;
    }

    $file = get_attached_file($attachment_id, true);

    // Delete resized versions
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (!empty($metadata['sizes'])) {
      $upload_dir = wp_upload_dir();
      $base_dir = dirname($file);

      foreach ($metadata['sizes'] as $size) {
        $sized_file = $base_dir . '/' . $size['file'];
        if (file_exists($sized_file)) {
          unlink($sized_file);
        }
      }
    }

    // Delete original file only if full migration is enabled
    if ($deleteLocalRule === 2 && file_exists($file)) {
      unlink($file);
    }

    update_post_meta($attachment_id, 'advmo_retention_policy', $deleteLocalRule);
  }
}
