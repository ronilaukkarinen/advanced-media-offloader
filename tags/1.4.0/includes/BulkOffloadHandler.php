<?php

namespace Advanced_Media_Offloader;

use Advanced_Media_Offloader\Services\BulkMediaOffloader;
use Advanced_Media_Offloader\Offloader;

class BulkOffloadHandler
{
    protected $process_all;

    # singleton
    private static $instance = null;

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('wp_ajax_advmo_check_bulk_offload_progress', array($this, 'get_progress'));
        add_action('wp_ajax_advmo_start_bulk_offload', array($this, 'bulk_offload'));
    }

    /**
     * Init
     */
    public function init()
    {
        $this->process_all    = new BulkMediaOffloader();
    }

    public function bulk_offload()
    {
        $this->handle_all();
        $bulk_offload_data = advmo_get_bulk_offload_data();

        wp_send_json_success([
            'total'     => $bulk_offload_data['total'],
        ]);
    }

    public function get_progress()
    {
        $bulk_offload_data = advmo_get_bulk_offload_data();

        wp_send_json_success([
            'processed' => $bulk_offload_data['processed'],
            'total'     => $bulk_offload_data['total'],
            'status'    => $bulk_offload_data['status'],
        ]);
    }

    protected function handle_all()
    {
        $names = $this->get_unoffloaded_attachments();

        foreach ($names as $name) {
            $this->process_all->push_to_queue($name);
        }

        $this->process_all->save()->dispatch();
    }

    protected function get_unoffloaded_attachments($batch_size = 50)
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'posts_per_page' => $batch_size,
            'fields' => 'ids',
            'orderby' => 'post_date',
            'order' => 'ASC',
            'offset' => 0,
            'cache_results' => false,
            'suppress_filters' => false,
            'meta_query' => [
                [
                    'key' => 'advmo_offloaded',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        // save advmo_bulk_offload_total if > 0
        $attachment_count = count($attachments);
        if ($attachment_count > 0) {
            advmo_update_bulk_offload_data(array(
                'total' => $attachment_count,
                'status' => 'processing',
                'processed' => 0
            ));
        } else {
            advmo_clear_bulk_offload_data();
        }

        return $attachments;
    }
}
