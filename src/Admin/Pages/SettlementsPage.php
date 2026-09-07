<?php
/**
 * Settlements / payout requests — admin UI.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Money\Decimal;
use LogicanvasAuctions\Domain\Settlement\PayoutRequestService;
use LogicanvasAuctions\Domain\Settlement\SettlementService;
use LogicanvasAuctions\Infrastructure\Database\QueryCache;

final class SettlementsPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTLEMENTS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$payouts  = new PayoutRequestService();
		$labels   = PayoutRequestService::method_labels();
		$requests = $payouts->list_all( 100 );

		global $wpdb;
		$table = Config::table( Config::TABLE_SETTLEMENTS );
		$rows  = QueryCache::remember(
			QueryCache::key( 'admin_settlements' ),
			45,
			static function () use ( $wpdb, $table ) {
				wp_cache_get( 'wcap_db', QueryCache::GROUP );
				return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', $table ), ARRAY_A );
			}
		);
		$rows  = is_array( $rows ) ? $rows : array();

		$pending_requests = 0;
		$pending_amount   = '0.00';
		$pending_currency = '';
		foreach ( $requests as $req ) {
			if ( ! in_array( (string) $req['status'], array( PayoutRequestService::STATUS_PENDING, PayoutRequestService::STATUS_APPROVED ), true ) ) {
				continue;
			}
			++$pending_requests;
			$pending_currency = (string) ( $req['currency'] ?: $pending_currency );
			$pending_amount   = Decimal::add( $pending_amount, Decimal::round( (string) $req['amount'], 2 ), 2 );
		}

		$open_settlements = 0;
		foreach ( $rows as $row ) {
			if ( SettlementService::PAYOUT_PENDING === (string) ( $row['payout_status'] ?? '' ) ) {
				++$open_settlements;
			}
		}

		Screen::open(
			__( 'Settlements', 'logicanvas-auctions' ),
			__( 'Review seller payout requests, transfer funds, then mark them paid. The ledger tracks commission and remaining balance.', 'logicanvas-auctions' )
		);

		echo '<div class="wcap-admin__stats">';
		$this->stat( __( 'Open requests', 'logicanvas-auctions' ), (string) $pending_requests, __( 'Pending or approved', 'logicanvas-auctions' ) );
		$this->stat( __( 'Requested total', 'logicanvas-auctions' ), $this->money( $pending_amount, $pending_currency ), __( 'Awaiting transfer', 'logicanvas-auctions' ) );
		$this->stat( __( 'Open settlements', 'logicanvas-auctions' ), (string) $open_settlements, __( 'Not fully paid out', 'logicanvas-auctions' ) );
		$this->stat( __( 'Ledger rows', 'logicanvas-auctions' ), (string) count( $rows ), __( 'Recent settlements', 'logicanvas-auctions' ) );
		echo '</div>';

		Screen::panel_open( 'wcap-settle-panel' );
		echo '<div class="wcap-settle-panel__head">';
		echo '<div><h2>' . esc_html__( 'Payout requests', 'logicanvas-auctions' ) . '</h2>';
		echo '<p>' . esc_html__( 'Sellers request withdrawals against their pending balance. Approve after verifying payment details, then mark paid once funds are sent.', 'logicanvas-auctions' ) . '</p></div>';
		echo '</div>';

		if ( empty( $requests ) ) {
			echo '<div class="wcap-settle-empty">' . esc_html__( 'No payout requests yet.', 'logicanvas-auctions' ) . '</div>';
		} else {
			echo '<div class="wcap-payout-cards">';
			foreach ( $requests as $req ) {
				$this->render_request_card( $req, $labels );
			}
			echo '</div>';
		}
		Screen::panel_close();

		Screen::panel_open( 'wcap-settle-panel' );
		echo '<div class="wcap-settle-panel__head">';
		echo '<div><h2>' . esc_html__( 'Settlement ledger', 'logicanvas-auctions' ) . '</h2>';
		echo '<p>' . esc_html__( 'Per-auction commission and net amounts. Released increases when payout requests are marked paid.', 'logicanvas-auctions' ) . '</p></div>';
		echo '</div>';

		if ( empty( $rows ) ) {
			echo '<div class="wcap-settle-empty">' . esc_html__( 'No settlement records yet.', 'logicanvas-auctions' ) . '</div>';
		} else {
			echo '<div class="wcap-settle-table-wrap">';
			echo '<table class="wcap-settle-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'ID', 'logicanvas-auctions' ) . '</th>';
			echo '<th>' . esc_html__( 'Auction', 'logicanvas-auctions' ) . '</th>';
			echo '<th>' . esc_html__( 'Holder', 'logicanvas-auctions' ) . '</th>';
			echo '<th class="is-num">' . esc_html__( 'Gross', 'logicanvas-auctions' ) . '</th>';
			echo '<th class="is-num">' . esc_html__( 'Commission', 'logicanvas-auctions' ) . '</th>';
			echo '<th class="is-num">' . esc_html__( 'Refunds', 'logicanvas-auctions' ) . '</th>';
			echo '<th class="is-num">' . esc_html__( 'Net', 'logicanvas-auctions' ) . '</th>';
			echo '<th class="is-num">' . esc_html__( 'Released', 'logicanvas-auctions' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'logicanvas-auctions' ) . '</th>';
			echo '<th class="is-actions">' . esc_html__( 'Action', 'logicanvas-auctions' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$status   = (string) ( $row['payout_status'] ?? '' );
				$currency = (string) ( $row['currency'] ?? '' );
				$holder   = get_userdata( (int) ( $row['holder_id'] ?? 0 ) );
				echo '<tr>';
				echo '<td class="is-muted">#' . esc_html( (string) $row['id'] ) . '</td>';
				echo '<td><a href="' . esc_url( get_edit_post_link( (int) $row['auction_id'], 'raw' ) ?: '#' ) . '">#' . esc_html( (string) $row['auction_id'] ) . '</a></td>';
				echo '<td>' . esc_html( $holder ? $holder->user_login : '#' . (string) ( $row['holder_id'] ?? '' ) ) . '</td>';
				echo '<td class="is-num">' . esc_html( $this->money( (string) $row['gross_amount'], '' ) ) . '</td>';
				echo '<td class="is-num">' . esc_html( $this->money( (string) $row['commission_amount'], '' ) ) . '</td>';
				echo '<td class="is-num">' . esc_html( $this->money( (string) $row['refund_amount'], '' ) ) . '</td>';
				echo '<td class="is-num is-strong">' . esc_html( $this->money( (string) $row['net_amount'], $currency ) ) . '</td>';
				echo '<td class="is-num">' . esc_html( $this->money( (string) ( $row['released_amount'] ?? '0' ), '' ) ) . '</td>';
				echo '<td>' . $this->status_badge( $status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td class="is-actions">';
				if ( SettlementService::PAYOUT_PENDING === $status ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wcap-settle-inline-form">';
					wp_nonce_field( 'wcap_admin_action' );
					echo '<input type="hidden" name="action" value="wcap_admin_action" />';
					echo '<input type="hidden" name="wcap_action" value="payout_paid" />';
					echo '<input type="hidden" name="settlement_id" value="' . esc_attr( (string) $row['id'] ) . '" />';
					echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Mark paid', 'logicanvas-auctions' ) . '</button>';
					echo '</form>';
				} else {
					echo '<span class="wcap-settle-dash">—</span>';
				}
				echo '</td></tr>';
			}

			echo '</tbody></table></div>';
		}
		Screen::panel_close();
		Screen::close();
	}

	/**
	 * @param array<string, mixed>  $req    Request row.
	 * @param array<string, string> $labels Method labels.
	 */
	private function render_request_card( array $req, array $labels ): void {
		$id         = (int) $req['id'];
		$holder_id  = (int) $req['holder_id'];
		$user       = get_userdata( $holder_id );
		$name       = $user ? $user->display_name : '#' . $holder_id;
		$login      = $user ? $user->user_login : '';
		$method     = (string) $req['payment_method'];
		$status     = (string) $req['status'];
		$details    = json_decode( (string) ( $req['payment_details'] ?? '' ), true );
		$details    = is_array( $details ) ? $details : array();
		$open       = in_array( $status, array( PayoutRequestService::STATUS_PENDING, PayoutRequestService::STATUS_APPROVED ), true );
		$proof      = ( new PayoutRequestService() )->proof_summary( $id );
		$tx_ref     = (string) ( $req['transaction_reference'] ?? '' );
		$tx_details = (string) ( $req['transaction_details'] ?? '' );

		echo '<article class="wcap-payout-card is-status-' . esc_attr( $status ) . '">';

		echo '<header class="wcap-payout-card__top">';
		echo '<div class="wcap-payout-card__who">';
		echo '<span class="wcap-payout-card__id">#' . esc_html( (string) $id ) . '</span>';
		echo '<strong>' . esc_html( $name ) . '</strong>';
		if ( '' !== $login ) {
			echo '<span class="wcap-payout-card__meta">' . esc_html( $login ) . ' · ID ' . esc_html( (string) $holder_id ) . '</span>';
		}
		echo '</div>';
		echo '<div class="wcap-payout-card__amount">';
		echo '<b>' . esc_html( $this->money( (string) $req['amount'], (string) $req['currency'] ) ) . '</b>';
		echo $this->status_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></header>';

		echo '<div class="wcap-payout-card__body">';
		echo '<div class="wcap-payout-card__method">';
		echo '<span class="wcap-payout-card__label">' . esc_html__( 'Payment method', 'logicanvas-auctions' ) . '</span>';
		echo '<strong class="wcap-payout-card__method-name">' . esc_html( (string) ( $labels[ $method ] ?? $method ) ) . '</strong>';
		echo $this->details_list( $details ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $req['holder_note'] ) ) {
			echo '<p class="wcap-payout-card__note"><span>' . esc_html__( 'Seller note', 'logicanvas-auctions' ) . '</span>' . esc_html( (string) $req['holder_note'] ) . '</p>';
		}
		echo '</div>';

		echo '<div class="wcap-payout-card__side">';
		echo '<span class="wcap-payout-card__label">' . esc_html__( 'Request info', 'logicanvas-auctions' ) . '</span>';
		echo '<dl class="wcap-payout-card__meta-list">';
		echo '<div><dt>' . esc_html__( 'Requested', 'logicanvas-auctions' ) . '</dt><dd>' . esc_html( $this->format_utc( (string) ( $req['created_at_utc'] ?? '' ) ) ) . '</dd></div>';
		if ( ! empty( $req['processed_at_utc'] ) ) {
			echo '<div><dt>' . esc_html__( 'Processed', 'logicanvas-auctions' ) . '</dt><dd>' . esc_html( $this->format_utc( (string) $req['processed_at_utc'] ) ) . '</dd></div>';
		}
		if ( ! empty( $req['admin_note'] ) ) {
			echo '<div><dt>' . esc_html__( 'Admin note', 'logicanvas-auctions' ) . '</dt><dd>' . esc_html( (string) $req['admin_note'] ) . '</dd></div>';
		}
		if ( is_array( $proof ) && ! empty( $proof['has_proof'] ) ) {
			if ( '' !== (string) $proof['transaction_reference'] ) {
				echo '<div><dt>' . esc_html__( 'TX reference', 'logicanvas-auctions' ) . '</dt><dd>' . esc_html( (string) $proof['transaction_reference'] ) . '</dd></div>';
			}
			if ( ! empty( $proof['download_url'] ) ) {
				echo '<div><dt>' . esc_html__( 'Document', 'logicanvas-auctions' ) . '</dt><dd><a href="' . esc_url( (string) $proof['download_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $proof['proof_file_name'] ) . '</a></dd></div>';
			}
		}
		echo '</dl></div></div>';

		echo '<footer class="wcap-payout-card__footer">';
		if ( $open ) {
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wcap-payout-form">';
			wp_nonce_field( 'wcap_admin_action' );
			echo '<input type="hidden" name="action" value="wcap_admin_action" />';
			echo '<input type="hidden" name="payout_request_id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<div class="wcap-payout-form__grid">';
			echo '<div class="wcap-payout-form__field"><label for="wcap-reason-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Admin note (optional)', 'logicanvas-auctions' ) . '</label>';
			echo '<input id="wcap-reason-' . esc_attr( (string) $id ) . '" type="text" name="reason" placeholder="' . esc_attr__( 'Internal note…', 'logicanvas-auctions' ) . '" /></div>';
			$this->proof_fields( $id, $tx_ref, $tx_details, $proof );
			echo '</div>';
			echo '<div class="wcap-payout-card__btns">';
			if ( PayoutRequestService::STATUS_PENDING === $status ) {
				echo '<button type="submit" name="wcap_action" value="payout_request_approve" class="button button-secondary">' . esc_html__( 'Approve', 'logicanvas-auctions' ) . '</button>';
			}
			echo '<button type="submit" name="wcap_action" value="payout_request_paid" class="button button-primary">' . esc_html__( 'Mark paid', 'logicanvas-auctions' ) . '</button>';
			echo '<button type="submit" name="wcap_action" value="payout_request_reject" class="button wcap-btn-danger" onclick="return confirm(\'' . esc_js( __( 'Reject this payout request?', 'logicanvas-auctions' ) ) . '\');">' . esc_html__( 'Reject', 'logicanvas-auctions' ) . '</button>';
			echo '</div></form>';
		} else {
			echo '<div class="wcap-payout-form__intro">';
			echo '<h3>' . esc_html__( 'Transaction proof', 'logicanvas-auctions' ) . '</h3>';
			echo '<p>' . esc_html__( 'Visible to the seller. Add a transfer reference and/or upload a receipt (PDF or image).', 'logicanvas-auctions' ) . '</p>';
			echo '</div>';
			if ( is_array( $proof ) && ! empty( $proof['transaction_details'] ) ) {
				echo '<p class="wcap-payout-proof-line">' . esc_html( (string) $proof['transaction_details'] ) . '</p>';
			} elseif ( ! is_array( $proof ) || empty( $proof['has_proof'] ) ) {
				echo '<p class="wcap-payout-proof-empty">' . esc_html__( 'No transaction proof uploaded yet.', 'logicanvas-auctions' ) . '</p>';
			}
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wcap-payout-form">';
			wp_nonce_field( 'wcap_admin_action' );
			echo '<input type="hidden" name="action" value="wcap_admin_action" />';
			echo '<input type="hidden" name="wcap_action" value="payout_request_proof" />';
			echo '<input type="hidden" name="payout_request_id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<div class="wcap-payout-form__grid">';
			$this->proof_fields( $id, $tx_ref, $tx_details, $proof );
			echo '</div>';
			echo '<div class="wcap-payout-card__btns">';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save / update proof', 'logicanvas-auctions' ) . '</button>';
			echo '</div></form>';
		}
		echo '</footer></article>';
	}

	/**
	 * @param array<string, mixed>|null $proof Proof summary.
	 */
	private function proof_fields( int $id, string $reference, string $details, ?array $proof ): void {
		echo '<div class="wcap-payout-form__field">';
		echo '<label for="wcap-tx-ref-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Transaction reference', 'logicanvas-auctions' ) . '</label>';
		echo '<input id="wcap-tx-ref-' . esc_attr( (string) $id ) . '" type="text" name="transaction_reference" value="' . esc_attr( $reference ) . '" placeholder="' . esc_attr__( 'Bank TRX ID, PayPal ID…', 'logicanvas-auctions' ) . '" />';
		echo '</div>';

		echo '<div class="wcap-payout-form__field wcap-payout-form__field--full">';
		echo '<label for="wcap-tx-details-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Transaction details', 'logicanvas-auctions' ) . '</label>';
		echo '<textarea id="wcap-tx-details-' . esc_attr( (string) $id ) . '" name="transaction_details" rows="3" placeholder="' . esc_attr__( 'Date, bank, notes for the seller…', 'logicanvas-auctions' ) . '">' . esc_textarea( $details ) . '</textarea>';
		echo '</div>';

		echo '<div class="wcap-payout-form__field wcap-payout-form__field--full">';
		echo '<label for="wcap-tx-file-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Proof document', 'logicanvas-auctions' ) . '</label>';
		echo '<input id="wcap-tx-file-' . esc_attr( (string) $id ) . '" type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" />';
		if ( is_array( $proof ) && ! empty( $proof['download_url'] ) ) {
			echo '<p class="wcap-payout-proof-current"><a href="' . esc_url( (string) $proof['download_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Current file', 'logicanvas-auctions' ) . ': ' . esc_html( (string) $proof['proof_file_name'] ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $details Payment method snapshot.
	 */
	private function details_list( array $details ): string {
		$method = (string) ( $details['method'] ?? '' );
		$map    = array();

		if ( 'bank_transfer' === $method ) {
			$map = array(
				__( 'Account name', 'logicanvas-auctions' )   => (string) ( $details['account_name'] ?? '' ),
				__( 'Bank', 'logicanvas-auctions' )           => (string) ( $details['bank_name'] ?? '' ),
				__( 'Account / IBAN', 'logicanvas-auctions' ) => (string) ( $details['account_number'] ?? '' ),
				__( 'Routing', 'logicanvas-auctions' )        => (string) ( $details['routing'] ?? '' ),
			);
		} elseif ( 'paypal' === $method ) {
			$map = array(
				__( 'PayPal email', 'logicanvas-auctions' ) => (string) ( $details['paypal_email'] ?? '' ),
			);
		} else {
			$map = array(
				__( 'Details', 'logicanvas-auctions' ) => (string) ( $details['other_details'] ?? '' ),
			);
		}

		$html = '<dl class="wcap-payout-details">';
		foreach ( $map as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}
		$html .= '</dl>';

		return $html;
	}

	private function status_badge( string $status ): string {
		$labels = array(
			'pending'   => __( 'Pending', 'logicanvas-auctions' ),
			'approved'  => __( 'Approved', 'logicanvas-auctions' ),
			'paid'      => __( 'Paid', 'logicanvas-auctions' ),
			'rejected'  => __( 'Rejected', 'logicanvas-auctions' ),
			'cancelled' => __( 'Cancelled', 'logicanvas-auctions' ),
			'reversed'  => __( 'Reversed', 'logicanvas-auctions' ),
			'disputed'  => __( 'Disputed', 'logicanvas-auctions' ),
		);
		$label = $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
		$key   = sanitize_html_class( $status );

		return '<span class="wcap-status-badge is-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</span>';
	}

	private function money( string $amount, string $currency ): string {
		$formatted = number_format( (float) Decimal::round( $amount, 2 ), 2, '.', ',' );
		if ( '' === $currency ) {
			return $formatted;
		}

		return $formatted . ' ' . $currency;
	}

	private function format_utc( string $utc ): string {
		if ( '' === $utc ) {
			return '—';
		}
		$ts = strtotime( $utc . ' UTC' );
		if ( ! $ts ) {
			return $utc;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	private function stat( string $label, string $value, string $hint ): void {
		echo '<div class="wcap-admin__stat">';
		echo '<span>' . esc_html( $label ) . '</span>';
		echo '<b>' . esc_html( $value ) . '</b>';
		echo '<em class="wcap-admin__stat-hint">' . esc_html( $hint ) . '</em>';
		echo '</div>';
	}
}
