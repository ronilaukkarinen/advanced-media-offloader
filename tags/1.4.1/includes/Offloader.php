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

	public function get_attachment_subdir( $attachment_id ) {
		// Check if already offlaoded, return advmo_path
		if ( $this->isOffloaded( $attachment_id ) ) {
			return get_post_meta( $attachment_id, 'advmo_path', true );
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$file_path = get_attached_file( $attachment_id );

		// For images, use the metadata 'file' if available
		if ( isset( $metadata['file'] ) ) {
			return trailingslashit( dirname( $metadata['file'] ) );
		}

		// For non-images, extract the year/month structure from the file path
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'];

		// Remove the base upload directory from the file path
		$relative_path = str_replace( $base_dir . '/', '', $file_path );

		// Extract the year/month
		$path_parts = explode( '/', $relative_path );
		if ( count( $path_parts ) >= 2 ) {
			return trailingslashit( $path_parts[0] . '/' . $path_parts[1] );
		}

		// Fallback: return empty string if we can't determine the structure
		return '';
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
			return false;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		// check if image & has multiple sizes
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $data ) {
				$file = get_attached_file( $attachment_id, true );
				$file = str_replace( wp_basename( $file ), $data['file'], $file );
				$uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );
				if ( ! $uploadResult ) {
					return false;
				}
				// Delete the sized file after successful upload
				if ( $this->shouldDeleteLocal() ) {
					$this->deleteLocalFile( $attachment_id, $data );
				}
			}
		}
		return true;
	}

	private function isOffloaded( $post_id ) {
		return (bool) get_post_meta( $post_id, 'advmo_offloaded', true );
	}

	private function shouldDeleteLocal() {
		$settings = get_option( 'advmo_settings' );
		return ! empty( $settings['delete_after_offload'] ) && $settings['delete_after_offload'] === '1';
	}

	private function deleteLocalFile( $attachment_id, $data ) {
		$file = get_attached_file( $attachment_id, true );
		$file = str_replace( wp_basename( $file ), $data['file'], $file );
		unlink( $file );
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
				$status .= ' on ' . gmdate( 'Y-m-d H:i:s', $offloaded_at );
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
}
