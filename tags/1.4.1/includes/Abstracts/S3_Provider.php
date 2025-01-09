<?php

namespace Advanced_Media_Offloader\Abstracts;

abstract class S3_Provider
{

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
	protected function checkRequiredConstants(array $constants)
	{
		$missingConstants = [];
		foreach ($constants as $constant => $message) {
			if (!defined($constant)) {
				$missingConstants[$constant] = $message;
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
	public function uploadFile($file, $key)
	{
		$client = $this->getClient();
		try {
			$client->putObject([
				'Bucket' => $this->getBucket(),
				'Key' => $key,
				'SourceFile' => $file,
				'ACL' => 'public-read',
			]);
			return $client->getObjectUrl($this->getBucket(), $key);
		} catch (\Exception $e) {
			error_log("Advanced Media Offloader: Error uploading file to S3: {$e->getMessage()}");
			return false;
		}
	}

	/**
	 * Check the connection to the service.
	 *
	 * @return mixed
	 */
	public function checkConnection()
	{
		$client = $this->getClient();
		try {
			# get bucket info
			$result = $client->headBucket([
				'Bucket' => $this->getBucket(),
				'@http'  => [
					'timeout' => 5,
				],
			]);
			return true;
		} catch (\Exception $e) {
			error_log("Advanced Media Offloader: Error checking connection to S3: {$e->getMessage()}");
			return false;
		}
	}

	public function TestConnectionHTMLButton()
	{
		$html = '<div class="advmo-test-connection-container">';
		$html .= '<button class="button advmo_js_test_connection">' . esc_html__('Test Connection', 'advanced-media-offloader') . '</button>';
		$html .= '<div class="advmo-test-connection-result">';
		$html .= '<p class="advmo-test-success" style="display: none;"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Connection successful!', 'advanced-media-offloader') . '</p>';
		$html .= '<p class="advmo-test-error" style="display: none;"><span class="dashicons dashicons-warning"></span> <span class="advmo-error-message"></span> ' . esc_html__('Connection Failed!', 'advanced-media-offloader') . '</p>';
		$html .= '</div>'; // Close advmo-test-connection-result
		$html .= '</div>'; // Close advmo-test-connection-container

		return $html;
	}

	public function getCredentialsFieldHTML($requiredConstants)
	{
		$missingConstants = $this->checkRequiredConstants($requiredConstants);
		$html = '<div class="advmo-credentials-container">';
		if (!empty($missingConstants)) {
			$html .= '<div class="advmo-missing-constants">';
			$html .= '<h3>' . esc_html__('Missing Constants', 'advanced-media-offloader') . '</h3>';
			$html .= '<p>' . esc_html__('Please define the following constants in wp-config.php:', 'advanced-media-offloader') . '</p>';
			$html .= '<ul>';
			foreach ($missingConstants as $constant => $message) {
				$html .= '<li><code>' . esc_html($constant) . '</code></li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		} else {
			$html .= '<div class="advmo-credentials-set">';
			$html .= '<p>' . sprintf(esc_html__('%s credentials are set in wp-config.php', 'advanced-media-offloader'), $this->getProviderName()) . '</p>';
			// use $provider_name instead of MinIO by refactoring the above line
			$html .= '<p>' . sprintf(esc_html__('Bucket: %s', 'advanced-media-offloader'), $this->getBucket()) . '</p>';


			$html .= '</div>';
			$html .= $this->TestConnectionHTMLButton();
		}

		$html .= '</div>'; // Close advmo-credentials-container

		return $html;
	}
}
