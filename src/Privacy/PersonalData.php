<?php
/**
 * Personal data exporter / eraser.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Privacy;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class PersonalData {

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters
	 * @return array<string, array<string, mixed>>
	 */
	public function exporters( array $exporters ): array {
		$exporters['logicanvas-auctions'] = array(
			'exporter_friendly_name' => Config::PRODUCT_NAME,
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers
	 * @return array<string, array<string, mixed>>
	 */
	public function erasers( array $erasers ): array {
		$erasers['logicanvas-auctions'] = array(
			'eraser_friendly_name' => Config::PRODUCT_NAME,
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		global $wpdb;
		$table = Config::table( Config::TABLE_BIDS );
		$bids  = QueryCache::remember(
			QueryCache::key( 'privacy_bids', $user->ID ),
			60,
			static function () use ( $wpdb, $table, $user ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, auction_id, amount, currency, created_at_utc FROM %i WHERE bidder_id = %d',
						$table,
						$user->ID
					),
					ARRAY_A
				);
			}
		);

		$items = array();
		foreach ( (array) $bids as $bid ) {
			$items[] = array(
				'group_id'    => 'wcap-bids',
				'group_label' => __( 'Auction bids', 'logicanvas-auctions' ),
				'item_id'     => 'bid-' . $bid['id'],
				'data'        => array(
					array( 'name' => __( 'Auction', 'logicanvas-auctions' ), 'value' => (string) $bid['auction_id'] ),
					array( 'name' => __( 'Amount', 'logicanvas-auctions' ), 'value' => (string) $bid['amount'] ),
					array( 'name' => __( 'Time', 'logicanvas-auctions' ), 'value' => (string) $bid['created_at_utc'] ),
				),
			);
		}

		return array( 'data' => $items, 'done' => true );
	}

	/**
	 * Financial records are retained; IP hashes and aliases can be anonymized.
	 *
	 * @return array{items_removed:bool,items_retained:bool,messages:string[],done:bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;
		$wpdb->update(
			Config::table( Config::TABLE_BIDS ),
			array(
				'ip_hash'         => null,
				'user_agent_hash' => null,
			),
			array( 'bidder_id' => $user->ID )
		);

		QueryCache::flush_group();

		delete_user_meta( $user->ID, 'wcap_bidder_alias' );

		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array( __( 'Bid financial records were retained and identifying hashes were removed.', 'logicanvas-auctions' ) ),
			'done'           => true,
		);
	}

	public function privacy_policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			Config::PRODUCT_NAME,
			__( 'This site stores auction bids, awards, and settlement records. Public bid history uses a masked name. IP addresses are stored as hashes when enabled. Financial records are retained after account erasure where legally required.', 'logicanvas-auctions' )
		);
	}
}
