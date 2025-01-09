<?php

namespace Advanced_Media_Offloader\Observers;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Interfaces\ObserverInterface;
use Advanced_Media_Offloader\Traits\OffloaderTrait;

class ImageSrcsetObserver implements ObserverInterface {

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
        $upload_dir = wp_get_upload_dir();
        $this->upload_base_url = trailingslashit( $upload_dir['baseurl'] );
    }

    /**
     * Register the observer with WordPress hooks.
     *
     * @return void
     */
    public function register(): void {
        add_filter( 'wp_calculate_image_srcset', [ $this, 'run' ], 10, 5 );
    }

    /**
     * Modify the image srcset.
     * @param array  $sources       Array of image sources with descriptor and URL.
     * @param array  $size_array    Array of width and height values.
     * @param string $image_src     The URL of the image.
     * @param array  $image_meta    The image meta data.
     * @param int    $attachment_id The attachment ID.
     * @return array Modified array of image sources.
     */
    public function run( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
        if ( ! $this->is_offloaded( $attachment_id ) ) {
            return $sources;
        }

        $subDir = $this->get_attachment_subdir( $attachment_id );
        return array_map(function ( $source ) use ( $subDir ) {
            $source['url'] = rtrim( $this->cloudProvider->getDomain(), '/' ) . '/' . ltrim( $subDir, '/' ) . basename( $source['url'] );
            return $source;
        }, $sources);
    }
}
