<?php
/**
 * Dedicated login / register / password reset.
 *
 * Expects auth keys on $wcap (view, notice, notice_type, redirect_to, register_enabled,
 * login_url, register_url, lost_url, rp_login, rp_key).
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_view             = (string) ( $wcap['view'] ?? 'login' );
$wcap_notice           = (string) ( $wcap['notice'] ?? '' );
$wcap_notice_type      = (string) ( $wcap['notice_type'] ?? 'error' );
$wcap_redirect_to      = (string) ( $wcap['redirect_to'] ?? '' );
$wcap_register_enabled = ! empty( $wcap['register_enabled'] );
$wcap_login_url        = (string) ( $wcap['login_url'] ?? '' );
$wcap_register_url     = (string) ( $wcap['register_url'] ?? '' );
$wcap_lost_url         = (string) ( $wcap['lost_url'] ?? '' );
$wcap_rp_login         = (string) ( $wcap['rp_login'] ?? '' );
$wcap_rp_key           = (string) ( $wcap['rp_key'] ?? '' );
$wcap_site             = (string) get_bloginfo( 'name' );

$wcap_titles = array(
	'login'    => __( 'Sign in', 'logicanvas-auctions' ),
	'register' => __( 'Create account', 'logicanvas-auctions' ),
	'lost'     => __( 'Reset password', 'logicanvas-auctions' ),
	'reset'    => __( 'Set a new password', 'logicanvas-auctions' ),
);
$wcap_copy   = array(
	'login'    => __( 'Use your bidder or seller account to bid, watch lots, and manage listings.', 'logicanvas-auctions' ),
	'register' => __( 'Create a free account to bid. Approved auction holders can sell from the same login.', 'logicanvas-auctions' ),
	'lost'     => __( 'Enter the email on your account. If it exists, we will send a reset link.', 'logicanvas-auctions' ),
	'reset'    => __( 'Choose a new password for your auction account.', 'logicanvas-auctions' ),
);
?>
<div class="wcap-auth" data-wcap-ssr="1">
	<div class="wcap-auth-card">
		<p class="wcap-eyebrow"><?php echo esc_html( strtoupper( $wcap_site ) ); ?></p>
		<h1><?php echo esc_html( $wcap_titles[ $wcap_view ] ?? $wcap_titles['login'] ); ?></h1>
		<p class="wcap-auth-lede"><?php echo esc_html( $wcap_copy[ $wcap_view ] ?? $wcap_copy['login'] ); ?></p>

		<?php if ( 'reset' !== $wcap_view ) : ?>
			<nav class="wcap-auth-tabs" aria-label="<?php esc_attr_e( 'Account forms', 'logicanvas-auctions' ); ?>">
				<a class="<?php echo 'login' === $wcap_view ? 'active' : ''; ?>" href="<?php echo esc_url( $wcap_login_url ); ?>"><?php esc_html_e( 'Log in', 'logicanvas-auctions' ); ?></a>
				<?php if ( $wcap_register_enabled ) : ?>
					<a class="<?php echo 'register' === $wcap_view ? 'active' : ''; ?>" href="<?php echo esc_url( $wcap_register_url ); ?>"><?php esc_html_e( 'Create account', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
				<a class="<?php echo 'lost' === $wcap_view ? 'active' : ''; ?>" href="<?php echo esc_url( $wcap_lost_url ); ?>"><?php esc_html_e( 'Forgot password', 'logicanvas-auctions' ); ?></a>
			</nav>
		<?php endif; ?>

		<?php if ( '' !== $wcap_notice ) : ?>
			<p class="wcap-auth-notice wcap-auth-notice--<?php echo 'success' === $wcap_notice_type ? 'success' : 'error'; ?>" role="alert"><?php echo wp_kses_post( $wcap_notice ); ?></p>
		<?php endif; ?>

		<?php if ( 'register' === $wcap_view && $wcap_register_enabled ) : ?>
			<form class="wcap-form lead-form wcap-auth-form" method="post" novalidate>
				<?php wp_nonce_field( 'wcap_auth_register', '_wcap_auth_nonce' ); ?>
				<input type="hidden" name="wcap_auth_action" value="register" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wcap_redirect_to ); ?>" />
				<p class="wcap-hp" hidden><label><?php esc_html_e( 'Leave blank', 'logicanvas-auctions' ); ?><input type="text" name="wcap_hp" value="" tabindex="-1" autocomplete="off" /></label></p>
				<label><?php esc_html_e( 'First name', 'logicanvas-auctions' ); ?>
					<input type="text" name="first_name" autocomplete="given-name" />
				</label>
				<label><?php esc_html_e( 'Last name', 'logicanvas-auctions' ); ?>
					<input type="text" name="last_name" autocomplete="family-name" />
				</label>
				<label class="full"><?php esc_html_e( 'Email', 'logicanvas-auctions' ); ?>
					<input type="email" name="user_email" required autocomplete="email" />
				</label>
				<label><?php esc_html_e( 'Password', 'logicanvas-auctions' ); ?>
					<input type="password" name="user_pass" required minlength="8" autocomplete="new-password" />
				</label>
				<label><?php esc_html_e( 'Confirm password', 'logicanvas-auctions' ); ?>
					<input type="password" name="user_pass_confirm" required minlength="8" autocomplete="new-password" />
				</label>
				<div class="wcap-form-actions">
					<button class="wcap-btn" type="submit"><?php esc_html_e( 'Create account', 'logicanvas-auctions' ); ?></button>
				</div>
			</form>
		<?php elseif ( 'lost' === $wcap_view ) : ?>
			<form class="wcap-form lead-form wcap-auth-form" method="post">
				<?php wp_nonce_field( 'wcap_auth_lost', '_wcap_auth_nonce' ); ?>
				<input type="hidden" name="wcap_auth_action" value="lost" />
				<p class="wcap-hp" hidden><label><?php esc_html_e( 'Leave blank', 'logicanvas-auctions' ); ?><input type="text" name="wcap_hp" value="" tabindex="-1" autocomplete="off" /></label></p>
				<label class="full"><?php esc_html_e( 'Email or username', 'logicanvas-auctions' ); ?>
					<input type="text" name="user_login" required autocomplete="username" />
				</label>
				<div class="wcap-form-actions">
					<button class="wcap-btn" type="submit"><?php esc_html_e( 'Send reset link', 'logicanvas-auctions' ); ?></button>
				</div>
			</form>
		<?php elseif ( 'reset' === $wcap_view ) : ?>
			<form class="wcap-form lead-form wcap-auth-form" method="post">
				<?php wp_nonce_field( 'wcap_auth_reset', '_wcap_auth_nonce' ); ?>
				<input type="hidden" name="wcap_auth_action" value="reset" />
				<input type="hidden" name="rp_login" value="<?php echo esc_attr( $wcap_rp_login ); ?>" />
				<input type="hidden" name="rp_key" value="<?php echo esc_attr( $wcap_rp_key ); ?>" />
				<label><?php esc_html_e( 'New password', 'logicanvas-auctions' ); ?>
					<input type="password" name="pass1" required minlength="8" autocomplete="new-password" />
				</label>
				<label><?php esc_html_e( 'Confirm new password', 'logicanvas-auctions' ); ?>
					<input type="password" name="pass2" required minlength="8" autocomplete="new-password" />
				</label>
				<div class="wcap-form-actions">
					<button class="wcap-btn" type="submit"><?php esc_html_e( 'Update password', 'logicanvas-auctions' ); ?></button>
				</div>
			</form>
		<?php else : ?>
			<form class="wcap-form lead-form wcap-auth-form" method="post">
				<?php wp_nonce_field( 'wcap_auth_login', '_wcap_auth_nonce' ); ?>
				<input type="hidden" name="wcap_auth_action" value="login" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wcap_redirect_to ); ?>" />
				<p class="wcap-hp" hidden><label><?php esc_html_e( 'Leave blank', 'logicanvas-auctions' ); ?><input type="text" name="wcap_hp" value="" tabindex="-1" autocomplete="off" /></label></p>
				<label class="full"><?php esc_html_e( 'Email or username', 'logicanvas-auctions' ); ?>
					<input type="text" name="log" required autocomplete="username" />
				</label>
				<label class="full"><?php esc_html_e( 'Password', 'logicanvas-auctions' ); ?>
					<input type="password" name="pwd" required autocomplete="current-password" />
				</label>
				<label class="wcap-auth-remember"><input type="checkbox" name="rememberme" value="forever" /> <?php esc_html_e( 'Keep me signed in', 'logicanvas-auctions' ); ?></label>
				<div class="wcap-form-actions">
					<button class="wcap-btn" type="submit"><?php esc_html_e( 'Log in', 'logicanvas-auctions' ); ?></button>
				</div>
			</form>
		<?php endif; ?>
	</div>
</div>
