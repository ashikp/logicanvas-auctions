<?php
/**
 * Auction holder accounts.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Holder;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class HolderService {

	public const PENDING  = 'pending';
	public const APPROVED = 'approved';
	public const REJECTED = 'rejected';
	public const SUSPENDED = 'suspended';

	/**
	 * @param array<string, mixed> $data
	 */
	public function apply( int $user_id, array $data ): int {
		global $wpdb;

		$existing = $this->for_user( $user_id );
		$now      = gmdate( 'Y-m-d H:i:s' );

		if ( $existing ) {
			$wpdb->update(
				Config::table( Config::TABLE_HOLDERS ),
				array(
					'status'             => self::PENDING,
					'company'            => sanitize_text_field( (string) ( $data['company'] ?? '' ) ),
					'application_notes'  => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
					'updated_at_utc'     => $now,
				),
				array( 'user_id' => $user_id )
			);
			QueryCache::delete( QueryCache::key( 'holder', $user_id ) );
			QueryCache::flush_group();
			return (int) $existing['id'];
		}

		$wpdb->insert(
			Config::table( Config::TABLE_HOLDERS ),
			array(
				'user_id'            => $user_id,
				'status'             => self::PENDING,
				'company'            => sanitize_text_field( (string) ( $data['company'] ?? '' ) ),
				'application_notes'  => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
				'created_at_utc'     => $now,
				'updated_at_utc'     => $now,
			)
		);

		QueryCache::flush_group();

		return (int) $wpdb->insert_id;
	}

	public function approve( int $user_id, int $reviewer_id ): void {
		$this->set_status( $user_id, self::APPROVED, $reviewer_id );
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$user->add_role( Config::HOLDER_ROLE );
			foreach ( Config::holder_capabilities() as $cap ) {
				$user->add_cap( $cap );
			}
		}
		do_action( 'wcap_holder_approved', $user_id );
	}

	public function reject( int $user_id, int $reviewer_id, string $notes = '' ): void {
		global $wpdb;

		$wpdb->update(
			Config::table( Config::TABLE_HOLDERS ),
			array(
				'status'            => self::REJECTED,
				'moderation_notes'  => sanitize_textarea_field( $notes ),
				'reviewed_by'       => $reviewer_id,
				'reviewed_at_utc'   => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_utc'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'user_id' => $user_id )
		);
		QueryCache::flush_group();
		do_action( 'wcap_holder_rejected', $user_id );
	}

	public function suspend( int $user_id, int $reviewer_id, string $notes = '' ): void {
		global $wpdb;

		$wpdb->update(
			Config::table( Config::TABLE_HOLDERS ),
			array(
				'status'           => self::SUSPENDED,
				'moderation_notes' => sanitize_textarea_field( $notes ),
				'reviewed_by'      => $reviewer_id,
				'reviewed_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_utc'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'user_id' => $user_id )
		);
		QueryCache::flush_group();
	}

	public function is_approved( int $user_id ): bool {
		$row = $this->for_user( $user_id );
		if ( $row && self::APPROVED === $row['status'] ) {
			return true;
		}

		return user_can( $user_id, 'manage_options' );
	}

	public function is_suspended_user( int $user_id ): bool {
		$row = $this->for_user( $user_id );
		return is_array( $row ) && self::SUSPENDED === $row['status'];
	}

	public function can_host( int $user_id ): bool {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		return $this->is_approved( $user_id ) && user_can( $user_id, Config::CAP_HOST_LIVE ) && ! $this->is_suspended_user( $user_id );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function for_user( int $user_id ): ?array {
		global $wpdb;

		$table = Config::table( Config::TABLE_HOLDERS );
		$row   = QueryCache::remember(
			QueryCache::key( 'holder', $user_id ),
			60,
			static function () use ( $wpdb, $table, $user_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE user_id = %d',
						$table,
						$user_id
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? $row : null;
	}

	private function set_status( int $user_id, string $status, int $reviewer_id ): void {
		global $wpdb;

		$wpdb->update(
			Config::table( Config::TABLE_HOLDERS ),
			array(
				'status'          => $status,
				'reviewed_by'     => $reviewer_id,
				'reviewed_at_utc' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'user_id' => $user_id )
		);
		QueryCache::flush_group();
	}
}
