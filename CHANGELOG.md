### 3.1.1: 2025-01-09

* Merge changes from revision 3219756 of advanced-media-offloader, svn trunk version 3.0.0 (prefixed with Upstream)
* Format changes with PHPCBF
* Upstream: Introduce a new user interface (UI) and improved user experience (UX) for the settings page.
* Upstream: Add functionality to offload and sync edited images with cloud storage
* Upstream: Improve bulk offloading to cloud storage by fixing various bugs
* Upstream: Implement error logging for bulk offload operations
* Upstream: Add ability to download a CSV file with detailed logs for attachments that encountered errors during offloading
* Upstream: Enhance overall security of the plugin
* Upstream: Fix various issues related to bulk offload JavaScript functionality.
* Upstream: Improve error handling and notifications for media attachments in the library
* Upstream: Refactor attachment deletion methods for better performance and reliability

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