<?php
/**
 * Bid History Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class BidHistoryWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_bid_history';
	}

	protected function widget_title(): string {
		return __( 'Bid History', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-bid-history';
	}
}
