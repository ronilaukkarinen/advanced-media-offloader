<?php

namespace Advanced_Media_Offloader\Observers;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Interfaces\ObserverInterface;
use Advanced_Media_Offloader\Services\CloudAttachmentUploader;

class AttachmentUploadObserver implements ObserverInterface {

    private CloudAttachmentUploader $cloudAttachmentUploader;

    public function __construct( S3_Provider $cloudProvider ) {
        $this->cloudAttachmentUploader = new CloudAttachmentUploader( $cloudProvider );
    }

    public function register(): void {
        add_filter( 'wp_generate_attachment_metadata', [ $this, 'handleAttachmentUpload' ], 99, 2 );
    }

    public function handleAttachmentUpload( $metadata, $attachment_id ) {
        $current_filter = current_filter();

        if ( ! $this->cloudAttachmentUploader->uploadAttachment( $attachment_id ) ) {
            if ( $current_filter == 'wp_generate_attachment_metadata' ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }

        return $metadata;
    }
}
