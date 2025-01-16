<?php

namespace Advanced_Media_Offloader;

use Advanced_Media_Offloader\Abstracts\S3_Provider;
use Advanced_Media_Offloader\Traits\OffloaderTrait;
use Advanced_Media_Offloader\Interfaces\ObserverInterface;
use Advanced_Media_Offloader\Observers\AttachmentUrlObserver;
use Advanced_Media_Offloader\Observers\AttachmentDeleteObserver;
use Advanced_Media_Offloader\Observers\OffloadStatusObserver;
use Advanced_Media_Offloader\Observers\ImageSrcsetObserver;
use Advanced_Media_Offloader\Observers\ImageSrcsetMetaObserver;
use Advanced_Media_Offloader\Observers\AttachmentUploadObserver;
use Advanced_Media_Offloader\Observers\PostContentImageTagObserver;
use Advanced_Media_Offloader\Observers\AttachmentUpdateObserver;

class Offloader {

	use OffloaderTrait;

	private static $instance = null;
	public $cloudProvider;
	private array $observers = [];
	private function __construct( S3_Provider $cloudProvider ) {
		$this->cloudProvider = $cloudProvider;
	}

	public static function get_instance( S3_Provider $cloudProvider ) {
		if ( self::$instance === null ) {
			self::$instance = new self( $cloudProvider );
		}
		return self::$instance;
	}

	public function initializeHooks() {
		$this->attach( new AttachmentUploadObserver( $this->cloudProvider ) );
		$this->attach( new ImageSrcsetObserver( $this->cloudProvider ) );
		$this->attach( new ImageSrcsetMetaObserver( $this->cloudProvider ) );
		$this->attach( new AttachmentUrlObserver( $this->cloudProvider ) );
		$this->attach( new OffloadStatusObserver( $this->cloudProvider ) );
		$this->attach( new AttachmentDeleteObserver( $this->cloudProvider ) );
		$this->attach( new PostContentImageTagObserver( $this->cloudProvider ) );
		$this->attach( new AttachmentUpdateObserver( $this->cloudProvider ) );

		foreach ( $this->observers as $observer ) {
			$observer->register();
		}
	}

	public function attach( ObserverInterface $observer ) {
		$this->observers[] = $observer;
	}

	public function detach( ObserverInterface $observer ) {
		foreach ( $this->observers as $key => $obs ) {
			if ( $obs === $observer ) {
				unset( $this->observers[ $key ] );
			}
		}
	}

	public function modifyAttachmentUrl( $url, $post_id ) {
		if ( $this->isOffloaded( $post_id ) ) {
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

	private function uploadToCloud( $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		// Verify original file exists
		if ( ! file_exists( $file ) ) {
			error_log( "Advanced Media Offloader - Original file not found: $file" );
			return false;
		}

		$subdir = $this->get_attachment_subdir( $attachment_id );
		$uploadResult = $this->cloudProvider->uploadFile( $file, $subdir . wp_basename( $file ) );

		if ( ! $uploadResult ) {
			update_post_meta( $attachment_id, 'advmo_offloaded', false );
			return false;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Handle image sizes
		if ( ! empty( $metadata['sizes'] ) ) {
			$metadata_sizes = $this->uniqueMetaDataSizes( $metadata['sizes'] );
			foreach ( $metadata_sizes as $size => $data ) {
				$sized_file = str_replace( wp_basename( $file ), $data['file'], $file );

				// Verify sized file exists before attempting upload
				if ( ! file_exists( $sized_file ) ) {
					error_log( "Advanced Media Offloader - Sized file not found: $sized_file" );
					continue;
				}

				$uploadResult = $this->cloudProvider->uploadFile( $sized_file, $subdir . wp_basename( $sized_file ) );
				if ( ! $uploadResult ) {
					update_post_meta( $attachment_id, 'advmo_offloaded', false );
					return false;
				}
			}
		}

		// Only delete local files after ALL uploads are successful
		$deleteLocalRule = $this->shouldDeleteLocal();
		if ( $deleteLocalRule !== 0 ) {
			$this->deleteLocalFile( $attachment_id, $deleteLocalRule );
		}

		return true;
	}
}
