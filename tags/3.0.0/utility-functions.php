<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Include a view file from the plugin directory.
 *
 * @param string $file The file path relative to the plugin directory.
 * @return void
 */
if ( ! function_exists( 'advmo_get_view' ) ) {
	function advmo_get_view( string $template ) {
		if ( file_exists( ADVMO_PATH . 'templates/' . $template . '.php' ) ) {
			include ADVMO_PATH . 'templates/' . $template . '.php';
		}
	}
}

/**
 * Get a plugin option value.
 *
 * @param string $option The option name.
 * @param mixed $default The default value if the option is not set.
 * @return mixed The option value or default.
 */
if ( ! function_exists( 'advmo_get_option' ) ) {
	function advmo_get_option( string $option, $default = '' ) {
		$options = get_option( 'advmo_options' );
		if ( is_array( $options ) && isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}
		return $default;
	}
}

/**
 * Update a plugin option value.
 *
 * @param string $option The option name.
 * @param mixed $value The new value for the option.
 * @return void
 */
if ( ! function_exists( 'advmo_update_option' ) ) {
	function advmo_update_option( string $option, $value ): void {
		$options = get_option( 'advmo_options' );
		if ( ! is_array( $options ) ) {
			$options = [];
		}
		$options[ $option ] = $value;
		update_option( 'advmo_options', $options );
	}
}

/**
 * Delete a plugin option.
 *
 * @param string $option The option name to delete.
 * @return void
 */

if ( ! function_exists( 'advmo_delete_opion' ) ) {
	function advmo_delete_option( string $option ): void {
		$options = get_option( 'advmo_options' );
		if ( is_array( $options ) && isset( $options[ $option ] ) ) {
			unset( $options[ $option ] );
		}
		update_option( 'advmo_options', $options );
	}
}

/**
 * Debug function to var_dump a variable.
 *
 * @param mixed $var The variable to dump.
 * @param bool $die Whether to die after dumping.
 * @return void
 */
if ( ! function_exists( 'advmo_vd' ) ) {
	function advmo_vd( $var, bool $die = false ): void {
		echo '<pre style="direction: ltr">';
		var_dump( $var );
		echo '</pre>';
		if ( $die ) {
			die();
		}
	}
}


if ( ! function_exists( 'advmo_is_settings_page' ) ) {
	function advmo_is_settings_page( $page_name = '' ): bool {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return false;
		}

		$ADVMO_GENERAL_PAGE = 'toplevel_page_advmo';
		$ADVMO_MEDIA_OVERVIEW_PAGE = 'media-offloader_page_advmo_media_overview';

		$plugin_pages = [
			$ADVMO_GENERAL_PAGE,    // Main settings page
			$ADVMO_MEDIA_OVERVIEW_PAGE,  // Submenu page
		];

		if ( ! empty( $page_name ) ) {
			switch ( $page_name ) {
				case 'general':
					return $current_screen->id === $ADVMO_GENERAL_PAGE;
				case 'media-overview':
					return $current_screen->id === $ADVMO_MEDIA_OVERVIEW_PAGE;
				default:
					return false; // Invalid page name provided
			}
		}

		return in_array( $current_screen->id, $plugin_pages );
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
if ( ! function_exists( 'advmo_get_copyright_text' ) ) {
	function advmo_get_copyright_text(): string {
		$year = date( 'Y' );
		$site_url = 'https://wpfitter.com/?utm_source=wp-plugin&utm_medium=plugin&utm_campaign=advanced-media-offloader';

		return sprintf(
			'Advanced Media Offloader plugin developed by <a href="%s" target="_blank">WPFitter</a>. ',
			esc_url( $site_url )
		);
	}
}

/**
 * Get bulk offload data.
 *
 * @return array The bulk offload data.
 */
if ( ! function_exists( 'advmo_get_bulk_offload_data' ) ) {
	function advmo_get_bulk_offload_data(): array {
		$defaults = array(
			'total' => 0,
			'status' => '',
			'processed' => 0,
			'errors' => 0,
		);

		$stored_data = get_option( 'advmo_bulk_offload_data', array() );

		return array_merge( $defaults, $stored_data );
	}
}

/**
 * Update bulk offload data.
 *
 * @param array $new_data The new data to update.
 * @return array The updated bulk offload data.
 */
if ( ! function_exists( 'advmo_update_bulk_offload_data' ) ) {
	function advmo_update_bulk_offload_data( array $new_data ): array {
		// Define the allowed keys
		$allowed_keys = array( 'total', 'status', 'processed', 'errors' );

		// Filter the new data to only include allowed keys
		$filtered_new_data = array_intersect_key( $new_data, array_flip( $allowed_keys ) );

		// Get the existing data
		$existing_data = advmo_get_bulk_offload_data();

		// Merge the filtered new data with the existing data
		$updated_data = array_merge( $existing_data, $filtered_new_data );

		// Ensure only allowed keys are in the final data set
		$final_data = array_intersect_key( $updated_data, array_flip( $allowed_keys ) );

		// Update the option in the database
		update_option( 'advmo_bulk_offload_data', $final_data );

		return $final_data;
	}
}

/**
 * Check if media is organized by year and month.
 *
 * @return bool True if media is organized by year and month, false otherwise.
 */
if ( ! function_exists( 'advmo_is_media_organized_by_year_month' ) ) {
	function advmo_is_media_organized_by_year_month(): bool {
		return get_option( 'uploads_use_yearmonth_folders' ) ? true : false;
	}
}

/**
 * Sanitize a URL path.
 *
 * @param string $path The path to sanitize.
 * @return string The sanitized path.
 */
if ( ! function_exists( 'advmo_sanitize_path' ) ) {
	function advmo_sanitize_path( string $path ): string {
		// Remove leading and trailing whitespace
		$path = trim( $path );

		// Remove or encode potentially harmful characters
		$path = wp_sanitize_redirect( $path );

		// Convert to lowercase for consistency (optional, depending on your needs)
		$path = strtolower( $path );

		// Remove any directory traversal attempts
		$path = str_replace( [ '../', './' ], '', $path );

		// Normalize slashes and remove duplicate slashes
		$path = preg_replace( '#/+#', '/', $path );

		// Remove leading and trailing slashes
		$path = trim( $path, '/' );

		// Optionally, you can use wp_normalize_path() if you want to ensure consistent directory separators
		// $path = wp_normalize_path($path);

		return $path;
	}
}

/**
 * Clear bulk offload data.
 *
 * @return void
 */
if ( ! function_exists( 'advmo_clear_bulk_offload_data' ) ) {
	function advmo_clear_bulk_offload_data(): void {
		delete_option( 'advmo_bulk_offload_data' );
	}
}

/**
 * Get the cloud provider key.
 *
 * @return string The cloud provider key.
 */
if ( ! function_exists( 'advmo_get_cloud_provider_key' ) ) {
	function advmo_get_cloud_provider_key(): string {
		$options = get_option( 'advmo_settings', [] );
		return $options['cloud_provider'] ?? '';
	}
}

/**
 * Get the count of unoffloaded media items.
 *
 * @return int The count of unoffloaded media items.
 */
if ( ! function_exists( 'advmo_get_unoffloaded_media_items_count' ) ) {
	function advmo_get_unoffloaded_media_items_count(): int {
		$args = [
			'fields' => 'ids',
			'numberposts' => -1,
			'post_type' => 'attachment',
			'post_status' => 'any',
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
		];
		$attachments = get_posts( $args );
		return count( $attachments );
	}
}

if ( ! function_exists( 'advmo_get_offloaded_media_items_count' ) ) {
	function advmo_get_offloaded_media_items_count() {
		$args = [
			'fields' => 'ids',
			'numberposts' => -1,
			'post_type' => 'attachment',
			'post_status' => 'any',
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => 'advmo_offloaded',
					'compare' => 'EXISTS',
				],
				[
					'key' => 'advmo_offloaded',
					'compare' => '!=',
					'value' => '',
				],
			],
		];
		$attachments = get_posts( $args );
		return count( $attachments );
	}
}
