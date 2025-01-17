# Advanced Media Offloader

This is an unofficial fork of the [Advanced Media Offloader](https://wordpress.org/plugins/advanced-media-offloader/) plugin.

> [!WARNING]  
> This plugin is NOT maintained by the original author and is not recommended for production use. Use with caution and at your own risk. Recommended to test with smaller sites before using on larger sites. WP CLI plugin works, but is work in progress.
> **It is CRUCIAL that you backup your media library, database and codebase before using this plugin.**

## Description

Offload WordPress media to Amazon S3, DigitalOcean Spaces, Min.io or Cloudflare R2.

## Features

- Automatic upload of new media attachments to S3-compatible cloud storage.
- Rewrites URLs for media files to serve them directly from cloud storage.
- Bulk offload media to cloud storage (50 files per batch in the free version).
- Provides hooks for further extension.

## WP CLI Support

```bash
wp advmo offload [--batch <number>] [--dry-run] [--verbose] [--all] [--yes] 
```

- `wp advmo offload --dry-run` - Preview what files would be offloaded without actually offloading them.
- `wp advmo offload --batch-size=100` - Offload 100 files per batch.
- `wp advmo offload --all` - Offload all media to cloud storage.
- `wp advmo upload 123 [--dry-run]` - Offload a single media file to cloud storage.
- `wp advmo fix_404s [--dry-run] [--verbose]` - (A debug command) Fix media items that are 404ing by checking if they exist in cloud storage

## Installation

1. In your WordPress admin panel, go to Plugins > Add New.
2. Search for "Advanced Media Offloader" and click "Install Now".
3. Once installed, click "Activate" to enable the plugin.
4. After activation, go to "Media Offloader" in the WordPress dashboard to access the plugin settings.
5. To configure the plugin, you need to add your S3-compatible cloud storage credentials to your wp-config.php file using constants.

## Contributing

1. Clone the repository.
2. Run

```bash
# First clean up
rm -rf vendor composer.lock build

# Install dependencies
composer install
```
