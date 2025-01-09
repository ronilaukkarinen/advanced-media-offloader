<?php

namespace Advanced_Media_Offloader\Services;

use Advanced_Media_Offloader\Offloader;
use Error;
use WP_Background_Process;

class BulkMediaOffloader extends WP_Background_Process
{
    protected $prefix = 'advmo';
    protected $action = 'bulk_offload_media_process';

    protected function task($item)
    {
        global $advmo;
        $offloader = $advmo->offloader;

        # get attachment metadata using $item=$attachmentid
        $metadata = wp_get_attachment_metadata($item);
        $offloader->handleAttachmentUpload($metadata, $item);

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
        advmo_update_bulk_offload_data(array(
            'processed' => $processed_count,
            'total' => $bulk_offload_data['total'],
            'status' => $bulk_offload_data['status'],
        ));
    }
}
