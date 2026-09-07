<?php
/**
 * Periodic WordPress.org review / rating popup (every 2 weeks).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

use LogicanvasAuctions\Config;

final class ReviewPrompt {

	private const USER_META_NEXT   = 'wcap_review_prompt_next';
	private const USER_META_DONE   = 'wcap_review_prompt_done';
	private const INTERVAL_SECONDS = 14 * DAY_IN_SECONDS;
	private const FIRST_DELAY      = 3 * DAY_IN_SECONDS;

	public function register(): void {
		add_action( 'admin_footer', array( $this, 'maybe_render_popup' ) );
		add_action( 'admin_post_wcap_review_prompt', array( $this, 'handle' ) );
		add_action( 'wp_ajax_wcap_review_prompt', array( $this, 'handle_ajax' ) );
	}

	public function maybe_render_popup(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$rate_url = Config::REVIEW_URI;
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'wcap_review_prompt' );
		?>
		<div id="wcap-review-prompt" class="wcap-review-prompt" role="dialog" aria-modal="true" aria-labelledby="wcap-review-prompt-title" hidden>
			<div class="wcap-review-prompt__backdrop" data-wcap-review="later" tabindex="-1"></div>
			<div class="wcap-review-prompt__dialog">
				<button type="button" class="wcap-review-prompt__x" data-wcap-review="later" aria-label="<?php esc_attr_e( 'Close', 'logicanvas-auctions' ); ?>">&times;</button>
				<div class="wcap-review-prompt__badge" aria-hidden="true">★★★★★</div>
				<h2 id="wcap-review-prompt-title"><?php esc_html_e( 'Enjoying Logicanvas Auctions?', 'logicanvas-auctions' ); ?></h2>
				<p><?php esc_html_e( 'If this plugin helps your store run auctions, a quick 5-star review on WordPress.org means a lot and helps other merchants find it.', 'logicanvas-auctions' ); ?></p>
				<div class="wcap-review-prompt__stars" aria-hidden="true">
					<span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
				</div>
				<div class="wcap-review-prompt__actions">
					<a
						class="button button-primary wcap-review-prompt__rate"
						href="<?php echo esc_url( $rate_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						data-wcap-review="rated"
					><?php esc_html_e( 'Rate 5 stars', 'logicanvas-auctions' ); ?></a>
					<button type="button" class="button" data-wcap-review="later"><?php esc_html_e( 'Remind me in 2 weeks', 'logicanvas-auctions' ); ?></button>
					<button type="button" class="button-link wcap-review-prompt__done" data-wcap-review="done"><?php esc_html_e( 'I already left a review', 'logicanvas-auctions' ); ?></button>
				</div>
				<p class="wcap-review-prompt__fine"><?php esc_html_e( 'This reminder appears at most once every two weeks.', 'logicanvas-auctions' ); ?></p>
			</div>
		</div>
		<script>
		(function(){
			var root=document.getElementById('wcap-review-prompt');
			if(!root){return;}
			var ajaxUrl=<?php echo wp_json_encode( $ajax_url ); ?>;
			var nonce=<?php echo wp_json_encode( $nonce ); ?>;
			function close(){root.hidden=true;document.body.classList.remove('wcap-review-prompt-open');}
			function save(choice){
				if(!ajaxUrl||!nonce){return;}
				var body=new FormData();
				body.append('action','wcap_review_prompt');
				body.append('_ajax_nonce',nonce);
				body.append('wcap_review',choice);
				if(window.fetch){
					window.fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:body}).catch(function(){});
				}
			}
			function onChoice(choice){
				save(choice);
				close();
			}
			root.querySelectorAll('[data-wcap-review]').forEach(function(el){
				el.addEventListener('click',function(e){
					var choice=el.getAttribute('data-wcap-review')||'later';
					if(choice==='rated'){
						save('later');
						return;
					}
					e.preventDefault();
					onChoice(choice);
				});
			});
			document.addEventListener('keydown',function(e){
				if(e.key==='Escape'&&!root.hidden){onChoice('later');}
			});
			window.setTimeout(function(){
				root.hidden=false;
				document.body.classList.add('wcap-review-prompt-open');
			},800);
		})();
		</script>
		<?php
		// Prevent duplicate render in same request if footer fires twice.
		remove_action( 'admin_footer', array( $this, 'maybe_render_popup' ) );
	}

	public function handle(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}
		check_admin_referer( 'wcap_review_prompt' );
		$this->apply_choice( sanitize_key( (string) wp_unslash( $_GET['wcap_review'] ?? 'later' ) ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wcap-dashboard' ) );
		exit;
	}

	public function handle_ajax(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'wcap_review_prompt' );
		$this->apply_choice( sanitize_key( (string) wp_unslash( $_POST['wcap_review'] ?? 'later' ) ) );
		wp_send_json_success( array( 'ok' => true ) );
	}

	private function apply_choice( string $choice ): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return;
		}

		if ( 'done' === $choice ) {
			update_user_meta( $user_id, self::USER_META_DONE, '1' );
			update_user_meta( $user_id, self::USER_META_NEXT, (string) ( time() + ( YEAR_IN_SECONDS * 5 ) ) );
			return;
		}

		// Rated / later → ask again in 2 weeks.
		update_user_meta( $user_id, self::USER_META_NEXT, (string) ( time() + self::INTERVAL_SECONDS ) );
	}

	private function should_show(): bool {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			return false;
		}
		if ( ! $this->is_plugin_admin_screen() ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return false;
		}
		if ( '1' === (string) get_user_meta( $user_id, self::USER_META_DONE, true ) ) {
			return false;
		}

		$next = (int) get_user_meta( $user_id, self::USER_META_NEXT, true );
		if ( $next < 1 ) {
			// First visit: schedule first ask after a short delay, do not show immediately.
			update_user_meta( $user_id, self::USER_META_NEXT, (string) ( time() + self::FIRST_DELAY ) );
			return false;
		}

		return time() >= $next;
	}

	private function is_plugin_admin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		if ( Config::CPT === $screen->post_type ) {
			return true;
		}
		$id = (string) $screen->id;
		return str_starts_with( $id, 'toplevel_page_wcap-' )
			|| str_contains( $id, '_page_wcap-' );
	}
}
