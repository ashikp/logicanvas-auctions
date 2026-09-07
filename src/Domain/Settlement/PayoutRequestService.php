<?php
/**
 * Seller payout requests against pending settlement balance.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Settlement;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Money\Decimal;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuditRepository;

final class PayoutRequestService {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_PAID     = 'paid';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_CANCELLED = 'cancelled';

	public const META_PAYMENT_METHOD = 'wcap_payout_payment_method';

	/**
	 * @return array<string, string>
	 */
	public static function method_labels(): array {
		return array(
			'bank_transfer' => __( 'Bank transfer', 'logicanvas-auctions' ),
			'paypal'        => __( 'PayPal', 'logicanvas-auctions' ),
			'other'         => __( 'Other', 'logicanvas-auctions' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_payment_method( int $holder_id ): array {
		$raw = get_user_meta( $holder_id, self::META_PAYMENT_METHOD, true );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return array(
			'method'         => sanitize_key( (string) ( $raw['method'] ?? '' ) ),
			'account_name'   => sanitize_text_field( (string) ( $raw['account_name'] ?? '' ) ),
			'bank_name'      => sanitize_text_field( (string) ( $raw['bank_name'] ?? '' ) ),
			'account_number' => sanitize_text_field( (string) ( $raw['account_number'] ?? '' ) ),
			'routing'        => sanitize_text_field( (string) ( $raw['routing'] ?? '' ) ),
			'paypal_email'   => sanitize_email( (string) ( $raw['paypal_email'] ?? '' ) ),
			'other_details'  => sanitize_textarea_field( (string) ( $raw['other_details'] ?? '' ) ),
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return true|\WP_Error
	 */
	public function save_payment_method( int $holder_id, array $input ) {
		if ( $holder_id < 1 ) {
			return new \WP_Error( 'forbidden', __( 'Invalid account.', 'logicanvas-auctions' ) );
		}

		$method  = sanitize_key( (string) ( $input['method'] ?? '' ) );
		$allowed = array_keys( self::method_labels() );
		if ( ! in_array( $method, $allowed, true ) ) {
			return new \WP_Error( 'invalid_method', __( 'Select a valid payout method.', 'logicanvas-auctions' ) );
		}

		$data = array(
			'method'         => $method,
			'account_name'   => sanitize_text_field( (string) ( $input['account_name'] ?? '' ) ),
			'bank_name'      => sanitize_text_field( (string) ( $input['bank_name'] ?? '' ) ),
			'account_number' => sanitize_text_field( (string) ( $input['account_number'] ?? '' ) ),
			'routing'        => sanitize_text_field( (string) ( $input['routing'] ?? '' ) ),
			'paypal_email'   => sanitize_email( (string) ( $input['paypal_email'] ?? '' ) ),
			'other_details'  => sanitize_textarea_field( (string) ( $input['other_details'] ?? '' ) ),
		);

		if ( 'bank_transfer' === $method ) {
			if ( '' === $data['account_name'] || '' === $data['account_number'] ) {
				return new \WP_Error( 'incomplete', __( 'Enter account name and account number for bank transfer.', 'logicanvas-auctions' ) );
			}
		} elseif ( 'paypal' === $method ) {
			if ( '' === $data['paypal_email'] || ! is_email( $data['paypal_email'] ) ) {
				return new \WP_Error( 'incomplete', __( 'Enter a valid PayPal email address.', 'logicanvas-auctions' ) );
			}
		} elseif ( '' === $data['other_details'] ) {
			return new \WP_Error( 'incomplete', __( 'Describe how you want to receive payouts.', 'logicanvas-auctions' ) );
		}

		update_user_meta( $holder_id, self::META_PAYMENT_METHOD, $data );

		return true;
	}

	public function payment_method_is_complete( int $holder_id ): bool {
		$data   = $this->get_payment_method( $holder_id );
		$method = (string) $data['method'];
		if ( 'bank_transfer' === $method ) {
			return '' !== $data['account_name'] && '' !== $data['account_number'];
		}
		if ( 'paypal' === $method ) {
			return '' !== $data['paypal_email'] && is_email( $data['paypal_email'] );
		}
		if ( 'other' === $method ) {
			return '' !== $data['other_details'];
		}

		return false;
	}

	/**
	 * @return array{amount:string,currency:string,formatted:string,pending:string,reserved:string}
	 */
	public function available_balance( int $holder_id ): array {
		global $wpdb;

		$currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'USD';
		$settle_t = Config::table( Config::TABLE_SETTLEMENTS );
		$rows     = QueryCache::remember(
			QueryCache::key( 'payout_pending', $holder_id ),
			45,
			static function () use ( $wpdb, $settle_t, $holder_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT net_amount, released_amount, currency FROM %i WHERE holder_id = %d AND payout_status = %s',
						$settle_t,
						$holder_id,
						SettlementService::PAYOUT_PENDING
					),
					ARRAY_A
				);
			}
		);

		$pending_s = '0.00';
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$net      = Decimal::round( (string) ( $row['net_amount'] ?? '0' ), 2 );
				$released = Decimal::round( (string) ( $row['released_amount'] ?? '0' ), 2 );
				$left     = Decimal::sub( $net, $released, 2 );
				if ( Decimal::cmp( $left, '0', 2 ) > 0 ) {
					$pending_s = Decimal::add( $pending_s, $left, 2 );
				}
				if ( ! empty( $row['currency'] ) ) {
					$currency = (string) $row['currency'];
				}
			}
		}

		$payout_t = Config::table( Config::TABLE_PAYOUT_REQUESTS );
		$reserved = QueryCache::remember(
			QueryCache::key( 'payout_reserved', $holder_id ),
			45,
			static function () use ( $wpdb, $payout_t, $holder_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COALESCE(SUM(amount), 0) FROM %i WHERE holder_id = %d AND status IN (%s, %s)',
						$payout_t,
						$holder_id,
						self::STATUS_PENDING,
						self::STATUS_APPROVED
					)
				);
			}
		);

		$reserved_s = Decimal::round( (string) ( $reserved ?: '0' ), 2 );
		$available  = Decimal::sub( $pending_s, $reserved_s, 2 );
		if ( Decimal::cmp( $available, '0', 2 ) < 0 ) {
			$available = '0.00';
		}

		return array(
			'amount'    => Decimal::round( $available, 2 ),
			'currency'  => $currency,
			'formatted' => Decimal::round( $available, 2 ) . ' ' . $currency,
			'pending'   => $pending_s,
			'reserved'  => $reserved_s,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return int|\WP_Error
	 */
	public function request( int $holder_id, array $input ) {
		if ( $holder_id < 1 ) {
			return new \WP_Error( 'forbidden', __( 'You cannot request payouts.', 'logicanvas-auctions' ) );
		}
		$can = user_can( $holder_id, Config::CAP_VIEW_OWN_SETTLEMENTS )
			|| user_can( $holder_id, Config::CAP_CREATE_AUCTIONS )
			|| user_can( $holder_id, 'manage_options' );
		if ( ! $can ) {
			return new \WP_Error( 'forbidden', __( 'You cannot request payouts.', 'logicanvas-auctions' ) );
		}

		if ( ! $this->payment_method_is_complete( $holder_id ) ) {
			return new \WP_Error( 'method_required', __( 'Save your payout payment method before requesting a payout.', 'logicanvas-auctions' ) );
		}

		$amount_raw = sanitize_text_field( (string) ( $input['amount'] ?? '' ) );
		$amount_raw = str_replace( ',', '', $amount_raw );
		if ( '' === $amount_raw || ! is_numeric( $amount_raw ) ) {
			return new \WP_Error( 'invalid_amount', __( 'Enter a valid payout amount.', 'logicanvas-auctions' ) );
		}

		$amount = Decimal::round( (string) $amount_raw, 2 );
		if ( Decimal::cmp( $amount, '0', 2 ) <= 0 ) {
			return new \WP_Error( 'invalid_amount', __( 'Payout amount must be greater than zero.', 'logicanvas-auctions' ) );
		}

		$balance = $this->available_balance( $holder_id );
		if ( Decimal::cmp( $amount, $balance['amount'], 2 ) > 0 ) {
			return new \WP_Error(
				'exceeds_balance',
				sprintf(
					/* translators: %s: available balance */
					__( 'Requested amount cannot exceed your available balance of %s.', 'logicanvas-auctions' ),
					$balance['formatted']
				)
			);
		}

		$method  = $this->get_payment_method( $holder_id );
		$now     = gmdate( 'Y-m-d H:i:s' );
		$note    = sanitize_textarea_field( (string) ( $input['note'] ?? '' ) );

		global $wpdb;
		$inserted = $wpdb->insert(
			Config::table( Config::TABLE_PAYOUT_REQUESTS ),
			array(
				'holder_id'        => $holder_id,
				'amount'           => $amount,
				'currency'         => $balance['currency'],
				'status'           => self::STATUS_PENDING,
				'payment_method'   => (string) $method['method'],
				'payment_details'  => wp_json_encode( $method ),
				'holder_note'      => $note,
				'admin_note'       => '',
				'created_at_utc'   => $now,
				'updated_at_utc'   => $now,
			)
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'db_error', __( 'Could not create the payout request.', 'logicanvas-auctions' ) );
		}

		QueryCache::flush_group();

		$id = (int) $wpdb->insert_id;
		( new WpdbAuditRepository() )->write(
			0,
			'payout_requested',
			$holder_id,
			$amount . ' ' . $balance['currency'],
			array( 'request_id' => $id ),
			$now,
			'user',
			'payout-' . $id
		);

		do_action( 'wcap_payout_requested', $id, $holder_id, $amount, $balance['currency'] );

		return $id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function for_holder( int $holder_id, int $limit = 30 ): array {
		global $wpdb;

		$table = Config::table( Config::TABLE_PAYOUT_REQUESTS );
		$rows  = QueryCache::remember(
			QueryCache::key( 'payouts_holder', $holder_id, $limit ),
			60,
			static function () use ( $wpdb, $table, $holder_id, $limit ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE holder_id = %d ORDER BY id DESC LIMIT %d',
						$table,
						$holder_id,
						$limit
					),
					ARRAY_A
				);
			}
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_all( int $limit = 100 ): array {
		global $wpdb;

		$table = Config::table( Config::TABLE_PAYOUT_REQUESTS );
		$rows  = QueryCache::remember(
			QueryCache::key( 'payouts_all', $limit ),
			60,
			static function () use ( $wpdb, $table, $limit ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i ORDER BY FIELD(status, %s, %s, %s, %s, %s), id DESC LIMIT %d',
						$table,
						self::STATUS_PENDING,
						self::STATUS_APPROVED,
						self::STATUS_PAID,
						self::STATUS_REJECTED,
						self::STATUS_CANCELLED,
						$limit
					),
					ARRAY_A
				);
			}
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return true|\WP_Error
	 */
	public function cancel( int $request_id, int $holder_id ) {
		$row = $this->find( $request_id );
		if ( ! $row || (int) $row['holder_id'] !== $holder_id ) {
			return new \WP_Error( 'not_found', __( 'Payout request not found.', 'logicanvas-auctions' ) );
		}
		if ( self::STATUS_PENDING !== (string) $row['status'] ) {
			return new \WP_Error( 'invalid', __( 'Only pending requests can be cancelled.', 'logicanvas-auctions' ) );
		}

		return $this->set_status( $request_id, self::STATUS_CANCELLED, $holder_id, '' );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function set_status( int $request_id, string $status, int $actor_id, string $admin_note = '' ) {
		$allowed = array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PAID, self::STATUS_REJECTED, self::STATUS_CANCELLED );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Invalid payout status.', 'logicanvas-auctions' ) );
		}

		$row = $this->find( $request_id );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Payout request not found.', 'logicanvas-auctions' ) );
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		global $wpdb;
		$wpdb->update(
			Config::table( Config::TABLE_PAYOUT_REQUESTS ),
			array(
				'status'            => $status,
				'admin_note'        => sanitize_textarea_field( $admin_note ),
				'processed_by'      => $actor_id,
				'processed_at_utc'  => $now,
				'updated_at_utc'    => $now,
			),
			array( 'id' => $request_id )
		);

		QueryCache::flush_group();

		if ( self::STATUS_PAID === $status ) {
			$this->allocate_paid_request( $row );
		}

		do_action( 'wcap_payout_request_status_changed', $request_id, $status, $actor_id );

		return true;
	}

	/**
	 * Save / update transaction reference, notes, and optional proof document.
	 *
	 * @param array<string, mixed>      $input Form fields.
	 * @param array<string, mixed>|null $file  Optional $_FILES entry.
	 * @return true|\WP_Error
	 */
	public function save_transaction_proof( int $request_id, int $actor_id, array $input, ?array $file = null, bool $preserve_blank = false ) {
		if ( ! user_can( $actor_id, Config::CAP_MANAGE_SETTLEMENTS ) && ! user_can( $actor_id, 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot update payout proof.', 'logicanvas-auctions' ) );
		}

		$row = $this->find( $request_id );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Payout request not found.', 'logicanvas-auctions' ) );
		}

		$reference = sanitize_text_field( (string) ( $input['transaction_reference'] ?? '' ) );
		$details   = sanitize_textarea_field( (string) ( $input['transaction_details'] ?? '' ) );
		if ( $preserve_blank ) {
			if ( '' === $reference ) {
				$reference = (string) ( $row['transaction_reference'] ?? '' );
			}
			if ( '' === $details ) {
				$details = (string) ( $row['transaction_details'] ?? '' );
			}
		}
		$now       = gmdate( 'Y-m-d H:i:s' );
		$attach_id = (int) ( $row['proof_attachment_id'] ?? 0 );

		$has_upload = is_array( $file ) && ! empty( $file['tmp_name'] ) && (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_NO_FILE;
		if ( $has_upload ) {
			$uploaded = ( new \LogicanvasAuctions\Infrastructure\Media\ProofUploader() )->handle( $file, $actor_id, $request_id );
			if ( is_wp_error( $uploaded ) ) {
				return $uploaded;
			}
			$old       = $attach_id;
			$attach_id = (int) $uploaded['id'];
			if ( $old > 0 && $old !== $attach_id ) {
				wp_delete_attachment( $old, true );
			}
		}

		if ( '' === $reference && '' === $details && $attach_id < 1 ) {
			return new \WP_Error( 'incomplete', __( 'Add a transaction reference, details, or upload a proof document.', 'logicanvas-auctions' ) );
		}

		global $wpdb;
		$wpdb->update(
			Config::table( Config::TABLE_PAYOUT_REQUESTS ),
			array(
				'transaction_reference' => $reference,
				'transaction_details'   => $details,
				'proof_attachment_id'   => $attach_id,
				'updated_at_utc'        => $now,
			),
			array( 'id' => $request_id )
		);

		QueryCache::flush_group();

		( new WpdbAuditRepository() )->write(
			0,
			'payout_proof_updated',
			$actor_id,
			$reference,
			array(
				'request_id'          => $request_id,
				'proof_attachment_id' => $attach_id,
			),
			$now,
			'user',
			'payout-proof-' . $request_id
		);

		do_action( 'wcap_payout_proof_updated', $request_id, $actor_id, $attach_id );

		return true;
	}

	/**
	 * Whether the user may view this request's proof.
	 */
	public function can_view_proof( int $request_id, int $user_id ): bool {
		if ( $user_id < 1 ) {
			return false;
		}
		if ( user_can( $user_id, Config::CAP_MANAGE_SETTLEMENTS ) || user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$row = $this->find( $request_id );
		if ( ! $row ) {
			return false;
		}

		return (int) $row['holder_id'] === $user_id;
	}

	/**
	 * Seller / admin-facing proof summary.
	 *
	 * @return array<string, mixed>|null
	 */
	public function proof_summary( int $request_id ): ?array {
		$row = $this->find( $request_id );
		if ( ! $row ) {
			return null;
		}

		$attach_id = (int) ( $row['proof_attachment_id'] ?? 0 );
		$file_name = '';
		$mime      = '';
		if ( $attach_id > 0 ) {
			$file_name = (string) get_the_title( $attach_id );
			$mime      = (string) get_post_mime_type( $attach_id );
			if ( '' === $file_name ) {
				$path = get_attached_file( $attach_id );
				$file_name = is_string( $path ) ? basename( $path ) : ( 'proof-' . $attach_id );
			}
		}

		return array(
			'transaction_reference' => (string) ( $row['transaction_reference'] ?? '' ),
			'transaction_details'   => (string) ( $row['transaction_details'] ?? '' ),
			'proof_attachment_id'   => $attach_id,
			'proof_file_name'       => $file_name,
			'proof_mime'            => $mime,
			'has_proof'             => $attach_id > 0 || '' !== trim( (string) ( $row['transaction_reference'] ?? '' ) ) || '' !== trim( (string) ( $row['transaction_details'] ?? '' ) ),
			'download_url'          => $attach_id > 0 ? $this->proof_download_url( $request_id ) : '',
		);
	}

	public function proof_download_url( int $request_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=wcap_view_payout_proof&request_id=' . $request_id ),
			'wcap_view_payout_proof_' . $request_id
		);
	}

	/**
	 * Stream the proof attachment to an authorized user.
	 */
	public function stream_proof( int $request_id, int $user_id ): void {
		if ( ! $this->can_view_proof( $request_id, $user_id ) ) {
			wp_die( esc_html__( 'You cannot view this payout proof.', 'logicanvas-auctions' ), 403 );
		}

		$row = $this->find( $request_id );
		$attach_id = $row ? (int) ( $row['proof_attachment_id'] ?? 0 ) : 0;
		if ( $attach_id < 1 ) {
			wp_die( esc_html__( 'No proof document is attached.', 'logicanvas-auctions' ), 404 );
		}

		$path = get_attached_file( $attach_id );
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'Proof document is missing.', 'logicanvas-auctions' ), 404 );
		}

		$mime = (string) ( get_post_mime_type( $attach_id ) ?: 'application/octet-stream' );
		$name = basename( $path );

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $name ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $request_id ): ?array {
		global $wpdb;

		$table = Config::table( Config::TABLE_PAYOUT_REQUESTS );
		$row   = QueryCache::remember(
			QueryCache::key( 'payout', $request_id ),
			60,
			static function () use ( $wpdb, $table, $request_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE id = %d',
						$table,
						$request_id
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Release settlement balance FIFO until the paid request amount is covered.
	 *
	 * @param array<string, mixed> $request Request row.
	 */
	private function allocate_paid_request( array $request ): void {
		$remaining = Decimal::round( (string) $request['amount'], 2 );
		$holder_id = (int) $request['holder_id'];
		if ( Decimal::cmp( $remaining, '0', 2 ) <= 0 ) {
			return;
		}

		global $wpdb;
		$table = Config::table( Config::TABLE_SETTLEMENTS );
		$rows  = QueryCache::remember(
			QueryCache::key( 'settle_alloc', $holder_id ),
			30,
			static function () use ( $wpdb, $table, $holder_id ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, net_amount, released_amount FROM %i WHERE holder_id = %d AND payout_status = %s ORDER BY id ASC',
						$table,
						$holder_id,
						SettlementService::PAYOUT_PENDING
					),
					ARRAY_A
				);
			}
		);
		if ( ! is_array( $rows ) ) {
			return;
		}

		$now       = gmdate( 'Y-m-d H:i:s' );
		$actor_id  = (int) ( $request['processed_by'] ?? 0 );

		foreach ( $rows as $row ) {
			if ( Decimal::cmp( $remaining, '0', 2 ) <= 0 ) {
				break;
			}

			$net      = Decimal::round( (string) $row['net_amount'], 2 );
			$released = Decimal::round( (string) ( $row['released_amount'] ?? '0' ), 2 );
			$left     = Decimal::sub( $net, $released, 2 );
			if ( Decimal::cmp( $left, '0', 2 ) <= 0 ) {
				$wpdb->update(
					$table,
					array(
						'payout_status'  => SettlementService::PAYOUT_PAID,
						'updated_at_utc' => $now,
					),
					array( 'id' => (int) $row['id'] )
				);
				continue;
			}

			$take     = Decimal::cmp( $left, $remaining, 2 ) <= 0 ? $left : $remaining;
			$released = Decimal::add( $released, $take, 2 );
			$remaining = Decimal::sub( $remaining, $take, 2 );
			$data      = array(
				'released_amount' => $released,
				'updated_at_utc'  => $now,
			);
			if ( Decimal::cmp( $released, $net, 2 ) >= 0 ) {
				$data['payout_status'] = SettlementService::PAYOUT_PAID;
			}

			$wpdb->update( $table, $data, array( 'id' => (int) $row['id'] ) );
			do_action( 'wcap_payout_status_changed', (int) $row['id'], $data['payout_status'] ?? SettlementService::PAYOUT_PENDING, $actor_id );
		}

		QueryCache::flush_group();
	}
}
