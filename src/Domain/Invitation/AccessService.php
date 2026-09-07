<?php
/**
 * Invitation / visibility access.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Invitation;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Auction\Visibility;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class AccessService {

	public function can_view( Auction $auction, int $user_id, string $token = '' ): bool {
		$visibility = $auction->visibility();

		if ( Visibility::PUBLIC_LISTED === $visibility ) {
			return true;
		}

		if ( Visibility::UNLISTED === $visibility ) {
			if ( $user_id > 0 && ( $auction->is_holder( $user_id ) || user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) ) {
				return true;
			}
			return $this->unlisted_token_valid( $auction, $token );
		}

		if ( Visibility::PRIVATE_INVITE === $visibility ) {
			if ( $user_id < 1 ) {
				return false;
			}
			if ( $auction->is_holder( $user_id ) || user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
				return true;
			}
			return $this->invitation_valid( $auction->id(), $user_id, $token );
		}

		if ( Visibility::ROLE_RESTRICTED === $visibility ) {
			/**
			 * Filter role-restricted auction access.
			 *
			 * @param bool    $allowed Default based on bid capability.
			 * @param Auction $auction Auction.
			 * @param int     $user_id User ID.
			 */
			return (bool) apply_filters( 'wcap_role_restricted_access', user_can( $user_id, Config::CAP_BID ), $auction, $user_id );
		}

		return false;
	}

	public function create_invitation( int $auction_id, int $created_by, int $max_uses = 1, ?int $user_id = null, ?string $email = null, ?int $ttl_seconds = null ): string {
		global $wpdb;

		$token = bin2hex( random_bytes( 32 ) );
		$hash  = $this->hash_token( $token );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$exp   = $ttl_seconds ? gmdate( 'Y-m-d H:i:s', time() + $ttl_seconds ) : null;

		$wpdb->insert(
			Config::table( Config::TABLE_INVITATIONS ),
			array(
				'auction_id'      => $auction_id,
				'token_hash'      => $hash,
				'email_hash'      => $email ? hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) ) : null,
				'user_id'         => $user_id,
				'max_uses'        => $max_uses,
				'use_count'       => 0,
				'expires_at_utc'  => $exp,
				'created_by'      => $created_by,
				'created_at_utc'  => $now,
			)
		);

		QueryCache::bust_auction( $auction_id );

		return $token;
	}

	public function revoke( int $invitation_id ): void {
		global $wpdb;

		$wpdb->update(
			Config::table( Config::TABLE_INVITATIONS ),
			array( 'revoked_at_utc' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => $invitation_id )
		);

		QueryCache::flush_group();
	}

	private function hash_token( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	private function unlisted_token_valid( Auction $auction, string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		$hash = $auction->to_array()['unlisted_token_hash'] ?? '';
		if ( ! is_string( $hash ) || '' === $hash ) {
			return false;
		}

		return hash_equals( $hash, $this->hash_token( $token ) );
	}

	private function invitation_valid( int $auction_id, int $user_id, string $token ): bool {
		global $wpdb;

		$table = Config::table( Config::TABLE_INVITATIONS );
		if ( '' === $token ) {
			$row = QueryCache::remember(
				QueryCache::key( 'invite_user', $auction_id, $user_id ),
				60,
				static function () use ( $wpdb, $table, $auction_id, $user_id ) {
					wp_cache_get( 'wcap_db', QueryCache::GROUP );
					return $wpdb->get_row(
						$wpdb->prepare(
							'SELECT * FROM %i WHERE auction_id = %d AND user_id = %d AND revoked_at_utc IS NULL',
							$table,
							$auction_id,
							$user_id
						),
						ARRAY_A
					);
				}
			);
		} else {
			$hash = $this->hash_token( $token );
			$row  = QueryCache::remember(
				QueryCache::key( 'invite_token', $auction_id, $hash ),
				60,
				static function () use ( $wpdb, $table, $auction_id, $hash ) {
					wp_cache_get( 'wcap_db', QueryCache::GROUP );
					return $wpdb->get_row(
						$wpdb->prepare(
							'SELECT * FROM %i WHERE auction_id = %d AND token_hash = %s AND revoked_at_utc IS NULL',
							$table,
							$auction_id,
							$hash
						),
						ARRAY_A
					);
				}
			);
		}

		if ( ! is_array( $row ) ) {
			return false;
		}

		if ( ! empty( $row['expires_at_utc'] ) && strtotime( $row['expires_at_utc'] . ' UTC' ) < time() ) {
			return false;
		}

		if ( (int) $row['use_count'] >= (int) $row['max_uses'] ) {
			return false;
		}

		if ( ! empty( $row['user_id'] ) && (int) $row['user_id'] !== $user_id ) {
			return false;
		}

		return true;
	}
}
