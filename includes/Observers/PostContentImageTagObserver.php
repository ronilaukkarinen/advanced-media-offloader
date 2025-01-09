<?php

namespace Advanced_Media_Offloader\Observers;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Interfaces\ObserverInterface;
use Advanced_Media_Offloader\Traits\OffloaderTrait;

/**
 * Observer class for handling image tags in post content.
 *
 * This class modifies image URLs in post content to use the offloaded cloud storage URLs.
 */
class PostContentImageTagObserver implements ObserverInterface {

    use OffloaderTrait;

    /**
     * @var S3_Provider
     */
    private S3_Provider $cloudProvider;

    /**
     * The base URL for uploads.
     *
     * @var string
     */
    private string $upload_base_url;

    /**
     * Constructor.
     *
     * @param S3_Provider $cloudProvider The cloud storage provider instance.
     */
    public function __construct( S3_Provider $cloudProvider ) {
        $this->cloudProvider = $cloudProvider;
    }

    /**
     * Register the observer with WordPress hooks.
     *
     * @return void
     */
    public function register(): void {
        add_filter( 'wp_content_img_tag', [ $this, 'run' ], 10, 3 );
    }

    /**
     * Modify the image tag to use the offloaded URL.
     *
     * @param string $filtered_image The current image tag HTML.
     * @param string $context       The context in which the image tag is being filtered.
     * @param int    $attachment_id The attachment ID for the image.
     * @return string Modified image tag HTML.
     */
    public function run( $filtered_image, $context, $attachment_id ) {
        if ( ! $this->is_offloaded( $attachment_id ) ) {
            return $filtered_image;
        }

        $src_attr = $this->get_image_src( $filtered_image );
        if ( empty( $src_attr ) ) {
            return $filtered_image;
        }

        $offloaded_image_url = wp_get_attachment_url( $attachment_id );
        $filtered_image = str_replace( $src_attr, $offloaded_image_url, $filtered_image );

        return $filtered_image;
    }

    /**
     * Extract the src attribute from an image tag.
     *
     * @param string $image_tag The HTML image tag to parse.
     * @return string The extracted src URL or empty string if not found.
     */
    private function get_image_src( $image_tag ) {
        $src = '';

        if ( preg_match( '/src=[\'"]?([^\'" >]+)[\'"]?/i', $image_tag, $matches ) ) {
            $src = $matches[1];
        }

        return $src;
    }
}
