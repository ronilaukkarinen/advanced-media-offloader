### 3.1.4: 2025-02-11

* Fix corner case with srcset URLs, when using `the_post_thumbnail()` in the theme, Fixes T-25302
* Fix deletion in some cases if v. 2.0.3-dev has been used before (the deletion process has evolved across versions. In the latest version, the deletion is handled by the deleteAttachment method instead)

### 3.1.3: 2025-01-17

* Add `wp advmo fix_404s [--dry-run] [--verbose]` command to fix media items that are 404ing by checking if they exist in cloud storage

### 3.1.2: 2025-01-16

* Fix compatibility with new AWS SDK
* Feature: Upload a single media file to cloud storage
* General fixes and improvements
* Fix: add fallback support for cloud providers using getDomain() instead of getBaseUrl()
* Fix: use CloudAttachmentUploader directly in CLI commands instead of protected BulkMediaOffloader task
* Add debug logs for CloudAttachmentUploader
* Fix: Add handleLocalFileDeletion method to CloudAttachmentUploader for proper file cleanup
* Fix some images being shown with http instead of https causing mixed content errors
* Fix corner case double slash issue which breaks the media library image URLs

### 3.1.1: 2025-01-09

* Merge changes from revision 3219756 of advanced-media-offloader, svn trunk version 3.0.0 (prefixed with Upstream), Fixes #1
* Format changes with PHPCBF
* Exclude vendor directory from PHPCS checks
* Simplify composer.json and remove scoper, there is no need for it since WordPress's plugin architecture naturally isolates plugins, AWS SDK uses its own namespaces (Aws\) which are unlikely to conflict
* Add skip deletion option to bulk offloader
* Add checks for WordPress upload directory and cloud provider settings
* Make build work in both dev and production without having to manually run separate composer commands
* Upstream: Introduce a new user interface (UI) and improved user experience (UX) for the settings page.
* Upstream: Add functionality to offload and sync edited images with cloud storage
* Upstream: Improve bulk offloading to cloud storage by fixing various bugs
* Upstream: Implement error logging for bulk offload operations
* Upstream: Add ability to download a CSV file with detailed logs for attachments that encountered errors during offloading
* Upstream: Enhance overall security of the plugin
* Upstream: Fix various issues related to bulk offload JavaScript functionality.
* Upstream: Improve error handling and notifications for media attachments in the library
* Upstream: Refactor attachment deletion methods for better performance and reliability
* Re-initiate WP CLI after merging upstream changes

### 3.1.0: 2025-01-09

* Format all php files with PHPCBF
* Declare PHP 8.3 compatibility in PHPCBF
* Declare fork version information in main plugin to prevent auto-updating
* Go through WPCS sniffs, fix all PHPCS errors
* Fix date function, use gmdate instead
* Add missing param comments

### 2.0.3-dev:2024-12-12

* Add WP CLI support, `wp advmo offload`
* Add tested up WordPress version to 6.7.1
* Add support for uploading all media to cloud storage
* Add PHP_CodeSniffer packages and rules for development
* Declare PHP 8.3 compatibility in PHP_CodeSniffer
* WP CLI: Add `--verbose` flag to show debug information
* WP CLI: Add support for removal of local files, ask for confirmation
* WP CLI: Add `--yes` flag to skip confirmation
* Phpcs:xml: Add formatting, allow snake case