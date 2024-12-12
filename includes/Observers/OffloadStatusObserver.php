<?php

namespace Advanced_Media_Offloader\Observers;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Interfaces\ObserverInterface;
use Advanced_Media_Offloader\Traits\OffloaderTrait;

class OffloadStatusObserver implements ObserverInterface
{
    use OffloaderTrait;

    /**
     * Cloud provider instance.
     *
     * @var S3_Provider
     */
    private S3_Provider $cloudProvider;

    /**
     * Meta keys for offload information.
     */
    private const META_OFFLOADED_AT = 'advmo_offloaded_at';
    private const META_PROVIDER = 'advmo_provider';
    private const META_BUCKET = 'advmo_bucket';

    /**
     * Constructor.
     *
     * @param S3_Provider $cloudProvider The cloud provider instance.
     */
    public function __construct(S3_Provider $cloudProvider)
    {
        $this->cloudProvider = $cloudProvider;
    }

    /**
     * Register the observer with WordPress hooks.
     *
     * @return void
     */
    public function register(): void
    {
        add_filter('attachment_fields_to_edit', [$this, 'run'], 10, 2);
    }

    /**
     * Display the offload status of the attachment.
     *
     * @param array    $form_fields The form fields for the attachment.
     * @param \WP_Post $post        The attachment post object.
     * @return array The modified form fields.
     */
    public function run(array $form_fields, \WP_Post $post): array
    {
        $status_details = $this->getOffloadStatusDetails($post->ID);

        $form_fields['advmo_offload_status'] = [
            'label' => __('Offload Status:', 'advanced-media-offloader'),
            'input' => 'html',
            'html'  => $this->generateStatusHtml($status_details),
        ];

        return $form_fields;
    }

    /**
     * Get offload status details for an attachment.
     *
     * @param int $post_id The attachment post ID.
     * @return array The offload status details.
     */
    private function getOffloadStatusDetails(int $post_id): array
    {
        if ($this->is_offloaded($post_id)) {
            return [
                'status' => $this->getOffloadedStatus($post_id),
                'color' => 'green',
            ];
        }

        return [
            'status' => __('Not offloaded', 'advanced-media-offloader'),
            'color' => 'red',
        ];
    }

    /**
     * Get the offloaded status message.
     *
     * @param int $post_id The attachment post ID.
     * @return string The formatted status message.
     */
    private function getOffloadedStatus(int $post_id): string
    {
        $offloaded_at = get_post_meta($post_id, self::META_OFFLOADED_AT, true);
        $provider = get_post_meta($post_id, self::META_PROVIDER, true);
        $bucket = get_post_meta($post_id, self::META_BUCKET, true);

        $status = sprintf(__('Offloaded to %s', 'advanced-media-offloader'), $provider);

        if ($bucket) {
            $status .= sprintf(__(' (Bucket: %s)', 'advanced-media-offloader'), $bucket);
        }

        if ($offloaded_at) {
            $formatted_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $offloaded_at);
            $status .= sprintf(__(' on %s', 'advanced-media-offloader'), $formatted_date);
        }

        return $status;
    }

    /**
     * Generate the HTML for displaying the offload status.
     *
     * @param array $status_details The offload status details.
     * @return string The generated HTML.
     */
    private function generateStatusHtml(array $status_details): string
    {
        return sprintf(
            '<div style="display: flex; align-items: center; height: 100%%; min-height: 30px;">
                <span style="color: %s;">%s</span>
            </div>',
            esc_attr($status_details['color']),
            esc_html($status_details['status'])
        );
    }
}
