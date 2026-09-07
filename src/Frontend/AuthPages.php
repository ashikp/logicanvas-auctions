<?php
/**
 * Dedicated frontend login, registration, and password reset.
 *
 * Replaces wp-login.php for customers, bidders, and holders. Core wp-login.php
 * remains available for logout and as an emergency fallback (?wcap_core=1).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Frontend\Dashboards\AccountRouter;
use LogicanvasAuctions\Infrastructure\Database\RateLimiter;
use WP_Error;
use WP_User;

final class AuthPages {

	public const VIEW_LOGIN    = 'login';
	public const VIEW_REGISTER = 'register';
	public const VIEW_LOST     = 'lost';
	public const VIEW_RESET    = 'reset';

	private string $notice = '';

	private string $notice_type = 'error';

	public function register(): void {
		add_shortcode( 'wcap_login', array( $this, 'shortcode' ) );
		add_shortcode( 'wcap_register', array( $this, 'shortcode_register' ) );
		add_action( 'init', array( $this, 'maybe_ensure_page' ), 20 );
		add_action( 'login_init', array( $this, 'redirect_core_login' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle_request' ), 8 );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 10, 2 );
		add_filter( 'register_url', array( $this, 'filter_register_url' ) );
		add_filter( 'logout_redirect', array( $this, 'filter_logout_redirect' ), 10, 3 );
		add_filter( 'retrieve_password_message', array( $this, 'filter_reset_message' ), 10, 4 );
		add_filter( 'document_title_parts', array( $this, 'document_title' ) );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function shortcode( $atts ): string {
		Assets::enqueue_frontend();
		$atts = shortcode_atts( array( 'view' => '' ), is_array( $atts ) ? $atts : array(), 'wcap_login' );

		ob_start();
		TemplateLoader::render(
			'frontend/auth',
			array(
				'view'              => $this->current_view( (string) $atts['view'] ),
				'notice'            => $this->notice,
				'notice_type'       => $this->notice_type,
				'redirect_to'       => $this->requested_redirect(),
				'register_enabled'  => $this->registration_enabled(),
				'login_url'         => PluginPages::login_url( $this->requested_redirect() ),
				'register_url'      => PluginPages::login_url( $this->requested_redirect(), self::VIEW_REGISTER ),
				'lost_url'          => PluginPages::login_url( $this->requested_redirect(), self::VIEW_LOST ),
				'rp_login'          => $this->reset_login(),
				'rp_key'            => $this->reset_key(),
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function shortcode_register( $atts ): string {
		$atts          = is_array( $atts ) ? $atts : array();
		$atts['view']  = self::VIEW_REGISTER;
		return $this->shortcode( $atts );
	}

	public function maybe_ensure_page(): void {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$pages = get_option( Config::OPTION_PAGES, array() );
		if ( ! is_array( $pages ) ) {
			$pages = array();
		}

		$existing_id = (int) ( $pages['login'] ?? 0 );
		if ( $existing_id > 0 && get_post_status( $existing_id ) ) {
			return;
		}

		if ( get_transient( 'wcap_creating_login_page' ) ) {
			return;
		}

		// Atomic claim so concurrent requests do not create duplicate login pages.
		if ( ! \LogicanvasAuctions\Infrastructure\Database\OptionLock::acquire( 'creating_login_page', MINUTE_IN_SECONDS ) ) {
			return;
		}

		set_transient( 'wcap_creating_login_page', '1', MINUTE_IN_SECONDS );

		$page = get_page_by_path( 'login' );
		if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
			$pages['login'] = (int) $page->ID;
			$this->ensure_shortcode( $page );
			update_option( Config::OPTION_PAGES, $pages, false );
			delete_transient( 'wcap_creating_login_page' );
			\LogicanvasAuctions\Infrastructure\Database\OptionLock::release( 'creating_login_page' );
			return;
		}

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Log in', 'logicanvas-auctions' ),
				'post_name'    => 'login',
				'post_content' => '[wcap_login]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$pages['login'] = (int) $id;
			update_option( Config::OPTION_PAGES, $pages, false );
		}

		delete_transient( 'wcap_creating_login_page' );
		\LogicanvasAuctions\Infrastructure\Database\OptionLock::release( 'creating_login_page' );
	}

	public function redirect_core_login(): void {
		if ( ! empty( $_GET['wcap_core'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['interim-login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : self::VIEW_LOGIN; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $action ) {
			$action = self::VIEW_LOGIN;
		}

		$keep = array( 'logout', 'postpass', 'confirmaction' );
		if ( in_array( $action, $keep, true ) ) {
			return;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) && ! in_array( $action, array( 'rp', 'resetpass', 'resetpassword', 'lostpassword', 'retrievepassword', 'register' ), true ) ) {
			return;
		}

		$url = PluginPages::url( 'login' );
		if ( '' === $url ) {
			return;
		}

		$view = self::VIEW_LOGIN;
		if ( in_array( $action, array( 'lostpassword', 'retrievepassword' ), true ) ) {
			$view = self::VIEW_LOST;
		} elseif ( 'register' === $action ) {
			$view = self::VIEW_REGISTER;
		} elseif ( in_array( $action, array( 'rp', 'resetpass', 'resetpassword' ), true ) ) {
			$view = self::VIEW_RESET;
		}

		$url = PluginPages::login_url( $this->requested_redirect(), $view );
		if ( self::VIEW_RESET === $view ) {
			$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( (string) $_GET['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' !== $key ) {
				$url = add_query_arg( 'key', $key, $url );
			}
			if ( '' !== $login ) {
				$url = add_query_arg( 'login', $login, $url );
			}
		}

		wp_safe_redirect( $url );
		exit;
	}

	public function handle_request(): void {
		if ( is_admin() ) {
			return;
		}

		$this->maybe_redirect_account_login();

		if ( $this->is_login_page() && is_user_logged_in() && self::VIEW_RESET !== $this->current_view() && empty( $_GET['reauth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( $this->destination_for( wp_get_current_user() ) );
			exit;
		}

		if ( empty( $_POST['wcap_auth_action'] ) || empty( $_POST['_wcap_auth_nonce'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( (string) $_POST['wcap_auth_action'] ) );
		$nonce  = sanitize_text_field( wp_unslash( (string) $_POST['_wcap_auth_nonce'] ) );
		$map    = array(
			'login'    => 'wcap_auth_login',
			'register' => 'wcap_auth_register',
			'lost'     => 'wcap_auth_lost',
			'reset'    => 'wcap_auth_reset',
		);
		if ( ! isset( $map[ $action ] ) || ! wp_verify_nonce( $nonce, $map[ $action ] ) ) {
			$this->fail( __( 'The request could not be completed. Please try again.', 'logicanvas-auctions' ) );
			return;
		}

		if ( 'login' === $action ) {
			$this->handle_login();
			return;
		}
		if ( 'register' === $action ) {
			$this->handle_register();
			return;
		}
		if ( 'lost' === $action ) {
			$this->handle_lost();
			return;
		}
		if ( 'reset' === $action ) {
			$this->handle_reset();
		}
	}

	/**
	 * @param mixed $login_url Default URL.
	 * @param mixed $redirect Redirect target.
	 * @param mixed $force_reauth Reauth flag.
	 */
	public function filter_login_url( $login_url, $redirect, $force_reauth ): string {
		$url = PluginPages::url( 'login' );
		if ( '' === $url ) {
			return is_string( $login_url ) ? $login_url : '';
		}

		$redirect = is_string( $redirect ) ? $redirect : '';
		$url      = PluginPages::login_url( $redirect );
		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}

		return $url;
	}

	/**
	 * @param mixed $url Default lost-password URL.
	 * @param mixed $redirect Redirect target.
	 */
	public function filter_lostpassword_url( $url, $redirect ): string {
		$custom = PluginPages::url( 'login' );
		if ( '' === $custom ) {
			return is_string( $url ) ? $url : '';
		}

		return PluginPages::login_url( is_string( $redirect ) ? $redirect : '', self::VIEW_LOST );
	}

	/**
	 * @param mixed $url Default registration URL.
	 */
	public function filter_register_url( $url ): string {
		$custom = PluginPages::url( 'login' );
		if ( '' === $custom ) {
			return is_string( $url ) ? $url : '';
		}

		return PluginPages::login_url( '', self::VIEW_REGISTER );
	}

	/**
	 * @param mixed $redirect_to Default logout destination.
	 * @param mixed $requested Requested destination.
	 * @param mixed $user User.
	 */
	public function filter_logout_redirect( $redirect_to, $requested, $user ) {
		unset( $requested, $user );
		$login = PluginPages::url( 'login' );

		return '' !== $login ? $login : $redirect_to;
	}

	/**
	 * @param mixed  $message Email body.
	 * @param mixed  $key Reset key.
	 * @param mixed  $user_login Username.
	 * @param mixed  $user_data User.
	 */
	public function filter_reset_message( $message, $key, $user_login, $user_data ): string {
		unset( $user_data );
		$message    = is_string( $message ) ? $message : '';
		$user_login = is_string( $user_login ) ? $user_login : '';
		$key        = is_string( $key ) ? $key : '';
		$reset      = PluginPages::login_url( '', self::VIEW_RESET );
		if ( '' === $reset || '' === $key ) {
			return $message;
		}

		$reset = add_query_arg(
			array(
				'key'   => $key,
				'login' => rawurlencode( $user_login ),
			),
			$reset
		);

		$core = network_site_url( 'wp-login.php', 'login' );
		$old  = add_query_arg(
			array(
				'action' => 'rp',
				'key'    => $key,
				'login'  => rawurlencode( $user_login ),
			),
			$core
		);

		if ( str_contains( $message, $old ) ) {
			return str_replace( $old, $reset, $message );
		}

		return (string) preg_replace( '#https?://\S*wp-login\.php\S*#', $reset, $message );
	}

	/**
	 * @param mixed $parts Title parts.
	 * @return array<string, string>
	 */
	public function document_title( $parts ): array {
		if ( ! is_array( $parts ) ) {
			$parts = array();
		}

		if ( ! $this->is_login_page() ) {
			return $parts;
		}

		$titles = array(
			self::VIEW_LOGIN    => __( 'Log in', 'logicanvas-auctions' ),
			self::VIEW_REGISTER => __( 'Create account', 'logicanvas-auctions' ),
			self::VIEW_LOST     => __( 'Reset password', 'logicanvas-auctions' ),
			self::VIEW_RESET    => __( 'Set a new password', 'logicanvas-auctions' ),
		);

		$parts['title'] = $titles[ $this->current_view() ] ?? $titles[ self::VIEW_LOGIN ];

		return $parts;
	}

	private function handle_login(): void {
		$wcap_nonce = isset( $_POST['_wcap_auth_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wcap_auth_nonce'] ) ) : '';
		if ( ! $wcap_nonce || ! wp_verify_nonce( $wcap_nonce, 'wcap_auth_login' ) ) {
			$this->fail( __( 'Login could not be completed. Please try again.', 'logicanvas-auctions' ) );
			return;
		}
		$wcap_hp = isset( $_POST['wcap_hp'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['wcap_hp'] ) ) ) : '';
		if ( '' !== $wcap_hp ) {
			$this->fail( __( 'Login could not be completed. Please try again.', 'logicanvas-auctions' ) );
			return;
		}

		if ( ! $this->rate_ok( 'login' ) ) {
			$this->fail( __( 'Too many sign-in attempts. Please wait a few minutes and try again.', 'logicanvas-auctions' ) );
			return;
		}

		$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['log'] ) ) : '';
		$pass  = $this->post_password( 'pwd' );
		if ( '' === $login || '' === $pass ) {
			$this->fail( __( 'Enter your email or username and password.', 'logicanvas-auctions' ) );
			return;
		}

		$remember = (bool) filter_input( INPUT_POST, 'rememberme', FILTER_VALIDATE_BOOLEAN );

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => $remember,
			),
			is_ssl()
		);

		if ( ! $user instanceof WP_User ) {
			$this->fail( __( 'Invalid username or password.', 'logicanvas-auctions' ) );
			return;
		}

		wp_set_current_user( $user->ID );
		wp_safe_redirect( $this->destination_for( $user ) );
		exit;
	}

	private function handle_register(): void {
		if ( ! $this->registration_enabled() ) {
			$this->fail( __( 'Registration is closed.', 'logicanvas-auctions' ) );
			return;
		}

		$wcap_nonce = isset( $_POST['_wcap_auth_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wcap_auth_nonce'] ) ) : '';
		if ( ! $wcap_nonce || ! wp_verify_nonce( $wcap_nonce, 'wcap_auth_register' ) ) {
			$this->fail( __( 'Registration could not be completed. Please try again.', 'logicanvas-auctions' ) );
			return;
		}
		$wcap_hp = isset( $_POST['wcap_hp'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['wcap_hp'] ) ) ) : '';
		if ( '' !== $wcap_hp ) {
			$this->fail( __( 'Registration could not be completed. Please try again.', 'logicanvas-auctions' ) );
			return;
		}

		if ( ! $this->rate_ok( 'register' ) ) {
			$this->fail( __( 'Too many attempts. Please wait a few minutes and try again.', 'logicanvas-auctions' ) );
			return;
		}

		$email    = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['user_email'] ) ) : '';
		$password = $this->post_password( 'user_pass' );
		$confirm  = $this->post_password( 'user_pass_confirm' );
		$first    = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['first_name'] ) ) : '';
		$last     = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['last_name'] ) ) : '';

		if ( ! is_email( $email ) ) {
			$this->fail( __( 'Enter a valid email address.', 'logicanvas-auctions' ) );
			return;
		}
		if ( strlen( $password ) < 8 ) {
			$this->fail( __( 'Use a password of at least 8 characters.', 'logicanvas-auctions' ) );
			return;
		}
		if ( ! hash_equals( $password, $confirm ) ) {
			$this->fail( __( 'Passwords do not match.', 'logicanvas-auctions' ) );
			return;
		}

		$username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $username || username_exists( $username ) ) {
			$username = sanitize_user( current( explode( '@', $email ) ) . wp_generate_password( 4, false, false ), true );
		}

		if ( function_exists( 'wc_create_new_customer' ) ) {
			$created = wc_create_new_customer( $email, $username, $password );
		} else {
			$created = wp_create_user( $username, $password, $email );
		}

		if ( $created instanceof WP_Error ) {
			$this->fail( $created->get_error_message() );
			return;
		}

		$user_id = (int) $created;
		if ( $user_id < 1 ) {
			$this->fail( __( 'The account could not be created.', 'logicanvas-auctions' ) );
			return;
		}

		$display = trim( $first . ' ' . $last );
		wp_update_user(
			array(
				'ID'           => $user_id,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => '' !== $display ? $display : $username,
			)
		);

		$user = get_user_by( 'id', $user_id );
		if ( $user instanceof WP_User ) {
			foreach ( Config::bidder_capabilities() as $cap ) {
				$user->add_cap( $cap );
			}
			if ( get_role( 'customer' ) && ! in_array( 'customer', (array) $user->roles, true ) && ! in_array( Config::BIDDER_ROLE, (array) $user->roles, true ) ) {
				$user->add_role( Config::BIDDER_ROLE );
			}
		}

		wp_set_current_user( 0 );
		$signed_in = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( $signed_in instanceof WP_User ) {
			wp_safe_redirect( $this->destination_for( $signed_in ) );
			exit;
		}

		wp_safe_redirect( AccountRouter::dashboard_for_user( $user_id ) );
		exit;
	}

	private function handle_lost(): void {
		$wcap_nonce = isset( $_POST['_wcap_auth_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wcap_auth_nonce'] ) ) : '';
		if ( ! $wcap_nonce || ! wp_verify_nonce( $wcap_nonce, 'wcap_auth_lost' ) ) {
			$this->fail( __( 'The request could not be completed. Please try again.', 'logicanvas-auctions' ) );
			return;
		}
		$wcap_hp = isset( $_POST['wcap_hp'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['wcap_hp'] ) ) ) : '';
		if ( '' !== $wcap_hp ) {
			$this->fail( __( 'The request could not be completed. Please try again.', 'logicanvas-auctions' ) );
			return;
		}

		if ( ! $this->rate_ok( 'lost' ) ) {
			$this->fail( __( 'Too many attempts. Please wait a few minutes and try again.', 'logicanvas-auctions' ) );
			return;
		}

		$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['user_login'] ) ) : '';
		if ( '' === $login ) {
			$this->fail( __( 'Enter your email or username.', 'logicanvas-auctions' ) );
			return;
		}

		$result = retrieve_password( $login );
		unset( $result );
		$this->succeed( __( 'If that account exists, a reset link has been sent.', 'logicanvas-auctions' ) );
	}

	private function handle_reset(): void {
		$wcap_nonce = isset( $_POST['_wcap_auth_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wcap_auth_nonce'] ) ) : '';
		if ( ! $wcap_nonce || ! wp_verify_nonce( $wcap_nonce, 'wcap_auth_reset' ) ) {
			$this->fail( __( 'The reset form expired. Open the link from your email again.', 'logicanvas-auctions' ) );
			return;
		}

		$login = isset( $_POST['rp_login'] ) ? sanitize_user( wp_unslash( (string) $_POST['rp_login'] ) ) : $this->reset_login();
		$key   = isset( $_POST['rp_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rp_key'] ) ) : $this->reset_key();
		$pass  = $this->post_password( 'pass1' );
		$pass2 = $this->post_password( 'pass2' );

		$user = check_password_reset_key( $key, $login );
		if ( ! $user instanceof WP_User ) {
			$this->fail( __( 'This reset link is invalid or has expired.', 'logicanvas-auctions' ) );
			return;
		}

		if ( strlen( $pass ) < 8 || ! hash_equals( $pass, $pass2 ) ) {
			$this->fail( __( 'Enter matching passwords of at least 8 characters.', 'logicanvas-auctions' ) );
			return;
		}

		reset_password( $user, $pass );
		$this->succeed( __( 'Your password was updated. You can sign in now.', 'logicanvas-auctions' ) );
		$_GET['wcap_auth'] = self::VIEW_LOGIN;
	}

	private function destination_for( WP_User $user ): string {
		$guard     = new AccessGuard();
		$requested = $this->requested_redirect();
		$target    = $guard->login_redirect( $requested, $requested, $user );

		return is_string( $target ) && '' !== $target ? $target : AccountRouter::dashboard_for_user( $user->ID );
	}

	private function maybe_redirect_account_login(): void {
		if ( is_user_logged_in() || $this->is_login_page() ) {
			return;
		}

		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		$login = PluginPages::url( 'login' );
		if ( '' === $login ) {
			return;
		}

		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		$here    = home_url( esc_url_raw( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) );

		$view = self::VIEW_LOGIN;
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' ) ) {
			$view = self::VIEW_LOST;
		} elseif ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'register' ) ) {
			$view = self::VIEW_REGISTER;
		}

		wp_safe_redirect( PluginPages::login_url( is_string( $account ) && '' !== $account ? $account : $here, $view ) );
		exit;
	}

	private function current_view( string $forced = '' ): string {
		$allowed = array( self::VIEW_LOGIN, self::VIEW_REGISTER, self::VIEW_LOST, self::VIEW_RESET );
		if ( in_array( $forced, $allowed, true ) ) {
			return $forced;
		}

		$requested = filter_input( INPUT_GET, 'wcap_auth', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$requested = is_string( $requested ) ? sanitize_key( $requested ) : '';
		if ( in_array( $requested, $allowed, true ) ) {
			return $requested;
		}

		if ( '' !== $this->reset_key() && '' !== $this->reset_login() ) {
			return self::VIEW_RESET;
		}

		return self::VIEW_LOGIN;
	}

	private function requested_redirect(): string {
		$raw = filter_input( INPUT_GET, 'redirect_to', FILTER_UNSAFE_RAW );
		if ( ! is_string( $raw ) || '' === $raw ) {
			$raw = filter_input( INPUT_POST, 'redirect_to', FILTER_UNSAFE_RAW );
		}
		$raw = is_string( $raw ) ? wp_unslash( $raw ) : '';

		return wp_validate_redirect( $raw, '' );
	}

	private function reset_login(): string {
		$login = filter_input( INPUT_GET, 'login', FILTER_UNSAFE_RAW );
		if ( ! is_string( $login ) || '' === $login ) {
			return '';
		}

		return sanitize_user( wp_unslash( $login ) );
	}

	private function reset_key(): string {
		$key = filter_input( INPUT_GET, 'key', FILTER_UNSAFE_RAW );
		if ( ! is_string( $key ) || '' === $key ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $key ) );
	}

	private function is_login_page(): bool {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}

		$page_id = (int) get_queried_object_id();
		$login   = PluginPages::id( 'login' );

		return $login > 0 && $page_id === $login;
	}

	private function registration_enabled(): bool {
		return (bool) apply_filters( 'wcap_allow_frontend_registration', true );
	}


	/**
	 * Read a password from POST.
	 *
	 * Uses wp_strip_all_tags (not sanitize_text_field) so valid password characters are preserved.
	 * Nonce checks happen in the calling handlers before this is used.
	 */
	private function post_password( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		return wp_strip_all_tags( (string) wp_check_invalid_utf8( wp_unslash( (string) $_POST[ $key ] ) ) );
	}

	private function rate_ok( string $action ): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$hash = hash( 'sha256', $ip . wp_salt( 'nonce' ) );

		return ( new RateLimiter() )->hit( 'auth_' . $action . '_' . $hash, 8, 15 * MINUTE_IN_SECONDS );
	}

	private function fail( string $message ): void {
		$this->notice      = $message;
		$this->notice_type = 'error';
	}

	private function succeed( string $message ): void {
		$this->notice      = $message;
		$this->notice_type = 'success';
	}

	private function ensure_shortcode( \WP_Post $page ): void {
		if ( str_contains( (string) $page->post_content, '[wcap_login]' ) ) {
			return;
		}

		$elementor = get_post_meta( $page->ID, '_elementor_data', true );
		if ( is_string( $elementor ) && '' !== $elementor ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => trim( (string) $page->post_content . "\n[wcap_login]" ),
			)
		);
	}
}
