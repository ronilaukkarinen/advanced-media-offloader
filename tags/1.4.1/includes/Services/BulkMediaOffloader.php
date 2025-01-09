<?php

namespace Advanced_Media_Offloader\Services;

use Advanced_Media_Offloader\Offloader;
use Error;
use WP_Background_Process;

class BulkMediaOffloader extends WP_Background_Process
{
    protected $action = 'advmo_bulk_offload_process';

    protected function task($item)
    {
        global $advmo;
        $offloader = $advmo->offloader;

        # get attachment metadata using $item=$attachmentid
        $metadata = wp_get_attachment_metadata($item);
        $offloader->handleAttachmentUpload($metadata, $item);

        $bulk_offload_data = advmo_get_bulk_offload_data();
        $processed = $bulk_offload_data['processed'];
        advmo_update_bulk_offload_data(['processed' => $processed + 1]);

        return false;
    }

    protected function complete()
    {
        parent::complete();
        advmo_update_bulk_offload_data(['status' => null]);
    }
}
