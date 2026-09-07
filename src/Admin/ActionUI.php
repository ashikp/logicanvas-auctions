<?php
/**
 * Shared admin action buttons + native dialog for confirm/reason.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

final class ActionUI {

	private static bool $css_printed    = false;
	private static bool $footer_hooked  = false;

	/**
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
		self::ensure_modal();

		$action          = sanitize_key( (string) ( $args['action'] ?? '' ) );
		$label           = (string) ( $args['label'] ?? '' );
		$icon            = (string) ( $args['icon'] ?? self::default_icon( $action ) );
		$variant         = (string) ( $args['variant'] ?? self::default_variant( $action ) );
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

		$icon_class = preg_replace( '/[^a-z0-9_-]/i', '', $icon ) ?: 'dashicons-admin-generic';
		$variant    = preg_replace( '/[^a-z0-9_-]/i', '', $variant ) ?: 'neutral';

		printf(
			'<button type="button" class="button wcap-act wcap-act--%1$s" data-wcap-act="1" data-action="%2$s" data-title="%3$s" data-label="%4$s" data-need-reason="%5$s" data-reason-required="%6$s" data-reason-label="%7$s" data-confirm="%8$s" data-fields="%9$s"><span class="dashicons %10$s" aria-hidden="true"></span> %11$s</button> ',
			esc_attr( $variant ),
			esc_attr( $action ),
			esc_attr( $title ),
			esc_attr( $label ),
			$need_reason ? '1' : '0',
			$reason_required ? '1' : '0',
			esc_attr( $reason_label ),
			esc_attr( $confirm ),
			esc_attr( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) ?: '{}' ),
			esc_attr( $icon_class ),
			esc_html( $label )
		);
	}

	/**
	 * Icon + text link styled like action buttons (View / Edit).
	 *
	 * @param array{
	 *   url: string,
	 *   label: string,
	 *   icon?: string,
	 *   variant?: string,
	 *   target?: string,
	 *   rel?: string
	 * } $args
	 */
	public static function link( array $args ): void {
		self::ensure_modal();

		$url     = (string) ( $args['url'] ?? '' );
		$label   = (string) ( $args['label'] ?? '' );
		$icon    = preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $args['icon'] ?? 'dashicons-admin-links' ) ) ?: 'dashicons-admin-links';
		$variant = preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $args['variant'] ?? 'neutral' ) ) ?: 'neutral';
		$target  = (string) ( $args['target'] ?? '' );
		$rel     = (string) ( $args['rel'] ?? ( '_blank' === $target ? 'noopener noreferrer' : '' ) );

		if ( '' === $url || '' === $label ) {
			return;
		}

		printf(
			'<a class="button wcap-act wcap-act--%1$s" href="%2$s"%3$s%4$s><span class="dashicons %5$s" aria-hidden="true"></span> %6$s</a> ',
			esc_attr( $variant ),
			esc_url( $url ),
			'' !== $target ? ' target="' . esc_attr( $target ) . '"' : '',
			'' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * Print button CSS immediately; dialog + JS in admin_footer.
	 */
	public static function ensure_modal(): void {
		self::print_css();
		if ( self::$footer_hooked ) {
			return;
		}
		self::$footer_hooked = true;
		add_action( 'admin_footer', array( self::class, 'print_dialog_and_js' ), 5 );
	}

	public static function print_css(): void {
		if ( self::$css_printed ) {
			return;
		}
		self::$css_printed = true;
		?>
		<style id="wcap-act-css">
			.wcap-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;max-width:420px}
			.wcap-actions__empty{color:#94a3b8}
			.wp-core-ui .button.wcap-act,
			.wcap-act.button{
				display:inline-flex !important;
				align-items:center !important;
				gap:5px !important;
				height:auto !important;
				min-height:30px !important;
				padding:4px 10px !important;
				margin:0 2px 2px 0 !important;
				line-height:1.3 !important;
				border-radius:6px !important;
				font-size:12px !important;
				font-weight:600 !important;
				white-space:nowrap !important;
				box-shadow:none !important;
				text-decoration:none !important;
			}
			.wcap-act .dashicons{
				width:16px !important;height:16px !important;font-size:16px !important;line-height:1 !important;
				margin:0 !important;padding:0 !important;position:static !important;vertical-align:middle !important;
			}
			.wp-core-ui .button.wcap-act--success,
			.wcap-act--success{color:#046c4e !important;border-color:#86efac !important;background:#ecfdf5 !important}
			.wp-core-ui .button.wcap-act--danger,
			.wcap-act--danger{color:#b91c1c !important;border-color:#fca5a5 !important;background:#fef2f2 !important}
			.wp-core-ui .button.wcap-act--warn,
			.wcap-act--warn{color:#92400e !important;border-color:#fcd34d !important;background:#fffbeb !important}
			.wp-core-ui .button.wcap-act--neutral,
			.wcap-act--neutral{color:#3730a3 !important;border-color:#a5b4fc !important;background:#eef2ff !important}
			.wcap-state-pill{display:inline-flex;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;text-transform:uppercase}
			#wcap-act-dialog{
				border:0 !important;padding:0 !important;border-radius:14px !important;
				max-width:460px !important;width:calc(100vw - 32px) !important;
				box-shadow:0 25px 60px rgba(15,23,42,.35) !important;background:#fff !important;
			}
			#wcap-act-dialog::backdrop{background:rgba(15,23,42,.55) !important}
			#wcap-act-dialog .wcap-act-dialog__head{
				display:flex !important;justify-content:space-between !important;align-items:flex-start !important;gap:12px !important;
				padding:18px 20px 12px !important;border-bottom:1px solid #e2e8f0 !important;background:#fff !important;
			}
			#wcap-act-dialog .wcap-act-dialog__head h2{margin:0 !important;padding:0 !important;font-size:18px !important;line-height:1.3 !important;color:#0f172a !important}
			#wcap-act-dialog .wcap-act-dialog__x{border:0 !important;background:transparent !important;font-size:22px !important;line-height:1 !important;cursor:pointer !important;color:#64748b !important;padding:0 !important}
			#wcap-act-dialog .wcap-act-dialog__body{padding:16px 20px !important;background:#fff !important}
			#wcap-act-dialog .wcap-act-dialog__confirm{margin:0 0 12px !important;color:#475569 !important;font-size:14px !important;line-height:1.5 !important}
			#wcap-act-dialog .wcap-act-dialog__reason-label{display:block !important;margin:0 0 6px !important;font-size:12px !important;font-weight:700 !important;color:#64748b !important;text-transform:uppercase !important;letter-spacing:.04em !important}
			#wcap-act-dialog textarea{display:block !important;width:100% !important;min-height:100px !important;box-sizing:border-box !important;padding:10px 12px !important;border:1px solid #cbd5e1 !important;border-radius:8px !important;font:400 14px/1.45 inherit !important;color:#0f172a !important;background:#fff !important}
			#wcap-act-dialog .wcap-act-dialog__foot{display:flex !important;justify-content:flex-end !important;gap:8px !important;padding:12px 20px 18px !important;border-top:1px solid #e2e8f0 !important;background:#f8fafc !important}
			#wcap-act-dialog[data-show-reason="0"] .wcap-act-dialog__reason{display:none !important}
			#wcap-act-dialog[data-show-confirm="0"] .wcap-act-dialog__confirm{display:none !important}
		</style>
		<?php
	}

	public static function print_dialog_and_js(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$post_url = admin_url( 'admin-post.php' );
		?>
		<dialog id="wcap-act-dialog" aria-labelledby="wcap-act-dialog-title">
			<form method="post" action="<?php echo esc_url( $post_url ); ?>" id="wcap-act-form">
				<?php wp_nonce_field( 'wcap_admin_action' ); ?>
				<input type="hidden" name="action" value="wcap_admin_action" />
				<input type="hidden" name="wcap_action" value="" id="wcap-act-action" />
				<div id="wcap-act-extra"></div>
				<div class="wcap-act-dialog__head">
					<h2 id="wcap-act-dialog-title"><?php esc_html_e( 'Confirm action', 'logicanvas-auctions' ); ?></h2>
					<button type="button" class="wcap-act-dialog__x" id="wcap-act-close" aria-label="<?php esc_attr_e( 'Close', 'logicanvas-auctions' ); ?>">&times;</button>
				</div>
				<div class="wcap-act-dialog__body">
					<p class="wcap-act-dialog__confirm" id="wcap-act-confirm"></p>
					<div class="wcap-act-dialog__reason" id="wcap-act-reason-wrap">
						<label class="wcap-act-dialog__reason-label" for="wcap-act-reason" id="wcap-act-reason-label"><?php esc_html_e( 'Reason', 'logicanvas-auctions' ); ?></label>
						<textarea id="wcap-act-reason" name="reason" rows="4" placeholder="<?php esc_attr_e( 'Enter a reason…', 'logicanvas-auctions' ); ?>"></textarea>
					</div>
				</div>
				<div class="wcap-act-dialog__foot">
					<button type="button" class="button" id="wcap-act-cancel"><?php esc_html_e( 'Cancel', 'logicanvas-auctions' ); ?></button>
					<button type="submit" class="button button-primary" id="wcap-act-submit"><?php esc_html_e( 'Confirm', 'logicanvas-auctions' ); ?></button>
				</div>
			</form>
		</dialog>
		<script>
		(function(){
			if(window.wcapActBound){return;}
			window.wcapActBound=true;
			function $(id){return document.getElementById(id);}
			function parseFields(raw){
				try{var o=JSON.parse(raw||'{}');return o&&typeof o==='object'?o:{};}
				catch(e){return {};}
			}
			function closeDlg(){
				var d=$('wcap-act-dialog');
				if(!d){return;}
				if(typeof d.close==='function'){d.close();}
				else{d.removeAttribute('open');}
			}
			function openDlg(btn){
				var d=$('wcap-act-dialog');
				if(!d||!btn){return;}
				var action=btn.getAttribute('data-action')||'';
				var title=btn.getAttribute('data-title')||btn.getAttribute('data-label')||'Confirm';
				var label=btn.getAttribute('data-label')||'Confirm';
				var confirmTxt=btn.getAttribute('data-confirm')||'';
				var needReason=btn.getAttribute('data-need-reason')==='1';
				var reasonRequired=btn.getAttribute('data-reason-required')==='1';
				var reasonLabel=btn.getAttribute('data-reason-label')||'Reason';
				var fields=parseFields(btn.getAttribute('data-fields'));
				$('wcap-act-action').value=action;
				$('wcap-act-dialog-title').textContent=title;
				$('wcap-act-submit').textContent=label;
				$('wcap-act-confirm').textContent=confirmTxt;
				$('wcap-act-reason-label').textContent=reasonLabel;
				$('wcap-act-reason').value='';
				$('wcap-act-reason').required=needReason&&reasonRequired;
				d.setAttribute('data-show-confirm',confirmTxt?'1':'0');
				d.setAttribute('data-show-reason',needReason?'1':'0');
				var extra=$('wcap-act-extra');
				extra.innerHTML='';
				Object.keys(fields).forEach(function(key){
					var input=document.createElement('input');
					input.type='hidden';input.name=key;
					input.value=String(fields[key]==null?'':fields[key]);
					extra.appendChild(input);
				});
				if(typeof d.showModal==='function'){d.showModal();}
				else{d.setAttribute('open','open');}
				if(needReason){setTimeout(function(){$('wcap-act-reason').focus();},40);}
			}
			document.addEventListener('click',function(e){
				var t=e.target; if(!t){return;}
				if(t.nodeType===3){t=t.parentElement;}
				var btn=t.closest?t.closest('[data-wcap-act="1"]'):null;
				if(btn){e.preventDefault();e.stopPropagation();openDlg(btn);return;}
				if(t.id==='wcap-act-close'||t.id==='wcap-act-cancel'){e.preventDefault();closeDlg();}
			},true);
			var form=$('wcap-act-form');
			if(form){
				form.addEventListener('submit',function(e){
					var d=$('wcap-act-dialog'); var reason=$('wcap-act-reason');
					if(d&&d.getAttribute('data-show-reason')==='1'&&reason&&reason.required&&!String(reason.value||'').trim()){
						e.preventDefault();reason.focus();
					}
				});
			}
		})();
		</script>
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
