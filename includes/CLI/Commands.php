<?php

namespace Advanced_Media_Offloader\CLI;

use WP_CLI;
use Advanced_Media_Offloader\Factories\CloudProviderFactory;
use Advanced_Media_Offloader\Services\BulkMediaOffloader;

class Commands {
    public function __construct() {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('advmo', $this);
        }
    }

    /**
     * Bulk offload media files to cloud storage
     *
     * ## OPTIONS
     *
     * [--batch-size=<number>]
     * : Number of files to process per batch
     * ---
     * default: 50
     * ---
     *
     * [--all]
     * : Process all files instead of using batch size
     *
     * [--dry-run]
     * : Preview what files would be offloaded without actually offloading them
     *
     * ## EXAMPLES
     *
     *     wp advmo offload
     *     wp advmo offload --batch-size=100
     *     wp advmo offload --all
     *     wp advmo offload --dry-run
     *
     * @when after_wp_load
     */
    public function offload($args, $assoc_args) {
        $process_all = isset($assoc_args['all']);
        $batch_size = $process_all ? -1 : (int) $assoc_args['batch-size'];
        $dry_run = isset($assoc_args['dry-run']);

        try {
            $cloud_provider_key = advmo_get_cloud_provider_key();
            if (empty($cloud_provider_key)) {
                WP_CLI::error('No cloud provider configured. Please configure a provider in the plugin settings first.');
                return;
            }

            // Get provider name for display
            $provider_names = [
                's3' => 'Amazon S3',
                'do' => 'DigitalOcean Spaces',
                'minio' => 'MinIO',
                'r2' => 'Cloudflare R2'
            ];
            $provider_name = $provider_names[$cloud_provider_key] ?? $cloud_provider_key;

            $attachments = $this->get_unoffloaded_attachments($batch_size);
            $total = count($attachments);

            if ($total === 0) {
                WP_CLI::success('No files need to be offloaded.');
                return;
            }

            // Display summary
            WP_CLI::line(sprintf('You have %d %s still stored on your server.',
                $total,
                _n('file', 'files', $total)
            ));
            WP_CLI::line(sprintf('%d files to be uploaded to %s', $total, $provider_name));
            WP_CLI::line('');

            if ($dry_run) {
                WP_CLI::line(sprintf('Found %d files that would be offloaded:', $total));
                $upload_dir = wp_upload_dir();

                foreach ($attachments as $attachment_id) {
                    $file_path = get_attached_file($attachment_id);
                    $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);

                    if (file_exists($file_path)) {
                        $file_size = size_format(filesize($file_path));
                    } else {
                        $file_size = 'File not found';
                    }

                    WP_CLI::line(sprintf(
                        ' - [ID: %d] %s (%s)',
                        $attachment_id,
                        $relative_path,
                        $file_size
                    ));
                }
                WP_CLI::success('Dry run completed. No files were offloaded.');
                return;
            }

            $cloud_provider = CloudProviderFactory::create($cloud_provider_key);
            $bulk_offloader = new BulkMediaOffloader($cloud_provider);

            WP_CLI::line(sprintf('Found %d files to offload', $total));
            $progress = \WP_CLI\Utils\make_progress_bar('Offloading files', $total);

            foreach ($attachments as $attachment_id) {
                try {
                    $file_path = get_attached_file($attachment_id);
                    $file_meta = wp_get_attachment_metadata($attachment_id);

                    // Skip if file doesn't exist
                    if (!file_exists($file_path)) {
                        WP_CLI::warning(sprintf('✗ Skipped: %s (File not found)', basename($file_path)));
                        continue;
                    }

                    // Ensure file metadata exists
                    if (!is_array($file_meta)) {
                        $file_meta = array();
                    }

                    // Add filesize if missing
                    if (!isset($file_meta['filesize'])) {
                        $file_meta['filesize'] = filesize($file_path);
                        wp_update_attachment_metadata($attachment_id, $file_meta);
                    }

                    $bulk_offloader->task($attachment_id);
                    WP_CLI::line(sprintf('✓ Offloaded: %s', basename($file_path)));
                } catch (\Exception $e) {
                    WP_CLI::warning(sprintf('✗ Failed to offload %s: %s', basename($file_path), $e->getMessage()));
                }
                $progress->tick();
            }

            $progress->finish();
            WP_CLI::success('Bulk offload completed.');

        } catch (\Exception $e) {
            WP_CLI::error('Error during offload: ' . $e->getMessage());
        }
    }

    private function get_unoffloaded_attachments($batch_size) {
        return get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'posts_per_page' => $batch_size < 0 ? -1 : $batch_size,
            'fields' => 'ids',
            'orderby' => 'post_date',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'advmo_offloaded',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'advmo_offloaded',
                    'compare' => '=',
                    'value' => ''
                ]
            ]
        ]);
    }
}
