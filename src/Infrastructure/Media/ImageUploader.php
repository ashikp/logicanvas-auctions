<?php
/**
 * Controlled image uploads through WordPress media APIs.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Media;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\RateLimiter;
use WP_Error;

final class ImageUploader {

	public const MAX_GALLERY = 12;

	/**
	 * @param array<string, mixed> $file $_FILES-style array.
	 * @return array{id:int,url:string,thumb:string}|WP_Error
	 */
	public function handle( array $file, int $user_id ) {
		if ( $user_id < 1 ) {
			return new WP_Error( 'forbidden', __( 'You must be logged in to upload images.', 'logicanvas-auctions' ) );
		}

		$limited = $this->rate_limited( $user_id );
		if ( $limited ) {
			return $limited;
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'Choose an image to upload.', 'logicanvas-auctions' ) );
		}

		$max = (int) apply_filters( 'wcap_max_image_bytes', 5 * 1024 * 1024 );
		if ( (int) ( $file['size'] ?? 0 ) > $max ) {
			return new WP_Error( 'too_large', __( 'That image is too large. Maximum size is 5 MB.', 'logicanvas-auctions' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$check   = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) ( $file['name'] ?? '' ) );
		$allowed = array(
			'image/jpeg' => true,
			'image/png'  => true,
			'image/gif'  => true,
			'image/webp' => true,
		);
		$type    = (string) ( $check['type'] ?: ( $file['type'] ?? '' ) );
		if ( empty( $allowed[ $type ] ) ) {
			return new WP_Error( 'invalid_type', __( 'Upload a JPEG, PNG, GIF, or WebP image.', 'logicanvas-auctions' ) );
		}

		$image_info = @getimagesize( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $image_info ) || empty( $image_info['mime'] ) || empty( $allowed[ $image_info['mime'] ] ) ) {
			return new WP_Error( 'invalid_type', __( 'Upload a JPEG, PNG, GIF, or WebP image.', 'logicanvas-auctions' ) );
		}

		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'gif'          => 'image/gif',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( isset( $moved['error'] ) ) {
			return new WP_Error( 'upload_failed', (string) $moved['error'] );
		}

		$attachment = array(
			'post_mime_type' => $moved['type'],
			'post_title'     => sanitize_file_name( pathinfo( (string) $moved['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => $user_id,
		);

		$id = wp_insert_attachment( $attachment, $moved['file'] );
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'upload_failed', __( 'Could not save the image.', 'logicanvas-auctions' ) );
		}

		wp_update_attachment_metadata( (int) $id, wp_generate_attachment_metadata( (int) $id, $moved['file'] ) );
		update_post_meta( (int) $id, '_wcap_uploaded_for_auction', '1' );


		return array(
			'id'    => (int) $id,
			'url'   => (string) wp_get_attachment_image_url( (int) $id, 'large' ),
			'thumb' => (string) wp_get_attachment_image_url( (int) $id, 'medium' ),
		);
	}

	public function user_can_use( int $attachment_id, int $user_id ): bool {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}

		$mime = (string) $post->post_mime_type;
		if ( ! str_starts_with( $mime, 'image/' ) ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return true;
		}

		return (int) $post->post_author === $user_id;
	}

	/**
	 * @param array<int|string> $ids
	 * @return int[]
	 */
	public function sanitize_ids( $ids, int $user_id, int $limit = self::MAX_GALLERY ): array {
		if ( is_string( $ids ) ) {
			$ids = preg_split( '/[\s,]+/', $ids ) ?: array();
		}

		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id < 1 || ! $this->user_can_use( $id, $user_id ) ) {
				continue;
			}
			$out[] = $id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return array_values( array_unique( $out ) );
	}

	private function rate_limited( int $user_id ): ?WP_Error {
		$max = (int) apply_filters( 'wcap_image_uploads_per_hour', 40 );
		if ( ! ( new RateLimiter() )->hit( 'img_up_' . $user_id, $max, (int) HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many image uploads. Try again later.', 'logicanvas-auctions' ) );
		}

		return null;
	}
}
