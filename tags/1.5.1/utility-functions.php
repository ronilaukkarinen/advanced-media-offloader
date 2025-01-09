<?php
if (!defined('ABSPATH')) {
	die('No direct script access allowed');
}

if (!function_exists('advmo_include')) {
	function advmo_include($file)
	{
		if (file_exists(ADVMO_PATH . $file)) {
			include_once ADVMO_PATH . $file;
		}
	}
}

if (!function_exists('advmo_get_option')) {
	function advmo_get_option($option, $default = '')
	{
		$options = get_option('advmo_options');
		if (is_array($options) && isset($options[$option])) {
			return $options[$option];
		}
		return $default;
	}
}

if (!function_exists('advmo_update_option')) {
	function advmo_update_option($option, $value)
	{
		$options = get_option('advmo_options');
		if (!is_array($options)) {
			$options = [];
		}
		$options[$option] = $value;
		update_option('advmo_options', $options);
	}
}

if (!function_exists('advmo_delete_option')) {
	function advmo_delete_option($option)
	{
		$options = get_option('advmo_options');
		if (is_array($options) && isset($options[$option])) {
			unset($options[$option]);
		}
		update_option('advmo_options', $options);
	}
}

if (!function_exists('advmo_vd')) {
	function advmo_vd($var, $die = false)
	{
		echo '<pre style="direction: ltr">';
		var_dump($var);
		echo '</pre>';
		if ($die) {
			die();
		}
	}
}

/**
 * Generate copyright text
 *
 * @return string
 */
/**
 * Generate copyright and support text
 *
 * @return string
 */
if (!function_exists('advmo_get_copyright_text')) {
	function advmo_get_copyright_text()
	{
		$year = date('Y');
		$site_url = 'https://wpfitter.com/?utm_source=wp-plugin&utm_medium=plugin&utm_campaign=advanced-media-offloader';
		$support_url = 'https://wpfitter.com/contact/?utm_source=wp-plugin&utm_medium=plugin&utm_campaign=advanced-media-offloader';

		return sprintf(
			'Advanced Media Offloader plugin developed by <a href="%s" target="_blank">WPFitter</a>. ' .
				'For support, please <a href="%s" target="_blank">contact us</a>.',
			esc_url($site_url),
			esc_url($support_url)
		);
	}
}

if (!function_exists('advmo_get_bulk_offload_data')) {
	function advmo_get_bulk_offload_data()
	{
		$defaults = array(
			'total' => 0,
			'status' => '',
			'processed' => 0
		);

		$stored_data = get_option('advmo_bulk_offload_data', array());

		return array_merge($defaults, $stored_data);
	}
}

if (!function_exists('advmo_update_bulk_offload_data')) {
	function advmo_update_bulk_offload_data($new_data)
	{
		// Define the allowed keys
		$allowed_keys = array('total', 'status', 'processed');

		// Filter the new data to only include allowed keys
		$filtered_new_data = array_intersect_key($new_data, array_flip($allowed_keys));

		// Get the existing data
		$existing_data = advmo_get_bulk_offload_data();

		// Merge the filtered new data with the existing data
		$updated_data = array_merge($existing_data, $filtered_new_data);

		// Ensure only allowed keys are in the final data set
		$final_data = array_intersect_key($updated_data, array_flip($allowed_keys));

		// Update the option in the database
		update_option('advmo_bulk_offload_data', $final_data);

		return $final_data;
	}
}

if (!function_exists('advmo_is_media_organized_by_year_month')) {
	function advmo_is_media_organized_by_year_month()
	{
		return get_option('uploads_use_yearmonth_folders') ? true : false;
	}
}

/**
 * Sanitize a URL path.
 *
 * @param string $path The path to sanitize.
 * @return string The sanitized path.
 */
if (!function_exists('advmo_sanitize_path')) {
	function advmo_sanitize_path(string $path): string
	{
		// Remove leading and trailing whitespace
		$path = trim($path);

		// Remove or encode potentially harmful characters
		$path = wp_sanitize_redirect($path);

		// Convert to lowercase for consistency (optional, depending on your needs)
		$path = strtolower($path);

		// Remove any directory traversal attempts
		$path = str_replace(['../', './'], '', $path);

		// Normalize slashes and remove duplicate slashes
		$path = preg_replace('#/+#', '/', $path);

		// Remove leading and trailing slashes
		$path = trim($path, '/');

		// Optionally, you can use wp_normalize_path() if you want to ensure consistent directory separators
		// $path = wp_normalize_path($path);

		return $path;
	}
}

if (!function_exists('advmo_clear_bulk_offload_data')) {
	function advmo_clear_bulk_offload_data()
	{
		delete_option('advmo_bulk_offload_data');
	}
}
