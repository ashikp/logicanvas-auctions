<?php
/**
 * Login Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class LoginWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_login';
	}

	protected function widget_title(): string {
		return __( 'Auction Login', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-login';
	}
}
