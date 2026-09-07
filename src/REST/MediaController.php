<?php
/**
 * Image upload REST.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Infrastructure\Media\ImageUploader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class MediaController {

	public function upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) ) {
			return new WP_Error( 'no_file', __( 'Choose an image to upload.', 'logicanvas-auctions' ), array( 'status' => 400 ) );
		}

		$result = ( new ImageUploader() )->handle( $file, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return new WP_REST_Response( $result, 201 );
	}
}
