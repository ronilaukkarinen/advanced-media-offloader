<?php

namespace Advanced_Media_Offloader;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\BulkOffloadHandler;

class Offloader {

	private static $instance = null;
	public $cloudProvider;

	private function __construct( S3_Provider $cloudProvider ) {
		$this->cloudProvider = $cloudProvider;
	}

	public static function get_instance( S3_Provider $cloudProvider ) {
		if ( self::$instance === null ) {
			self::$instance = new self( $cloudProvider );
		}
		return self::$instance;
	}

	public function initializeHooks() {
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'handleAttachmentUpload' ], 10, 2 );
		add_filter( 'wp_get_attachment_url', [ $this, 'modifyAttachmentUrl' ], 10, 2 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'modifyImageSrcset' ], 10, 5 );
		add_filter( 'attachment_fields_to_edit', [ $this, 'displayOffloadStatus' ], 10, 2 );
		add_action( 'delete_attachment', [ $this, 'deleteCloudFiles' ], 10, 2 );
	}

	public function modifyAttachmentUrl( $url, $post_id ) {
		if ( $this->isOffloaded( $post_id ) ) {
			$subDir = $this->get_attachment_subdir( $post_id );
			$url = $this->cloudProvider->getDomain() . $subDir . basename( $url );
		}
		return $url;
	}

	public function modifyImageSrcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( $this->isOffloaded( $attachment_id ) ) {
			$cdnUrl = trailingslashit( $this->cloudProvider->getDomain() );
			foreach ( $sources as $key => $source ) {
				$sources[ $key ]['url'] = str_replace( trailingslashit( wp_get_upload_dir()['baseurl'] ), $cdnUrl, $source['url'] );
			}
		}
		return $sources;
	}

	/**
	 * Get the path prefix for offloaded files.
	 *
	 * @return string The sanitized path prefix or an empty string if not active.
	 */
	private function get_path_prefix(): string {
		$settings = get_option( 'advmo_settings', [] );
		$prefix_active = $settings['path_prefix_active'] ?? false;
		$path_prefix = $settings['path_prefix'] ?? '';

		if ( ! $prefix_active || empty( $path_prefix ) ) {
			return '';
		}

		return trailingslashit( advmo_sanitize_path( $path_prefix ) );
	}

	private function get_object_version( $attachment_id ) {
		$advmo_settings = get_option( 'advmo_settings' );
		$object_versioning = isset( $advmo_settings['object_versioning'] ) ? $advmo_settings['object_versioning'] : '0';

		// If versioning is not enabled, return an empty string
		if ( ! $object_versioning ) {
			return '';
		}

		// Check if we already have a version for this attachment
		$existing_version = get_post_meta( $attachment_id, 'advmo_object_version', true );
		if ( $existing_version ) {
			return trailingslashit( $existing_version );
		}

		// Generate a new version
		if ( ! advmo_is_media_organized_by_year_month() ) {
			$new_version = date( 'YmdHis' );
		} else {
			$new_version = date( 'dHis' );
		}

		// Save the new version in post meta
		update_post_meta( $attachment_id, 'advmo_object_version', $new_version );

		return trailingslashit( $new_version );
	}

	public function get_attachment_subdir( $attachment_id ) {
		// Check if already offlaoded, return advmo_path
		if ( $this->isOffloaded( $attachment_id ) ) {
			return get_post_meta( $attachment_id, 'advmo_path', true );
		}

		$object_version = $this->get_object_version( $attachment_id );
		$path_prefix = $this->get_path_prefix();

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$file_path = get_attached_file( $attachment_id );

		// For images, use the metadata 'file' if available
		if ( isset( $metadata['file'] ) ) {
			$dirname = advmo_is_media_organized_by_year_month() ? trailingslashit( dirname( $metadata['file'] ) ) : '';
			return $path_prefix . $dirname . $object_version;
		}

		// For non-images, extract the year/month structure from the file path
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'];

		// Remove the base upload directory from the file path
		$relative_path = str_replace( $base_dir . '/', '', $file_path );

		// Extract the year/month
		$path_parts = explode( '/', trim( $relative_path, '/' ), 3 );

		$response = '';
		if ( count( $path_parts ) >= 2 && is_numeric( $path_parts[0] ) && is_numeric( $path_parts[1] ) ) {
			$response = trailingslashit( $path_parts[0] . '/' . $path_parts[1] );
		}

		// Fallback: return empty string if we can't determine the structure
		return $path_prefix . $response . $object_version;
	}

	public function handleAttachmentUpload( $metadata, $attachment_id ) {
		$current_filter = current_filter();
		if ( ! $this->isOffloaded( $attachment_id ) ) {
			if ( $this->uploadToCloud( $attachment_id ) ) {
				update_post_meta( $attachment_id, 'advmo_path', $this->get_attachment_subdir( $attachment_id ) ); // save this first to avoid issues with path not being set correctly
				update_post_meta( $attachment_id, 'advmo_offloaded', true );
				update_post_meta( $attachment_id, 'advmo_offloaded_at', time() );
				update_post_meta( $attachment_id, 'advmo_provider', $this->cloudProvider->getProviderName() );
				update_post_meta( $attachment_id, 'advmo_bucket', $this->cloudProvider->getBucket() );
			} elseif ( $current_filter == 'wp_generate_attachment_metadata' ) {
					wp_delete_attachment( $attachment_id, true );
			}
		}
		return $metadata;
	}

	private function uploadToCloud( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		$subdir = $this->get_attachment_subdir( $attachment_id );
		$uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );

		if ( ! $uploadResult ) {
			update_post_meta( $attachment_id, 'advmo_offloaded', false );
			return false;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		// check if image & has multiple sizes
		if ( ! empty( $metadata['sizes'] ) ) {
			$metadata_sizes = $this->uniqueMetaDataSizes( $metadata['sizes'] );
			foreach ( $metadata_sizes as $size => $data ) {
				$file = get_attached_file( $attachment_id, true );
				$file = str_replace( wp_basename( $file ), $data['file'], $file );
				$uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );
				if ( ! $uploadResult ) {
					update_post_meta( $attachment_id, 'advmo_offloaded', false );
					return false;
				}
			}
		}
		// Delete the sized file after successful upload
		$deleteLocalRule = $this->shouldDeleteLocal();
		if ( $deleteLocalRule !== 0 ) {
			$this->deleteLocalFile( $attachment_id, $deleteLocalRule );
		}
		return true;
	}

	private function uniqueMetaDataSizes( $sizes ) {
		$uniqueSizes = [];
		$dimensionMap = [];

		foreach ( $sizes as $name => $sizeInfo ) {
			$dimension = $sizeInfo['width'] . 'x' . $sizeInfo['height'];

			if ( ! isset( $dimensionMap[ $dimension ] ) ) {
				$dimensionMap[ $dimension ] = $name;
				$uniqueSizes[ $name ] = $sizeInfo;
			} else {
				// If this size has a larger filesize, replace the existing one
				$existingName = $dimensionMap[ $dimension ];
				if ( $sizeInfo['filesize'] > $uniqueSizes[ $existingName ]['filesize'] ) {
					unset( $uniqueSizes[ $existingName ] );
					$dimensionMap[ $dimension ] = $name;
					$uniqueSizes[ $name ] = $sizeInfo;
				}
			}
		}

		return $uniqueSizes;
	}

	private function isOffloaded( $post_id ) {
		return (bool) get_post_meta( $post_id, 'advmo_offloaded', true );
	}

	private function shouldDeleteLocal() {
		$settings = get_option( 'advmo_settings' );
		$retention_policy = isset( $settings['retention_policy'] ) ? $settings['retention_policy'] : '0';

		// Ensure the value is a string and convert it to an integer
		return intval( (string) $retention_policy );
	}

	private function deleteLocalFile( $attachment_id, $deleteLocalRule ) {
		// Get the full path of the original file
		$original_file = get_attached_file( $attachment_id, true );

		if ( ! file_exists( $original_file ) ) {
			error_log( "Advanced Media Offloader: Original file not found for deletion: $original_file" );
			return false;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		// Delete resized versions
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

		// Delete the original file if $deleteLocalRule is 2
		if ( $deleteLocalRule === 2 ) {
			wp_delete_file( $original_file );
		}

		update_post_meta( $attachment_id, 'advmo_retention_policy', $deleteLocalRule );

		return true;
	}

	public function displayOffloadStatus( $form_fields, $post ) {
		if ( $this->isOffloaded( $post->ID ) ) {
			$offloaded_at = get_post_meta( $post->ID, 'advmo_offloaded_at', true );
			$provider = get_post_meta( $post->ID, 'advmo_provider', true );
			$bucket = get_post_meta( $post->ID, 'advmo_bucket', true );

			$status = "Offloaded to $provider";
			if ( $bucket ) {
				$status .= " (Bucket: $bucket)";
			}
			if ( $offloaded_at ) {
				$status .= ' on ' . date( 'Y-m-d H:i:s', $offloaded_at );
			}
			$color = 'green';
		} else {
			$status = 'Not offloaded';
			$color = 'red';
		}

		$form_fields['advmo_offload_status'] = array(
			'label' => __( 'Offload Status:', 'advanced-media-offloader' ),
			'input' => 'html',
			'html'  => "
            <div style='
                display: flex;
                align-items: center;
                height: 100%;
                min-height: 30px;
            '>
                <span style='color: $color;'>$status</span>
            </div>",
		);

		return $form_fields;
	}

	/**
	 * Check if cloud files should be deleted based on settings and post type.
	 *
	 * @param WP_Post $post The post object.
	 * @return bool
	 */
	private function shouldDeleteCloudFiles( $post ) {
		$advmo_settings = get_option( 'advmo_settings' );
		$delete_after_offload = isset( $advmo_settings['mirror_delete'] ) && $advmo_settings['mirror_delete'] === '1';

		return $delete_after_offload && $post->post_type === 'attachment' && $this->isOffloaded( $post->ID );
	}


	/**
	 * Handle errors during cloud file deletion.
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $error_message The error message.
	 * @return void
	 */
	private function handleDeletionError( $post_id, $error_message ) {
		$log_message = "Cloud file deletion failed for attachment ID: {$post_id}. " .
			'The file remains in the cloud storage and locally due to an error. ' .
			'Please try again or contact support if the issue persists.';

		error_log( $log_message );

		// Add a notice to the dashboard
		add_action('admin_notices', function () use ( $error_message ) {
			echo '<div class="error"><p>' . esc_html( $error_message ) . '</p></div>';
		});

		wp_die( 'Error deleting file from cloud provider: ' . esc_html( $error_message ) );
	}

	/**
	 * Perform the actual deletion of cloud files.
	 *
	 * @param int $post_id The ID of the post.
	 * @return void
	 */
	private function performCloudFileDeletion( $post_id ) {
		try {
			$result = $this->cloudProvider->deleteFile( $post_id );
			if ( ! $result ) {
				throw new \Exception( 'Cloud file deletion failed' );
			}
		} catch ( \Exception $e ) {
			$this->handleDeletionError( $post_id, $e->getMessage() );
		}
	}

	public function deleteCloudFiles( $post_id, $post ) {

		if ( ! $this->shouldDeleteCloudFiles( $post ) ) {
			return;
		}

		$this->performCloudFileDeletion( $post_id );
	}
}
