<?php

namespace Advanced_Media_Offloader\Observers;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Interfaces\ObserverInterface;
use Advanced_Media_Offloader\Traits\OffloaderTrait;

class ImageSrcsetObserver implements ObserverInterface
{
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
     * @param S3_Provider $cloudProvider
     */
    public function __construct(S3_Provider $cloudProvider)
    {
        $this->cloudProvider = $cloudProvider;
        $upload_dir = wp_get_upload_dir();
        $this->upload_base_url = trailingslashit($upload_dir['baseurl']);
    }

    /**
     * Register the observer with WordPress hooks.
     *
     * @return void
     */
    public function register(): void
    {
        add_filter('wp_calculate_image_srcset', [$this, 'modifyImageSrcset'], 10, 5);
    }

    /**
     * Modify the image srcset.
     * @param array $sources
     * @param array $size_array
     * @param array $image_src
     * @param array $image_meta
     * @param int $attachment_id
     * @return array
     */
    public function modifyImageSrcset(array $sources, array $size_array, array $image_src, array $image_meta, int $attachment_id)
    {
        if (! $this->is_offloaded($attachment_id)) {
            return $sources;
        }

        $cdn_url = trailingslashit($this->cloudProvider->getDomain());

        return array_map(function ($source) use ($cdn_url) {
            $source['url'] = str_replace($this->upload_base_url, $cdn_url, $source['url']);
            return $source;
        }, $sources);
    }
}
