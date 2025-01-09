<?php

namespace Advanced_Media_Offloader\Admin;

class GeneralSettings
{
	protected $cloud_providers;

	public function __construct($cloud_providers)
	{
		$this->cloud_providers = $cloud_providers;
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'initialize']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_advmo_test_connection', [$this, 'check_connection_ajax']);
		\Advanced_Media_Offloader\BulkOffloadHandler::get_instance();
	}

	public function initialize()
	{
		register_setting('advmo', 'advmo_settings', [$this, 'sanitize']);

		$this->add_settings_section();
		$this->add_provider_field();
		$this->add_credentials_field();
		$this->add_delete_after_upload_field();
		$this->add_non_offloaded_media_field();
	}

	private function add_settings_section()
	{
		add_settings_section(
			'default',
			__('Settings', 'advanced-media-offloader'),
			function () {
				echo '<p>' . esc_attr__('Select a cloud storage provider and provide the necessary credentials.', 'advanced-media-offloader') . '</p>';
				echo '<p>' . esc_attr__('Please note that Advanced Media Offloader will only offload media uploaded from now on and won\'t offload your previously uploaded media.', 'advanced-media-offloader') . '</p>';
			},
			'advmo'
		);
	}

	private function add_provider_field()
	{
		add_settings_field(
			'cloud_provider',
			__('Cloud Provider', 'advanced-media-offloader'),
			[$this, 'cloud_provider_field'],
			'advmo',
			'default'
		);
	}

	private function add_credentials_field()
	{
		add_settings_field(
			'advmo_cloud_provider_credentials',
			__('Credentials', 'advanced-media-offloader'),
			[$this, 'cloud_provider_credentials_field'],
			'advmo',
			'default'
		);
	}

	private function add_delete_after_upload_field()
	{
		add_settings_field(
			'delete_after_offload',
			__('Delete After Offload', 'advanced-media-offloader'),
			[$this, 'delete_after_offload_field'],
			'advmo',
			'default'
		);
	}

	private function add_non_offloaded_media_field()
	{
		add_settings_field(
			'non_offloaded_media',
			__('Local Media Overview', 'advanced-media-offloader'),
			[$this, 'non_offloaded_media_field'],
			'advmo',
			'default'
		);
	}

	public function non_offloaded_media_field()
	{
		$bulk_offload_data = advmo_get_bulk_offload_data();
		$count = $this->count_non_offloaded_media();
		$is_offloading = $bulk_offload_data['status'] === 'processing';
		$progress = ($is_offloading && $bulk_offload_data['total'] > 0) ? ($bulk_offload_data['processed'] / $bulk_offload_data['total']) * 100 : 0;

		if ($count > 0 || $is_offloading) {
			if (!$is_offloading) {
				echo '<p>' . sprintf(
					_n(
						'%d file is still hosted on your server',
						'%d files are still hosted on your server',
						$count,
						'advanced-media-offloader'
					),
					$count
				) . '</p>';
				echo '<p class="description">' . __('Offload these files to cloud storage now to free up space and boost website performance.', 'advanced-media-offloader') . '</p>';
				echo '<button type="button" id="bulk-offload-button" class="button">' . __('Bulk Offload Now', 'advanced-media-offloader') . '</button>';
				echo '<p class="description">' . __('This process will run in the background, so you can close this page after starting.', 'advanced-media-offloader') . '</p>';

				// Add information about batch size limitation
				echo '<p class="description" style="margin-top: 15px;"><strong>' . __('Note:', 'advanced-media-offloader') . '</strong> ';
				echo sprintf(
					__('The free version of Advanced Media Offloader processes up to 50 media files per batch. If you have more than 50 files, you\'ll need to run the bulk import multiple times or %supgrade to the pro version%s for unlimited batch processing.', 'advanced-media-offloader'),
					'<a href="https://wpfitter.com/contact/?utm_source=advanced-media-offloader&utm_medium=settings&utm_campaign=bulk-offload-limit" target="_blank">',
					'</a>'
				) . '</p>';
			}

			$display_style = $is_offloading ? 'block' : 'none';
			$progress_width = $is_offloading ? $progress : 0;
			$progress_text = $is_offloading ? round($progress) . '%' : '0%';
			if ($is_offloading && $bulk_offload_data['total'] == 0) {
				$progress_text = __('Preparing...', 'advanced-media-offloader');
			}

			$progress_status = $is_offloading ? 'processing' : 'idle';
			$processed = $bulk_offload_data['processed'] ?? 0;
			$total = $bulk_offload_data['total'] ?? 0;

			echo '<div id="progress-container" style="display: ' . esc_attr($display_style) . '; margin-top: 20px;" data-status="' . esc_attr($progress_status) . '">';
			echo '<p id="progress-title" style="font-size: 16px; font-weight: bold;">' .
				sprintf(
					__('Offloading media files to cloud storage (%1$s of %2$s)', 'advanced-media-offloader'),
					'<span id="processed-count">' . esc_html($processed) . '</span>',
					'<span id="total-count">' . esc_html($total) . '</span>'
				) .
				'</p>';
			echo '    <div class="progress-bar-container" style="width: 100%; background-color: #e0e0e0; padding: 3px; border-radius: 3px;">';
			printf('        <div id="offload-progress" style="width: %.1f%%; height: 20px; background-color: #0073aa; border-radius: 2px; transition: width 0.5s;"></div>', esc_html($progress_width));
			echo '    </div>';
			printf('    <p id="progress-text" style="margin-top: 10px; font-weight: bold;">%s</p>', esc_html($progress_text));
			echo '</div>';
		} else {
			echo '<p>' . __('All media files are currently stored in the cloud.', 'advanced-media-offloader') . '</p>';
		}
	}

	public function delete_after_offload_field()
	{
		$options = get_option('advmo_settings');
		$delete_after_offload = isset($options['delete_after_offload']) ? $options['delete_after_offload'] : 0;

		echo '<input type="checkbox" id="delete_after_offload" name="advmo_settings[delete_after_offload]" value="1" ' . checked(1, $delete_after_offload, false) . '/>';
		echo '<label for="delete_after_offload">' . esc_html__('Delete image from server after successful upload to the cloud storage', 'advanced-media-offloader') . '</label>';
		echo '<p class="description">' . esc_html__('Note: The original image will remain on the server for backup purposes.', 'advanced-media-offloader') . '</p>';
	}

	public function sanitize($options)
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$options['cloud_provider'] = sanitize_text_field($options['cloud_provider'] ?? '');

		if (!array_key_exists($options['cloud_provider'], $this->cloud_providers)) {
			add_settings_error('advmo_messages', 'advmo_message', __('Invalid Cloud Provider!', 'advanced-media-offloader'), 'error');
			return $options;
		}

		$options['delete_local'] = isset($options['delete_local']) ? 1 : 0;

		add_settings_error('advmo_messages', 'advmo_message', __('Settings Saved', 'advanced-media-offloader'), 'updated');
		return $options;
	}

	public function add_settings_page()
	{
		add_menu_page(
			__('Advanced Media Offloader', 'advanced-media-offloader'),
			__('Media Offloader', 'advanced-media-offloader'),
			'manage_options',
			'advmo',
			[$this, 'settings_page_view'],
			'dashicons-cloud-upload',
			100
		);
	}

	public function cloud_provider_field($args)
	{
		$options = get_option('advmo_settings');
		echo '<select name="advmo_settings[cloud_provider]">';
		foreach ($this->cloud_providers as $key => $provider) {
			$disabled = $provider['class'] === null ? 'disabled' : '';
			$selected = selected($options['cloud_provider'], $key, false);
			echo '<option value="' . esc_attr($key) . '" ' . esc_attr($selected) . ' ' . esc_attr($disabled) . '>' . esc_html($provider['name']) . '</option>';
		}
		echo '</select>';
	}

	public function cloud_provider_credentials_field()
	{
		$options = get_option('advmo_settings');
		$cloud_provider = $options['cloud_provider'] ?? '';
		if (!empty($cloud_provider) && $this->cloud_providers[$cloud_provider]['class'] !== null) {
			$cloud_provider_class = $this->cloud_providers[$cloud_provider]['class'];
			$cloud_provider_instance = new $cloud_provider_class();
			$cloud_provider_instance->credentialsField();
		}
	}

	public function settings_page_view()
	{
		include ADVMO_PATH . 'templates/admin/settings.php';
	}

	public function enqueue_scripts()
	{
		if (!isset($_GET['page']) || $_GET['page'] !== 'advmo') {
			return;
		}
		wp_enqueue_script('advmo_settings', ADVMO_URL . 'assets/js/advmo_settings.js', [], ADVMO_VERSION, true);
		wp_localize_script('advmo_settings', 'advmo_ajax_object', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('advmo_test_connection'),
			'bulk_offload_nonce' => wp_create_nonce('advmo_bulk_offload')
		]);
		wp_enqueue_style('advmo_admin', ADVMO_URL . 'assets/css/admin.css', [], ADVMO_VERSION);
	}

	public function check_connection_ajax()
	{
		$security_nonce = sanitize_text_field($_POST['security_nonce']) ?? '';
		if (!wp_verify_nonce($security_nonce, 'advmo_test_connection')) {
			wp_send_json_error(['message' => __('Invalid nonce!', 'advanced-media-offloader')]);
		}

		$options = get_option('advmo_settings');
		$cloud_provider = $options['cloud_provider'] ?? '';
		if (!empty($cloud_provider) && $this->cloud_providers[$cloud_provider]['class'] !== null) {
			$cloud_provider_class = $this->cloud_providers[$cloud_provider]['class'];
			$cloud_provider_instance = new $cloud_provider_class();
			$result = $cloud_provider_instance->checkConnection();
			if ($result) {
				wp_send_json_success(['message' => __('Connection successful!', 'advanced-media-offloader')]);
			} else {
				wp_send_json_error(['message' => __('Connection failed!', 'advanced-media-offloader')], 401);
			}
		} else {
			wp_send_json_error(['message' => __('Invalid Cloud Provider!', 'advanced-media-offloader')]);
		}
	}

	private function count_non_offloaded_media()
	{
		// we should count attachments that doesn't have  advmo_offloaded meta key or its value is not 1
		$args = [
			'fields' => 'ids',
			'numberposts' => -1,
			'post_type' => 'attachment',
			'post_status' => 'any',
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => 'advmo_offloaded',
					'compare' => 'NOT EXISTS'
				]
			]
		];
		$attachments = get_posts($args);
		return count($attachments);
	}
}
