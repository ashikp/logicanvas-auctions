<?php
/**
 * Payout proof document uploads (PDF / images).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Media;

use WP_Error;

final class ProofUploader {

	/**
	 * @param array<string, mixed> $file $_FILES-style array.
	 * @return array{id:int,url:string,title:string}|WP_Error
	 */
	public function handle( array $file, int $user_id, int $payout_request_id ) {
		if ( $user_id < 1 ) {
			return new WP_Error( 'forbidden', __( 'You must be logged in to upload proof.', 'logicanvas-auctions' ) );
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'Choose a file to upload.', 'logicanvas-auctions' ) );
		}

		$max = (int) apply_filters( 'wcap_max_proof_bytes', 8 * 1024 * 1024 );
		if ( (int) ( $file['size'] ?? 0 ) > $max ) {
			return new WP_Error( 'too_large', __( 'That file is too large. Maximum size is 8 MB.', 'logicanvas-auctions' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) ( $file['name'] ?? '' ) );
		$type  = (string) ( $check['type'] ?: ( $file['type'] ?? '' ) );
		$ext   = strtolower( (string) ( $check['ext'] ?: pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) );

		$allowed = array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
		);

		if ( ! isset( $allowed[ $ext ] ) ) {
			return new WP_Error( 'invalid_type', __( 'Upload a PDF, JPEG, PNG, or WebP file.', 'logicanvas-auctions' ) );
		}

		// Prefer sniffed type when available.
		if ( '' !== $type && $type !== $allowed[ $ext ] && ! ( 'image/jpeg' === $allowed[ $ext ] && 'image/jpeg' === $type ) ) {
			// Allow common PDF sniff variance.
			if ( ! ( 'pdf' === $ext && ( 'application/pdf' === $type || 'application/x-pdf' === $type ) ) ) {
				if ( ! str_starts_with( $allowed[ $ext ], 'image/' ) || ! str_starts_with( $type, 'image/' ) ) {
					return new WP_Error( 'invalid_type', __( 'Upload a PDF, JPEG, PNG, or WebP file.', 'logicanvas-auctions' ) );
				}
			}
		}

		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'pdf'          => 'application/pdf',
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( isset( $moved['error'] ) ) {
			return new WP_Error( 'upload_failed', (string) $moved['error'] );
		}

		$attachment = array(
			'post_mime_type' => (string) ( $moved['type'] ?? $allowed[ $ext ] ),
			'post_title'     => sanitize_file_name( (string) ( $file['name'] ?? 'payout-proof' ) ),
			'post_content'   => '',
			'post_status'    => 'private',
			'post_author'    => $user_id,
		);

		$attach_id = wp_insert_attachment( $attachment, (string) $moved['file'] );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return new WP_Error( 'attach_failed', __( 'Could not save the proof document.', 'logicanvas-auctions' ) );
		}

		$meta = wp_generate_attachment_metadata( (int) $attach_id, (string) $moved['file'] );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( (int) $attach_id, $meta );
		}

		update_post_meta( (int) $attach_id, '_wcap_payout_request_id', $payout_request_id );
		update_post_meta( (int) $attach_id, '_wcap_proof_document', 1 );

		return array(
			'id'    => (int) $attach_id,
			'url'   => (string) wp_get_attachment_url( (int) $attach_id ),
			'title' => get_the_title( (int) $attach_id ),
		);
	}
}
