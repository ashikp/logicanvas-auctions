<?php
/**
 * Shared admin action buttons + reason modal markup.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

final class ActionUI {

	private static bool $modal_hooked = false;

	/**
	 * Icon + text action control. Opens a popup when a reason (or confirm) is required.
	 *
	 * @param array{
	 *   action: string,
	 *   label: string,
	 *   icon?: string,
	 *   variant?: string,
	 *   fields?: array<string, scalar>,
	 *   need_reason?: bool,
	 *   reason_required?: bool,
	 *   reason_label?: string,
	 *   confirm?: string,
	 *   title?: string
	 * } $args
	 */
	public static function button( array $args ): void {
		$action          = sanitize_key( (string) ( $args['action'] ?? '' ) );
		$label           = (string) ( $args['label'] ?? '' );
		$icon            = sanitize_html_class( (string) ( $args['icon'] ?? self::default_icon( $action ) ) );
		$variant         = sanitize_html_class( (string) ( $args['variant'] ?? self::default_variant( $action ) ) );
		$fields          = is_array( $args['fields'] ?? null ) ? $args['fields'] : array();
		$need_reason     = ! empty( $args['need_reason'] );
		$reason_required = array_key_exists( 'reason_required', $args ) ? ! empty( $args['reason_required'] ) : $need_reason;
		$reason_label    = (string) ( $args['reason_label'] ?? __( 'Reason', 'logicanvas-auctions' ) );
		$confirm         = (string) ( $args['confirm'] ?? '' );
		$title           = (string) ( $args['title'] ?? $label );

		$payload = array();
		foreach ( $fields as $key => $value ) {
			$payload[ sanitize_key( (string) $key ) ] = is_scalar( $value ) ? (string) $value : '';
		}

		printf(
			'<button type="button" class="wcap-action-btn wcap-action-btn--%1$s" data-wcap-admin-action data-wcap-action="%2$s" data-title="%3$s" data-label="%4$s" data-need-reason="%5$s" data-reason-required="%6$s" data-reason-label="%7$s" data-confirm="%8$s" data-fields="%9$s"><span class="dashicons %10$s" aria-hidden="true"></span><span class="wcap-action-btn__text">%11$s</span></button>',
			esc_attr( $variant ),
			esc_attr( $action ),
			esc_attr( $title ),
			esc_attr( $label ),
			$need_reason ? '1' : '0',
			$reason_required ? '1' : '0',
			esc_attr( $reason_label ),
			esc_attr( $confirm ),
			esc_attr( wp_json_encode( $payload ) ?: '{}' ),
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * Ensure the shared modal is printed once in admin_footer (on body, not inside page wrap).
	 */
	public static function ensure_modal(): void {
		if ( self::$modal_hooked ) {
			return;
		}
		self::$modal_hooked = true;
		add_action( 'admin_footer', array( self::class, 'render_modal' ), 100 );
	}

	public static function render_modal(): void {
		?>
		<style id="wcap-action-modal-css">
			body.wcap-action-modal-open{overflow:hidden !important}
			#wcap-action-modal.wcap-action-modal{
				position:fixed !important;
				inset:0 !important;
				z-index:1000000 !important;
				display:none !important;
				align-items:center !important;
				justify-content:center !important;
				padding:24px !important;
				margin:0 !important;
				box-sizing:border-box !important;
			}
			#wcap-action-modal.wcap-action-modal.is-open{display:flex !important}
			#wcap-action-modal .wcap-action-modal__backdrop{
				position:absolute !important;
				inset:0 !important;
				background:rgba(15,23,42,.58) !important;
			}
			#wcap-action-modal .wcap-action-modal__dialog{
				position:relative !important;
				z-index:1 !important;
				width:100% !important;
				max-width:460px !important;
				margin:0 !important;
				background:#fff !important;
				border-radius:16px !important;
				box-shadow:0 28px 70px rgba(15,23,42,.35) !important;
				overflow:hidden !important;
			}
			#wcap-action-modal .wcap-action-modal__head{
				display:flex !important;
				align-items:flex-start !important;
				justify-content:space-between !important;
				gap:12px !important;
				padding:18px 20px 12px !important;
				border-bottom:1px solid #e2e8f0 !important;
				background:#fff !important;
			}
			#wcap-action-modal .wcap-action-modal__head h2{
				margin:0 !important;
				padding:0 !important;
				font-size:18px !important;
				line-height:1.3 !important;
				color:#0f172a !important;
			}
			#wcap-action-modal .wcap-action-modal__x{
				border:0 !important;
				background:transparent !important;
				font-size:24px !important;
				line-height:1 !important;
				cursor:pointer !important;
				color:#64748b !important;
				padding:0 2px !important;
				box-shadow:none !important;
			}
			#wcap-action-modal .wcap-action-modal__body{
				padding:16px 20px 8px !important;
				background:#fff !important;
			}
			#wcap-action-modal .wcap-action-modal__confirm{
				margin:0 0 14px !important;
				color:#475569 !important;
				font-size:14px !important;
				line-height:1.5 !important;
			}
			#wcap-action-modal .wcap-action-modal__confirm[hidden],
			#wcap-action-modal .wcap-action-modal__reason[hidden]{display:none !important}
			#wcap-action-modal .wcap-action-modal__reason{
				display:block !important;
				margin:0 0 8px !important;
			}
			#wcap-action-modal .wcap-action-modal__reason-label{
				display:block !important;
				margin:0 0 8px !important;
				font-size:12px !important;
				font-weight:700 !important;
				color:#64748b !important;
				text-transform:uppercase !important;
				letter-spacing:.04em !important;
			}
			#wcap-action-modal .wcap-action-modal__reason textarea{
				display:block !important;
				width:100% !important;
				min-height:110px !important;
				margin:0 !important;
				padding:10px 12px !important;
				border:1px solid #e2e8f0 !important;
				border-radius:10px !important;
				font:400 14px/1.45 inherit !important;
				color:#0f172a !important;
				text-transform:none !important;
				letter-spacing:normal !important;
				resize:vertical !important;
				box-sizing:border-box !important;
				background:#fff !important;
			}
			#wcap-action-modal .wcap-action-modal__foot{
				display:flex !important;
				justify-content:flex-end !important;
				gap:8px !important;
				padding:12px 20px 18px !important;
				border-top:1px solid #e2e8f0 !important;
				background:#f8fafc !important;
			}
		</style>
		<div id="wcap-action-modal" class="wcap-action-modal" aria-hidden="true">
			<div class="wcap-action-modal__backdrop" data-wcap-action-close tabindex="-1"></div>
			<div class="wcap-action-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="wcap-action-modal-title">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcap-action-modal__form">
					<?php wp_nonce_field( 'wcap_admin_action' ); ?>
					<input type="hidden" name="action" value="wcap_admin_action" />
					<input type="hidden" name="wcap_action" value="" data-wcap-action-field />
					<div data-wcap-action-extra></div>
					<header class="wcap-action-modal__head">
						<h2 id="wcap-action-modal-title" data-wcap-action-title><?php esc_html_e( 'Confirm action', 'logicanvas-auctions' ); ?></h2>
						<button type="button" class="wcap-action-modal__x" data-wcap-action-close aria-label="<?php esc_attr_e( 'Close', 'logicanvas-auctions' ); ?>">&times;</button>
					</header>
					<div class="wcap-action-modal__body">
						<p class="wcap-action-modal__confirm" data-wcap-action-confirm hidden></p>
						<div class="wcap-action-modal__reason" data-wcap-action-reason-wrap hidden>
							<label class="wcap-action-modal__reason-label" for="wcap-action-reason-field" data-wcap-action-reason-label><?php esc_html_e( 'Reason', 'logicanvas-auctions' ); ?></label>
							<textarea id="wcap-action-reason-field" name="reason" rows="4" data-wcap-action-reason placeholder="<?php esc_attr_e( 'Enter a reason…', 'logicanvas-auctions' ); ?>"></textarea>
						</div>
					</div>
					<footer class="wcap-action-modal__foot">
						<button type="button" class="button" data-wcap-action-close><?php esc_html_e( 'Cancel', 'logicanvas-auctions' ); ?></button>
						<button type="submit" class="button button-primary" data-wcap-action-submit><?php esc_html_e( 'Confirm', 'logicanvas-auctions' ); ?></button>
					</footer>
				</form>
			</div>
		</div>
		<?php
	}

	private static function default_icon( string $action ): string {
		$map = array(
			'approve_auction' => 'dashicons-yes-alt',
			'approve_holder'  => 'dashicons-yes-alt',
			'reject_auction'  => 'dashicons-dismiss',
			'reject_holder'   => 'dashicons-dismiss',
			'force_close'     => 'dashicons-lock',
			'cancel_auction'  => 'dashicons-no-alt',
			'void_bid'        => 'dashicons-trash',
		);
		return $map[ $action ] ?? 'dashicons-admin-generic';
	}

	private static function default_variant( string $action ): string {
		if ( str_contains( $action, 'approve' ) ) {
			return 'success';
		}
		if ( str_contains( $action, 'reject' ) || str_contains( $action, 'void' ) || str_contains( $action, 'cancel' ) ) {
			return 'danger';
		}
		if ( str_contains( $action, 'force' ) || str_contains( $action, 'close' ) ) {
			return 'warn';
		}
		return 'neutral';
	}
}
