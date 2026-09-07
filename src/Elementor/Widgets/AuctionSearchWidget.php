<?php
/**
 * Auction Search and Filters Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class AuctionSearchWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_auction_grid';
	}

	protected function widget_title(): string {
		return __( 'Auction Search and Filters', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-auction-search';
	}
}
