<?php

namespace Advanced_Media_Offloader\Services;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Traits\OffloaderTrait;

class CloudAttachmentUploader
{
    use OffloaderTrait;

    private S3_Provider $cloudProvider;

    public function __construct(S3_Provider $cloudProvider)
    {
        $this->cloudProvider = $cloudProvider;
    }

    public function uploadAttachment(int $attachment_id): bool
    {
        if ($this->is_offloaded($attachment_id)) {
            return true;
        }

        if ($this->uploadToCloud($attachment_id)) {
            $this->updateAttachmentMetadata($attachment_id);
            return true;
        }

        return false;
    }

    private function uploadToCloud(int $attachment_id): bool
    {
        /**
         * Fires before the attachment is uploaded to the cloud.
         *
         * This action allows developers to perform tasks or logging before
         * the attachment is uploaded to the cloud.
         *
         * @param int $attachment_id
         */
        do_action('advmo_before_upload_to_cloud', $attachment_id);

        $file = get_attached_file($attachment_id);
        $subdir = $this->get_attachment_subdir($attachment_id);
        $uploadResult = $this->cloudProvider->uploadFile($file, $subdir . wp_basename($file));

        if (!$uploadResult) {
            update_post_meta($attachment_id, 'advmo_offloaded', false);
            return false;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $metadata_sizes = $this->uniqueMetaDataSizes($metadata['sizes']);
            foreach ($metadata_sizes as $size => $data) {
                $file = get_attached_file($attachment_id, true);
                $file = str_replace(wp_basename($file), $data['file'], $file);
                $uploadResult = $this->cloudProvider->uploadFile($file, $subdir . wp_basename($file));
                if (!$uploadResult) {
                    update_post_meta($attachment_id, 'advmo_offloaded', false);
                    return false;
                }
            }
        }

        $deleteLocalRule = $this->shouldDeleteLocal();
        if ($deleteLocalRule !== 0) {
            $this->deleteLocalFile($attachment_id, $deleteLocalRule);
        }

        /**
         * Fires after the attachment has been uploaded to the cloud.
         *
         * This action allows developers to perform additional tasks or logging after
         * the attachment has been uploaded to the cloud.
         *
         * @param int $attachment_id    The ID of the attachment that was processed.
         */
        do_action('advmo_after_upload_to_cloud', $attachment_id);

        return true;
    }

    private function updateAttachmentMetadata(int $attachment_id): void
    {
        update_post_meta($attachment_id, 'advmo_path', $this->get_attachment_subdir($attachment_id));
        update_post_meta($attachment_id, 'advmo_offloaded', true);
        update_post_meta($attachment_id, 'advmo_offloaded_at', time());
        update_post_meta($attachment_id, 'advmo_provider', $this->cloudProvider->getProviderName());
        update_post_meta($attachment_id, 'advmo_bucket', $this->cloudProvider->getBucket());
    }

    private function deleteLocalFile(int $attachment_id, int $deleteLocalRule): bool
    {
        /**
         * Fires before the local file(s) associated with an attachment are deleted.
         *
         * This action allows developers to perform tasks or logging before
         * the local files are removed following a successful cloud upload.
         *
         * @param int $attachment_id    The ID of the attachment to be processed.
         * @param int $deleteLocalRule  The rule to be applied for local file deletion:
         *                              1 - Delete only sized images, keep original.
         *                              2 - Delete all local files including the original.
         */
        do_action('advmo_before_delete_local_file', $attachment_id, $deleteLocalRule);

        $original_file = get_attached_file($attachment_id, true);

        if (!file_exists($original_file)) {
            error_log("Advanced Media Offloader: Original file not found for deletion: $original_file");
            return false;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $path = trailingslashit($upload_dir['path']);
            foreach ($metadata['sizes'] as $size => $sizeinfo) {
                $sized_file = $path . $sizeinfo['file'];
                if (file_exists($sized_file)) {
                    wp_delete_file($sized_file);
                }
            }
        }

        if ($deleteLocalRule === 2) {
            wp_delete_file($original_file);
        }

        update_post_meta($attachment_id, 'advmo_retention_policy', $deleteLocalRule);

        /**
         * Fires after the local file(s) associated with an attachment have been deleted.
         *
         * This action allows developers to perform additional tasks or logging after
         * the local files have been removed following a successful cloud upload.
         *
         * @param int $attachment_id    The ID of the attachment that was processed.
         * @param int $deleteLocalRule  The rule applied for local file deletion:
         *                              1 - Delete only sized images, keep original.
         *                              2 - Delete all local files including the original.
         */
        do_action('advmo_after_delete_local_file', $attachment_id, $deleteLocalRule);

        return true;
    }
}
