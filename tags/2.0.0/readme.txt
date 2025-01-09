=== Advanced Media Offloader ===
Contributors: masoudin, bahreynipour, wpfitter
Tags: media, offload, s3, digitalocean, cloudflare
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offload WordPress media to Amazon S3, DigitalOcean Spaces, Min.io or Cloudflare R2.

== Description ==

Advanced Media Offloader automatically uploads media attachments from your WordPress site to an S3-compatible cloud storage, replacing the URLs appropriately to serve the media from the cloud. This can help reduce server load, increase page speed, and optimize media delivery.

== Features ==

- Automatic upload of new media attachments to S3-compatible cloud storage.
- Rewrites URLs for media files to serve them directly from cloud storage.
- Bulk offload media to cloud storage (50 files per batch in the free version).
- Provides hooks for further extension.

== Installation ==

1. In your WordPress admin panel, go to Plugins > Add New.
2. Search for "Advanced Media Offloader" and click "Install Now".
3. Once installed, click "Activate" to enable the plugin.
4. After activation, go to "Media Offloader" in the WordPress dashboard to access the plugin settings.
5. To configure the plugin, you need to add your S3-compatible cloud storage credentials to your wp-config.php file using constants. Here are examples for different providers:

- For [Cloudflare R2](https://developers.cloudflare.com/r2/), add the following to wp-config.php:
`
	define('ADVMO_CLOUDFLARE_R2_KEY', 'your-access-key');
	define('ADVMO_CLOUDFLARE_R2_SECRET', 'your-secret-key');
	define('ADVMO_CLOUDFLARE_R2_BUCKET', 'your-bucket-name');
    define('ADVMO_CLOUDFLARE_R2_DOMAIN', 'your-domain-url');
    define('ADVMO_CLOUDFLARE_R2_ENDPOINT', 'your-endpoint-url');
`

- For [DigitalOcean Spaces](https://www.digitalocean.com/products/spaces), add the following to wp-config.php:
`
	define('ADVMO_DOS_KEY', 'your-access-key');
	define('ADVMO_DOS_SECRET', 'your-secret-key');
	define('ADVMO_DOS_BUCKET', 'your-bucket-name');
    define('ADVMO_DOS_DOMAIN', 'your-domain-url');
    define('ADVMO_DOS_ENDPOINT', 'your-endpoint-url');
`

- For [MinIO](https://min.io/docs/minio/linux/administration/identity-access-management/minio-user-management.html), add the following to wp-config.php:
`
	define('ADVMO_MINIO_KEY', 'your-access-key');
	define('ADVMO_MINIO_SECRET', 'your-secret-key');
	define('ADVMO_MINIO_BUCKET', 'your-bucket-name');
    define('ADVMO_MINIO_DOMAIN', 'your-domain-url');
    define('ADVMO_MINIO_ENDPOINT', 'your-endpoint-url');
`

- For [Amazon S3](https://aws.amazon.com/s3/), add the following to wp-config.php:
`
	define('ADVMO_AWS_KEY', 'your-access-key');
	define('ADVMO_AWS_SECRET', 'your-secret-key');
	define('ADVMO_AWS_BUCKET', 'your-bucket-name');
    define('ADVMO_AWS_REGION', 'your-bukcet-region');
    define('ADVMO_AWS_DOMAIN', 'your-domain-url');
`

== Frequently Asked Questions ==

= Does this plugin support other cloud storage platforms? =

Currently, the plugin supports only Cloudflare R2, DigitalOcean Spaces, Amazon S3 & MinIO, but we are working on adding support for other cloud storage platforms.

= What happens to the media files already uploaded on my server? =

They will remain on your server and be served from there. You can choose to bulk offload them to cloud storage if needed.

= How does the plugin handle loading images from cloud storage? =

The plugin automatically detects and rewrites URLs for uploaded files, enabling them to be served directly from cloud storage.

= What happens to my files after offloading them to cloud storage? =

After offloading to the cloud, files remain on your server by default. However, in the plugin settings, you can enable “Smart Local Cleanup” to retain only the original file after offloading, or select “Full Cloud Migration” to remove all local files.

= What happens if I remove a media file from the WordPress Media Library? =

If the “Mirror Delete” option is enabled in the plugin settings, the corresponding file will also be removed from cloud storage. Otherwise, the file will remain in cloud storage even after being deleted from the WordPress Media Library.

== Changelog ==
= 2.0.0 =
- Refactored the Advanced Media Offloader codebase.
- Added new action hooks for custom actions before and after critical operations.
- Fixed compatibility issue with the Performance Lab WordPress plugin.
- Fixed a bug in bulk offloading media files.
- Added support for MinIO path-style endpoint configuration using the ADVMO_MINIO_PATH_STYLE_ENDPOINT constant.
- Fixed minor bugs and made improvements.

= 1.6.0 =
- Refactored the code base to improve maintainability and readability, resulting in enhanced performance across the plugin.
- Resolved an issue where the bulk offload process would become unresponsive
- Added a button to cancel the bulk offload process, providing users with greater control during file transfers.

= 1.5.2 =
- Fix a minor bug related to the path of existing media files when deleting local files.

= 1.5.1 =
- Fix minor bugs to improve bulk offload process

= 1.5.0 =
- Added support for Amazon S3 cloud storage
- Enhanced plugin performance and stability
- Fix minor bugs

= 1.4.5 =
- Fix minor bugs with Min.io

= 1.4.4 =
- New Feature: Custom Path Prefix for Cloud Storage
- Fix minor bugs

= 1.4.3 = 
- Add Version to Bucket Path: Automatically add unique timestamps to your media file paths to ensure the latest versions are always delivered
- Add Mirror Delete: Automatically delete local files after successful upload to Cloud Storage.
- Improve Settings UI: Enhanced the user interface of the settings page.

= 1.4.2 =
- Added 'Sync Local and Cloud Deletions' feature to automatically remove media from cloud storage when deleted locally.
- Enhanced WooCommerce compatibility: Added support for WooCommerce-specific image sizes and optimized handling of product images.

= 1.4.1 =
- Fix minor bugs related to Bulk offloading the existing media files

= 1.4.0 =
- Added bulk offload feature for media files (50 per batch in free version)
- Fixed subdir path issue for non-image files
- UI Improvements
- Fixed minor bugs

= 1.3.0 =
- UI Improvements
- Fixed minor bugs

= 1.2.0 =
- Added MinIO as a new cloud storage provider
- Introduced an option to choose if local files should be deleted after offloading to cloud storage
- Implemented UI improvements for the plugin settings page
- Added Offload status to Attachment details section in Media Library
- Fixed minor bugs

= 1.1.0 =
- Improved the code base to fix some issues
- Added support for DigitalOcean Spaces

= 1.0.0 =
- Initial release.

== Upgrade Notice ==
= 1.5.1 =
This update improves the Bulk offload process

= 1.5.0 =
This update introduces support for Amazon S3, a new cloud storage provider. Please update to access these new features and bug fixes.

= 1.4.0 =
This update introduces a bulk offload feature, fixes the subdir path for non-image files, and includes UI improvements. Please update to access these new features and bug fixes.

= 1.3.0 =
This update introduces UI improvements and bug fixes. Please update to access these new features and bug fixes.

= 1.2.0 =
This update introduces MinIO support, local file deletion options, UI improvements, and offload status in Media Library. Please update to access these new features and bug fixes.

= 1.1.0 =
This update improves the code base and adds support for DigitalOcean Spaces. Update recommended for all users.

= 1.0.0 =
Initial release. Please provide feedback and report any issues through the support forum.

== Using the S3 PHP SDK ==

The Advanced Media Offloader utilizes the AWS SDK for PHP to interact with S3-compatible cloud storage. This powerful SDK provides an easy-to-use API for managing your cloud storage operations, including file uploads, downloads, and more. The SDK is maintained by Amazon Web Services, ensuring high compatibility and performance with S3 services.

For more information about the AWS SDK for PHP, visit:
[https://aws.amazon.com/sdk-for-php/](https://aws.amazon.com/sdk-for-php/)

== Screenshots ==

1. Plugin settings page - Configure your cloud storage settings and offload options.
2. Attachment details page - View the offload status of individual media files.