=== Advanced Media Offloader ===
Contributors: masoudin, bahreynipour, wpfitter
Tags: media, offload, s3, digitalocean, cloudflare
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.5.1
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

== Frequently Asked Questions ==

= Does this plugin support other cloud storage platforms? =

Currently, the plugin supports only Cloudflare R2 DigitalOcean Spaces & MinIO, but we are working on adding support for other cloud storage platforms such as Amazon S3 and more.

= What happens to the media files already uploaded on my server? =

They will remain on your server, and they will be served from your server.

= How does the plugin handle media files that are already uploaded to my cloud storage? =

The plugin will automatically detect and rewrite URLs for these files, so they can be served from the cloud storage.

= What happens to media files uploading to my cloud storage? =

By default, uploaded files remain on your server after offloading. However, you can now choose to delete local files after successful upload to the Cloud Storage via the settings page. Note that even if you opt for deletion, the original file is always kept on the server for backup. Only custom-sized versions are removed locally when this option is enabled.

== Changelog ==
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