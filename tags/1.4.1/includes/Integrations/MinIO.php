<?php

namespace Advanced_Media_Offloader\Integrations;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Aws\S3\S3Client;

class MinIO extends S3_Provider {

    public $providerName = 'MinIO';

    public function __construct() {
        // Do nothing.
    }

    public function getProviderName() {
        return $this->providerName;
    }

    public function getClient() {
        return new S3Client([
            'version' => 'latest',
            'endpoint' => defined( 'ADVMO_MINIO_ENDPOINT' ) ? ADVMO_MINIO_ENDPOINT : '',
            'region' => defined( 'ADVMO_MINIO_REGION' ) ? ADVMO_MINIO_REGION : 'us-east-1',
            'credentials' => [
                'key' => defined( 'ADVMO_MINIO_KEY' ) ? ADVMO_MINIO_KEY : '',
                'secret' => defined( 'ADVMO_MINIO_SECRET' ) ? ADVMO_MINIO_SECRET : '',
            ],
            'retries' => 1,
        ]);
    }

    public function getBucket() {
        return defined( 'ADVMO_CLOUDFLARE_R2_BUCKET' ) ? ADVMO_CLOUDFLARE_R2_BUCKET : null;
    }

    public function getDomain() {
        return defined( 'ADVMO_MINIO_DOMAIN' ) ? trailingslashit( ADVMO_MINIO_DOMAIN ) . trailingslashit( ADVMO_MINIO_BUCKET ) : '';
    }

    public function credentialsField() {
        $requiredConstants = [
            'ADVMO_MINIO_KEY' => __( 'ADVMO_MINIO_KEY', 'advanced-media-offloader' ),
            'ADVMO_MINIO_SECRET' => __( 'ADVMO_MINIO_SECRET', 'advanced-media-offloader' ),
            'ADVMO_MINIO_ENDPOINT' => __( 'ADVMO_MINIO_ENDPOINT', 'advanced-media-offloader' ),
            'ADVMO_MINIO_BUCKET' => __( 'ADVMO_MINIO_BUCKET', 'advanced-media-offloader' ),
            'ADVMO_MINIO_DOMAIN' => __( 'ADVMO_MINIO_DOMAIN', 'advanced-media-offloader' ),
        ];

        echo $this->getCredentialsFieldHTML( $requiredConstants );
    }
}
