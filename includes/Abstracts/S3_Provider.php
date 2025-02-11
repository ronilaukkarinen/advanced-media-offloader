<?php

namespace Advanced_Media_Offloader\Abstracts;

abstract class S3_Provider {


	protected $s3Client;

	/**
	 * Get the client instance.
	 *
	 * @return mixed
	 */
	abstract protected function getClient();

	/**
	 * Get the credentials field for the UI.
	 *
	 * @return mixed
	 */
	abstract public function credentialsField();

	/**
	 * Check for required constants and return any that are missing.
	 *
	 * @param array $constants Associative array of constant names and messages.
	 * @return array Associative array of missing constants and their messages.
	 */
	protected function checkRequiredConstants( array $constants ) {
		$missingConstants = [];
		foreach ( $constants as $constant => $message ) {
			if ( ! defined( $constant ) ) {
				$missingConstants[ $constant ] = $message;
			}
		}
		return $missingConstants;
	}

	abstract function getBucket();

	abstract function getProviderName();

	abstract function getDomain();

	/**
	 * Upload a file to the specified bucket.
	 *
	 * @param string $file Path to the file to upload.
	 * @param string $key The key to store the file under in the bucket.
	 * @param string $bucket The bucket to upload the file to.
	 * @return string URL of the uploaded object.
	 */
	public function uploadFile( $file, $key ) {
		$client = $this->getClient();
		try {
			$result = $client->putObject([
				'Bucket' => $this->getBucket(),
				'Key' => $key,
				'SourceFile' => $file,
				'ACL' => 'public-read',
			]);
			return $client->getObjectUrl( $this->getBucket(), $key );
		} catch ( \Exception $e ) {
			error_log( "Advanced Media Offloader: Error uploading file to S3: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Check the connection to the service.
	 *
	 * @return mixed
	 */
	public function checkConnection() {
		$client = $this->getClient();
		try {
			// get bucket info
			$result = $client->headBucket([
				'Bucket' => $this->getBucket(),
				'@http'  => [
					'timeout' => 5,
				],
			]);
			return true;
		} catch ( \Exception $e ) {
			error_log( "Advanced Media Offloader: Error checking connection to S3: {$e->getMessage()}" );
			return false;
		}
	}

	public function TestConnectionHTMLButton() {
		$html = '<div class="advmo-test-connection-container">';
		$html .= '<button class="button advmo_js_test_connection">' . esc_html__( 'Test Connection', 'advanced-media-offloader' ) . '</button>';
		$html .= '<div class="advmo-test-connection-result">';
		$html .= '<p class="advmo-test-success" style="display: none;"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Connection successful!', 'advanced-media-offloader' ) . '</p>';
		$html .= '<p class="advmo-test-error" style="display: none;"><span class="dashicons dashicons-warning"></span> <span class="advmo-error-message"></span> ' . esc_html__( 'Connection Failed!', 'advanced-media-offloader' ) . '</p>';
		$html .= '</div>'; // Close advmo-test-connection-result
		$html .= '</div>'; // Close advmo-test-connection-container

		return $html;
	}
	private function getConstantCodes( $missingConstants ) {
		$html = '';
		foreach ( $missingConstants as $constant => $message ) {
			$html .= 'define(\'' . esc_html( $constant ) . '\', \'' . esc_html( sanitize_title( $message ) ) . '\');' . "\n";
		}
		return $html;
	}

	public function getCredentialsFieldHTML( $requiredConstants ) {
		$missingConstants = $this->checkRequiredConstants( $requiredConstants );
		$html = '<div class="advmo-credentials-container">';
		if ( ! empty( $missingConstants ) ) {
			$section_title = esc_html__( 'Missing Credentials Setup', 'advanced-media-offloader' );
			$section_description = sprintf( esc_html__( 'To enable cloud storage integration, you need to define the following constants in your %s file. Make sure to replace the placeholders with your actual credentials.', 'advanced-media-offloader' ), '<code>wp-config.php</code>' );
			$constantsCode = $this->getConstantCodes( $missingConstants );
			$security_note = sprintf(
				'%s We recommend using the %s file for enhanced security. This ensures your sensitive credentials are not exposed within the WordPress admin interface.',
				'<strong>Note:</strong>',
				'<code>wp-config.php</code>'
			);
			$html .= <<<HTML
				<div class="advmo-missing-constants">{$html}
				<h3>{$section_title}</h3>
				<p>{$section_description}</p>
				<pre class="advmo-code-snippet">{$constantsCode}</pre>
				<p>{$security_note}</p>
				</div>
			HTML;
		} else {
			$credentials_are_set = sprintf( esc_html__( '%s credentials are set in wp-config.php', 'advanced-media-offloader' ), $this->getProviderName() );
			$bucket_name = sprintf( esc_html__( 'Bucket: %s', 'advanced-media-offloader' ), $this->getBucket() );
			$test_connection_button = $this->TestConnectionHTMLButton();
			$html .= <<<HTML
					<div class="advmo-credentials-set">
						<p>{$credentials_are_set}</p>
						<p>{$bucket_name}</p>
					</div>
					{$test_connection_button}
			HTML;
		}

		$html .= '</div>'; // Close advmo-credentials-container

		return $html;
	}

	/**
	 * Delete a file from the specified bucket.
	 *
	 * @param int $attachment_id The WordPress attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public function deleteAttachment( $attachment_id ) {
		try {
			// First verify if this attachment is actually offloaded
			$is_offloaded = get_post_meta( $attachment_id, 'advmo_offloaded', true );
			if ( ! $is_offloaded ) {
				// Not offloaded, so nothing to delete from cloud
				return true;
			}

			// Get required meta data
			$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$advmo_path = get_post_meta( $attachment_id, 'advmo_path', true );

			// If meta is missing but marked as offloaded, clean up the inconsistent state
			if ( ! $attached_file || ! $advmo_path ) {
				error_log( "Advanced Media Offloader: Cleaning up inconsistent state for attachment {$attachment_id}" ); // phpcs:ignore
				delete_post_meta( $attachment_id, 'advmo_offloaded' );
				delete_post_meta( $attachment_id, 'advmo_path' );
				return true;
			}

			// Proceed with deletion
			$key = $this->getAttachmentKey( $attachment_id );
			$this->deleteS3Object( $key );

			if ( wp_attachment_is_image( $attachment_id ) ) {
				$base_dir = trailingslashit( dirname( $key ) );
				$this->deleteImageSizes( $attachment_id, $base_dir );
				$this->deleteImageBackupSizes( $attachment_id, $base_dir );
			}

			return true;
		} catch ( \Exception $e ) {
			error_log( "Advanced Media Offloader: Error deleting file from S3: {$e->getMessage()}" );
			return false;
		}
	}

	private function deleteImageSizes( $attachment_id, $base_dir ) {
		// Check if there are any thumbnails to delete
		$metadata = wp_get_attachment_metadata( $attachment_id );
		// Make sure $sizes is always defined to allow the removal of original images after the first foreach loop.
		$sizes = ! isset( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ? array() : $metadata['sizes'];

		foreach ( $sizes as $size => $sizeinfo ) {
			$thumbnail_key = $base_dir . $sizeinfo['file'];
			$this->deleteS3Object( $thumbnail_key );
		}
	}

	private function deleteImageBackupSizes( $attachment_id, $base_dir ) {
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

		if ( ! is_array( $backup_sizes ) ) {
			return;
		}

		foreach ( $backup_sizes as $size => $sizeinfo ) {
			$backup_key = $base_dir . $sizeinfo['file'];
			$this->deleteS3Object( $backup_key );
		}
	}

	private function getAttachmentKey( int $attachment_id ): string {
		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$advmo_path = get_post_meta( $attachment_id, 'advmo_path', true );

		if ( ! $attached_file || ! $advmo_path ) {
			throw new \Exception( "Unable to find S3 key for attachment ID {$attachment_id}" ); // phpcs:ignore
		}

		$file_name = basename( $attached_file );
		return trailingslashit( $advmo_path ) . $file_name;
	}

	private function deleteS3Object( string $key ): void {
		$client = $this->getClient();
		$bucket = $this->getBucket();

		$client->deleteObject([
			'Bucket' => $bucket,
			'Key'    => $key,
		]);
	}
}
