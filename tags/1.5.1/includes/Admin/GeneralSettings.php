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
		$this->add_retention_policy_field();
		$this->add_path_prefix_field();
		$this->add_object_versioning_field();
		$this->add_non_offloaded_media_field();
		$this->add_mirror_delete_field();
	}

	private function add_settings_section()
	{
		add_settings_section(
			'cloud_provider',
			__('Cloud Provider', 'advanced-media-offloader'),
			function () {
				echo '<p>' . esc_attr__('Select a cloud storage provider and provide the necessary credentials.', 'advanced-media-offloader') . '</p>';
			},
			'advmo'
		);

		add_settings_section(
			'general_settings',
			__('General Settings', 'advanced-media-offloader'),
			function () {
				echo '<p>' . esc_attr__('Configure the core options for managing and offloading your media files to cloud storage.', 'advanced-media-offloader') . '</p>';
			},
			'advmo'
		);

		add_settings_section(
			'media_overview',
			__('Media Library Overview', 'advanced-media-offloader'),
			function () {
				echo '<p>' . esc_attr__('Get a comprehensive overview of all media files in your WordPress library. Easily identify any files that haven’t been offloaded to the Cloud and offload them in bulk.', 'advanced-media-offloader') . '</p>';
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
			'cloud_provider'
		);
	}

	private function add_credentials_field()
	{
		add_settings_field(
			'advmo_cloud_provider_credentials',
			__('Credentials', 'advanced-media-offloader'),
			[$this, 'cloud_provider_credentials_field'],
			'advmo',
			'cloud_provider'
		);
	}
	private function add_retention_policy_field()
	{
		add_settings_field(
			'retention_policy',
			__('Retention Policy', 'advanced-media-offloader'),
			[$this, 'retention_policy_field'],
			'advmo',
			'general_settings'
		);
	}
	private function add_mirror_delete_field()
	{
		add_settings_field(
			'mirror_delete',
			__('Mirror Delete', 'advanced-media-offloader'),
			[$this, 'mirror_delete_field'],
			'advmo',
			'general_settings'
		);
	}

	private function add_object_versioning_field()
	{
		add_settings_field('object_versioning', __('File Versioning', 'advanced-media-offloader'), [$this, 'object_versioning_field'], 'advmo', 'general_settings');
	}

	private function add_path_prefix_field()
	{
		add_settings_field('path_prefix', __('Custom Path Prefix', 'advanced-media-offloader'), [$this, 'path_prefix_field'], 'advmo', 'general_settings');
	}

	private function add_non_offloaded_media_field()
	{
		add_settings_field(
			'non_offloaded_media',
			__('Local Media Overview', 'advanced-media-offloader'),
			[$this, 'non_offloaded_media_field'],
			'advmo',
			'media_overview'
		);
	}

	public function path_prefix_field()
	{
		$options = get_option('advmo_settings');
		$path_prefix = isset($options['path_prefix']) ? $options['path_prefix'] : "wp-content/uploads/";
		$path_prefix_Active = isset($options['path_prefix_active']) ? $options['path_prefix_active'] : 0;
		echo '<div class="advmo-checkbox-option">';
		echo '<input type="checkbox" id="path_prefix_active" name="advmo_settings[path_prefix_active]" value="1" ' . checked(1, $path_prefix_Active, false) . '/>';
		echo '<label for="path_prefix_active">' . esc_html__('Use Custom Path Prefix', 'advanced-media-offloader') . '</label>';
		echo '<p class="description">' . '<input type="input" id="path_prefix" name="advmo_settings[path_prefix]" value="' . esc_html($path_prefix) . '"' . ($path_prefix_Active ? '' : ' disabled') . '/>'  . '</p>';
		echo '<p class="description">' . esc_html__('Add a common prefix to organize offloaded media files from this site in your cloud storage bucket.', 'advanced-media-offloader') . '</p>';
		echo '</div>';
	}

	public function object_versioning_field()
	{
		$options = get_option('advmo_settings');
		$object_versioning = isset($options['object_versioning']) ? $options['object_versioning'] : 0;

		echo '<div class="advmo-checkbox-option">';
		echo '<input type="checkbox" id="object_versioning" name="advmo_settings[object_versioning]" value="1" ' . checked(1, $object_versioning, false) . '/>';
		echo '<label for="object_versioning">' . esc_html__('Add Version to Bucket Path', 'advanced-media-offloader') . '</label>';
		echo '<p class="description">' . esc_html__('Automatically add unique timestamps to your media file paths to ensure the latest versions are always delivered. This prevents outdated content from being served due to CDN caching, even when you replace files with the same name. Eliminate manual cache invalidation and guarantee your visitors always see the most up-to-date media.', 'advanced-media-offloader') . '</p>';
		echo '</div>';
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
						'You have %d file still stored on your server.',
						'You have %d files still stored on your server.',
						$count,
						'advanced-media-offloader'
					),
					$count
				) . '</p>';
				echo '<p class="description">' . __('Offload them to cloud storage now to free up space and enhance your website\'s performance.', 'advanced-media-offloader') . '</p>';
				echo '<button type="button" id="bulk-offload-button" class="button">' . __('Offload Now', 'advanced-media-offloader') . '</button>';
				// Add information about batch size limitation
				echo '<p class="description"><strong>' . __('Note:', 'advanced-media-offloader') . '</strong> ';
				echo __('The offloading process handles up to 50 media files per batch. If you have more than 50 files, you’ll need to run the bulk offload multiple times. This process runs in the background—you can close this page after starting.', 'advanced-media-offloader') . '</p>';
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
	public function mirror_delete_field()
	{
		$options = get_option('advmo_settings');
		$mirror_delete = isset($options['mirror_delete']) ? intval($options['mirror_delete']) : 0;
		echo '<div class="advmo-checkbox-option">';
		echo '<input type="checkbox" id="mirror_delete" name="advmo_settings[mirror_delete]" value="1" ' . checked(1, $mirror_delete, false) . '/>';
		echo '<label for="mirror_delete">' . esc_html__('Sync Deletion with Cloud Storage', 'advanced-media-offloader') . '</label>';
		echo '<p class="description">' . esc_html__('When enabled, deleting a media file in WordPress will also remove it from your cloud storage.', 'advanced-media-offloader') . '</p>';
		echo '</div>';
	}

	public function retention_policy_field()
	{
		$options = get_option('advmo_settings');
		$retention_policy = isset($options['retention_policy']) ? intval($options['retention_policy']) : 0;

		echo '<div class="advmo-radio-group">';

		echo '<div class="advmo-radio-option">';
		echo '<input type="radio" id="retention_policy" name="advmo_settings[retention_policy]" value="0" ' . checked(0, $retention_policy, false) . '/>';
		echo '<label for="retention_policy_none">' . esc_html__('Retain Local Files', 'advanced-media-offloader') . '</label>';
		echo '<p class="description">' . esc_html__('Keep all files on your local server after offloading to the cloud. This option provides redundancy but uses more local storage.', 'advanced-media-offloader') . '</p>';
		echo '</div>';

		echo '<div class="advmo-radio-option">';
		echo '<input type="radio" id="retention_policy_cloud" name="advmo_settings[retention_policy]" value="1" ' . checked(1, $retention_policy, false) . '/>';
		echo '<label for="retention_policy_cloud">' . esc_html__('Smart Local Cleanup', 'advanced-media-offloader') . '</label>';
		echo '<p class="description">' . esc_html__('Remove local copies after cloud offloading, but keep the original file as a backup. Balances storage efficiency with data safety.', 'advanced-media-offloader') . '</p>';
		echo '</div>';

		echo '<div class="advmo-radio-option">';
		echo '<input type="radio" id="retention_policy_all" name="advmo_settings[retention_policy]" value="2" ' . checked(2, $retention_policy, false) . '/>';
		echo '<label for="retention_policy_all">' . esc_html__('Full Cloud Migration', 'advanced-media-offloader') . '</label>';
		echo '<p class="description">' . esc_html__('Remove all local files, including originals, after successful cloud offloading. Maximizes local storage savings but relies entirely on cloud storage.', 'advanced-media-offloader') . '</p>';
		echo '</div>';

		echo '</div>';
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

		$options['retention_policy'] = isset($options['retention_policy']) && in_array($options['retention_policy'], [0, 1, 2]) ? $options['retention_policy'] : 0;
		$options['object_versioning'] = isset($options['object_versioning']) ? 1 : 0;
		$options['path_prefix'] = isset($options['path_prefix']) ? advmo_sanitize_path($options['path_prefix']) : '';

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
