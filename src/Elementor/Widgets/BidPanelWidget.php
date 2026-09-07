<?php
/**
 * Bid Panel Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class BidPanelWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_bid_panel';
	}

	protected function widget_title(): string {
		return __( 'Bid Panel', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-bid-panel';
	}
}
