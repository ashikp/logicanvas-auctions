<?php
/**
 * My Bids Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class MyBidsWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_my_bids';
	}

	protected function widget_title(): string {
		return __( 'My Bids', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-my-bids';
	}
}
