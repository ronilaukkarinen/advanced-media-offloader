<?php
/*
 * Plugin Name:       Advanced Media Offloader
 * Plugin URI:        https://wpfitter.com/plugins/advanced-media-offloader/
 * Description:       Offload WordPress media to Amazon S3, DigitalOcean Spaces, Min.io or Cloudflare R2.
 * Version:           1.5.1
 * Requires at least: 5.6
 * Requires PHP:      8.1
 * Author:            WP Fitter
 * Author URI:        https://wpfitter.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-media-offloader
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (!class_exists('ADVMO')) {
	/**
	 * The main ADVMO class
	 */
	class ADVMO
	{

		/**
		 * The plugin version number.
		 *
		 * @var string
		 */
		public $version;

		/**
		 * The plugin settings array.
		 *
		 * @var array
		 */
		public $settings = array();

		/**
		 * The offloader instance.
		 *
		 * @var Advanced_Media_Offloader\Offloader
		 */
		public $offloader;

		/**
		 * The plugin data array.
		 *
		 * @var array
		 */
		public $data = array();

		protected $cloud_providers = [
			'cloudflare_r2' => [
				'name' => 'Cloudflare R2',
				'class' => 'Advanced_Media_Offloader\Integrations\Cloudflare_R2',
			],
			'dos' => [
				'name' => 'DigitalOcean Spaces',
				'class' => 'Advanced_Media_Offloader\Integrations\DigitalOceanSpaces',
			],
			'minio' => [
				'name' => 'MinIO',
				'class' => 'Advanced_Media_Offloader\Integrations\MinIO',
			],
			'aws_s3' => [
				'name' => 'Amazon S3',
				'class' => 'Advanced_Media_Offloader\Integrations\AmazonS3',
			]
		];

		/**
		 * A dummy constructor to ensure WP Fitter Media Offloader is only setup once.
		 *
		 * @since   1.0.0
		 *
		 * @return  void
		 */
		public function __construct()
		{
			$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'));
			$this->version = $plugin_data['Version'];
		}

		/**
		 * Sets up the Advanced Media Offloader
		 *
		 * @since   1.0.0
		 *
		 * @return  void
		 */
		public function initialize()
		{

			// Define constants.
			$this->define('ADVMO', true);
			$this->define('ADVMO_PATH', plugin_dir_path(__FILE__));
			$this->define('ADVMO_URL', plugin_dir_url(__FILE__));
			$this->define('ADVMO_BASENAME', plugin_basename(__FILE__));
			$this->define('ADVMO_VERSION', $this->version);
			$this->define('ADVMO_API_VERSION', 1);

			// Register activation hook.
			register_activation_hook(__FILE__, array($this, 'plugin_activated'));

			// Define settings.
			$this->settings = array(
				'name'                    => __('Advanced Media Offloader', 'advanced-media-offloader'),
				'description'             => __('Offload WordPress media to Amazon S3, DigitalOcean Spaces, Min.io or Cloudflare R2.', 'advanced-media-offloader'),
				'slug'                    => dirname(ADVMO_BASENAME),
				'version'                 => ADVMO_VERSION,
				'basename'                => ADVMO_BASENAME,
				'path'                    => ADVMO_PATH,
				'file'                    => __FILE__,
				'url'                     => plugin_dir_url(__FILE__),
				'show_admin'              => true,
				'show_updates'            => true,
				'enable_post_types'       => true,
				'enable_options_pages_ui' => true,
				'stripslashes'            => false,
				'local'                   => true,
				'json'                    => true,
				'save_json'               => '',
				'load_json'               => array(),
				'default_language'        => '',
				'current_language'        => '',
				'capability'              => 'manage_options',
				'uploader'                => 'wp',
				'autoload'                => false,
				'offloader'               => '',
			);

			if (get_option('advmo_options') === false) {
				update_option('advmo_options', $this->settings);
			}

			# Include Composer autoload.
			if (file_exists(ADVMO_PATH . 'vendor/autoload.php')) {
				include_once ADVMO_PATH . 'vendor/autoload.php';
			}

			# include Utility Functions
			include_once ADVMO_PATH . 'utility-functions.php';

			# check if AWS SDK is loaded
			if (!class_exists(\Aws\S3\S3Client::class)) {
				// Show admin notice if AWS SDK is missing.
				add_action('admin_notices', function () {
					$this->notice(__('AWS SDK for PHP is required to use Advanced Media Offloader. Please install it via Composer.', 'advanced-media-offloader'), 'error');
				});
				return;
			}

			# Include admin.
			if (is_admin()) {
				new Advanced_Media_Offloader\Admin\GeneralSettings($this->cloud_providers);
			}

			$this->init();
		}

		/**
		 * Completes the setup process on "init" of earlier.
		 *
		 * @since   1.0.0
		 *
		 * @return  void
		 */
		public function init()
		{
			// Load textdomain file.
			load_plugin_textdomain('advanced-media-offloader', false, dirname(plugin_basename(__FILE__)) . '/languages/');

			// Get selected cloud provider in plugin settings page.
			$plugin_settings = get_option('advmo_settings');
			$cloud_provider = $plugin_settings['cloud_provider'] ?? null;

			if ($cloud_provider && isset($this->cloud_providers[$cloud_provider])) {
				$offloader_class = $this->cloud_providers[$cloud_provider]['class'];

				// Initiate the class if it exists.
				if (class_exists($offloader_class)) {
					global $advmo;
					$cloud_provider = new $offloader_class();
					$this->offloader = Advanced_Media_Offloader\Offloader::get_instance($cloud_provider);
					$this->offloader->initializeHooks();
				}
			}
		}

		/**
		 * Plugin Activation Hook
		 *
		 * @since 1.0.0
		 */
		public function plugin_activated()
		{
			// Set the first activated version of Advanced Media Offloader.
			if (null === get_option('advmo_first_activated_version', null)) {
				update_option('advmo_first_activated_version', ADVMO_VERSION, true);
			}
		}

		public function define($name, $value = true)
		{
			if (!defined($name)) {
				define($name, $value);
			}
		}

		public function notice($message, $type = 'info')
		{
			$class = 'notice notice-' . $type;
			printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_attr($message));
		}
	}

	function advmo()
	{
		global $advmo;

		// Instantiate only once.
		if (!isset($advmo)) {
			$advmo = new ADVMO();
			$advmo->initialize();
		}
		return $advmo;
	}

	// Instantiate.
	advmo();
} // class_exists check