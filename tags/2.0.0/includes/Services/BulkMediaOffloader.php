<?php

namespace Advanced_Media_Offloader\Services;

use Advanced_Media_Offloader\Services\CloudAttachmentUploader;
use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Abstracts\WP_Background_Processing\WP_Background_Process;

class BulkMediaOffloader extends WP_Background_Process
{
    protected $prefix = 'advmo';
    protected $action = 'bulk_offload_media_process';

    private CloudAttachmentUploader $cloudAttachmentUploader;

    public function __construct(S3_Provider $cloudProvider)
    {
        parent::__construct();
        $this->cloudAttachmentUploader = new CloudAttachmentUploader($cloudProvider);
    }

    protected function task($item)
    {
        // $item is the attachment ID
        $this->cloudAttachmentUploader->uploadAttachment($item);

        // Update the processed count
        $this->update_processed_count();

        return false;
    }

    protected function complete()
    {
        parent::complete();
        advmo_update_bulk_offload_data(['status' => null]);
    }

    public function update_processed_count()
    {
        $bulk_offload_data = advmo_get_bulk_offload_data();
        $processed_count = $bulk_offload_data['processed'];
        $processed_count++;
        advmo_update_bulk_offload_data([
            'processed' => $processed_count,
            'total' => $bulk_offload_data['total'],
            'status' => $bulk_offload_data['status'],
        ]);
    }
}
