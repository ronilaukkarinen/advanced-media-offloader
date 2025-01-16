<?php

namespace Advanced_Media_Offloader\Observers;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Interfaces\ObserverInterface;
use Advanced_Media_Offloader\Traits\OffloaderTrait;

class AttachmentUrlObserver implements ObserverInterface {

    use OffloaderTrait;

    /**
     * @var S3_Provider
     */
    private S3_Provider $cloudProvider;

    /**
     * Constructor.
     *
     * @param S3_Provider $cloudProvider // phpcs:ignore Squiz.Commenting.FunctionComment.MissingParamComment
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
        add_filter( 'wp_get_attachment_url', [ $this, 'run' ], 10, 2 );
        add_filter( 'wp_calculate_image_srcset', [ $this, 'modifyImageSrcset' ], 10, 5 );
    }

    /**
     * Modify the attachment URL if the media is offloaded.
     *
     * @param string $url      The original URL.
     * @param int    $post_id  The attachment ID.
     * @return string          The modified URL.
     */
    public function run( string $url, int $post_id ): string {
        if ( $this->is_offloaded( $post_id ) ) {
            $subDir = $this->get_attachment_subdir( $post_id );
            $domain = rtrim( $this->cloudProvider->getDomain(), '/' );
            $subDir = trim( $subDir, '/' );
            $basename = basename( $url );

            // Build URL ensuring single slashes between parts
            $parts = array_filter( [ $domain, $subDir, $basename ] ); // Remove empty elements
            $url = implode( '/', $parts );
        }
        return $url;
    }

    /**
     * Modify image srcset URLs if the media is offloaded.
     *
     * @param array  $sources    Array of image sources.
     * @param array  $size_array Array of width and height values.
     * @param string $image_src  The 'src' of the image.
     * @param array  $image_meta The image meta data.
     * @param int    $attachment_id The attachment ID.
     * @return array Modified sources array.
     */
    public function modifyImageSrcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ): array {
      if ( $this->is_offloaded( $attachment_id ) ) {
        $domain = rtrim( $this->cloudProvider->getDomain(), '/' );
        $upload_dir = wp_get_upload_dir();
        $base_url = rtrim( $upload_dir['baseurl'], '/' );

        foreach ( $sources as $key => $source ) {
          $sources[ $key ]['url'] = str_replace( $base_url, $domain, $source['url'] );
        }
      }
      return $sources;
    }
}
