<?php
/**
 * My Auctions Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class MyAuctionsWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_holder_dashboard';
	}

	protected function widget_title(): string {
		return __( 'My Auctions', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-my-auctions';
	}
}
