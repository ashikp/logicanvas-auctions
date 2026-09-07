<?php
/**
 * Single Auction Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class SingleAuctionWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_single_auction';
	}

	protected function widget_title(): string {
		return __( 'Single Auction', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-single-auction';
	}
}
