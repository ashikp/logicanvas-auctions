<?php
/**
 * Auction REST endpoints.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\REST;

use LogicanvasAuctions\Application\AuctionPresenter;
use LogicanvasAuctions\Core\Logger;
use LogicanvasAuctions\Domain\Auction\AuctionService;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class AuctionController {

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$type     = sanitize_key( (string) ( $request->get_param( 'type' ) ?? '' ) );
		$states   = array_filter( array_map( 'sanitize_key', explode( ',', (string) ( $request->get_param( 'state' ) ?? '' ) ) ) );
		$states   = array_values( array_filter( $states, static fn( string $state ) => \LogicanvasAuctions\Domain\Auction\AuctionState::is_valid( $state ) ) );
		if ( ! $states ) {
			$states = array( 'active', 'scheduled', 'live', 'lobby' );
		}

		$args = array(
			'limit'  => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
			'state'  => $states,
			'type'   => $type,
			'search' => sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) ),
		);

		$repo    = new WpdbAuctionRepository();
		$items   = $repo->query( $args );
		$total   = $repo->count( $args );
		$present = new AuctionPresenter();
		$user    = get_current_user_id();

		$data = array_map( static fn( $a ) => $present->public_state( $a, $user ), $items );

		$response = new WP_REST_Response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );
		return $response;
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auction = ( new WpdbAuctionRepository() )->find( (int) $request['id'] );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ), array( 'status' => 404 ) );
		}

		$payload = ( new AuctionPresenter() )->public_state( $auction, get_current_user_id(), RestAccess::invite_token( $request ) );
		if ( empty( $payload['accessible'] ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this auction.', 'logicanvas-auctions' ), array( 'status' => 403 ) );
		}

		return new WP_REST_Response( $payload );
	}

	public function state( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->show( $request );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$raw = $request->get_json_params();
		if ( ! is_array( $raw ) || ! $raw ) {
			$raw = $request->get_params();
		}
		$params = \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::sanitize( is_array( $raw ) ? $raw : array(), false );
		if ( is_wp_error( $params ) ) {
			$params->add_data( array( 'status' => 400 ) );
			return $params;
		}

		try {
			$result = ( new AuctionService() )->create( get_current_user_id(), $params );
		} catch ( \Throwable $e ) {
			( new Logger() )->error( 'Auction create failed', array( 'error' => $e->getMessage() ) );
			return new WP_Error( 'server_error', __( 'Could not create the auction.', 'logicanvas-auctions' ), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		$auction = ( new WpdbAuctionRepository() )->find( (int) $result );
		if ( ! $auction ) {
			return new WP_REST_Response( array( 'id' => (int) $result, 'state' => 'draft' ), 201 );
		}

		return new WP_REST_Response( ( new AuctionPresenter() )->public_state( $auction, get_current_user_id() ), 201 );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = (int) $request['id'];
		$raw = $request->get_json_params();
		if ( ! is_array( $raw ) || ! $raw ) {
			$raw = $request->get_params();
		}
		// Drop route params so they cannot override typed body fields.
		if ( is_array( $raw ) ) {
			unset( $raw['id'], $raw['rest_route'] );
		}
		$params = \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::sanitize( is_array( $raw ) ? $raw : array(), false );
		if ( is_wp_error( $params ) ) {
			$params->add_data( array( 'status' => 400 ) );
			return $params;
		}

		try {
			$result = ( new AuctionService() )->update_draft( $id, get_current_user_id(), $params );
		} catch ( \Throwable $e ) {
			( new Logger() )->error( 'Auction update failed', array( 'error' => $e->getMessage(), 'id' => $id ) );
			return new WP_Error( 'server_error', __( 'Could not update the auction.', 'logicanvas-auctions' ), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $result ) ) {
			$code   = $result->get_error_code();
			$status = in_array( $code, array( 'forbidden', 'not_found' ), true ) ? ( 'forbidden' === $code ? 403 : 404 ) : 400;
			$result->add_data( array( 'status' => $status ) );
			return $result;
		}

		$auction = ( new WpdbAuctionRepository() )->find( $id );
		if ( ! $auction ) {
			return new WP_REST_Response( array( 'id' => $id, 'ok' => true ) );
		}

		return new WP_REST_Response( ( new AuctionPresenter() )->public_state( $auction, get_current_user_id() ) );
	}

	public function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = (int) $request['id'];
		$auction = ( new WpdbAuctionRepository() )->find( $id );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ), array( 'status' => 404 ) );
		}
		if ( $auction->holder_id() !== get_current_user_id() && ! current_user_can( \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ) ) {
			return new WP_Error( 'forbidden', __( 'Forbidden.', 'logicanvas-auctions' ), array( 'status' => 403 ) );
		}

		$result = ( new AuctionService() )->submit( $id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'ok' => true ) );
	}

	public function approve( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = (int) $request['id'];
		$auction = ( new WpdbAuctionRepository() )->find( $id );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ), array( 'status' => 404 ) );
		}

		$result = ( new AuctionService() )->approve( $id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		$auction = ( new WpdbAuctionRepository() )->find( $id );
		if ( ! $auction ) {
			return new WP_REST_Response( array( 'id' => $id, 'ok' => true ), 200 );
		}

		return new WP_REST_Response( ( new AuctionPresenter() )->public_state( $auction, get_current_user_id() ) );
	}

	public function accept_bid( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = (int) $request['id'];
		$auction = ( new WpdbAuctionRepository() )->find( $id );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ), array( 'status' => 404 ) );
		}

		$result = ( new \LogicanvasAuctions\Domain\Award\AwardService() )->accept_highest_bid( $id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$status = in_array( $code, array( 'forbidden', 'not_found' ), true ) ? ( 'forbidden' === $code ? 403 : 404 ) : 400;
			$result->add_data( array( 'status' => $status ) );
			return $result;
		}

		$auction = ( new WpdbAuctionRepository() )->find( $id );
		if ( ! $auction ) {
			return new WP_REST_Response( array( 'ok' => true, 'id' => $id ) );
		}

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'auction' => ( new AuctionPresenter() )->public_state( $auction, get_current_user_id() ),
			)
		);
	}
}
