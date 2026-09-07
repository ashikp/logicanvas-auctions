<?php
/**
 * Auction Status Badge Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class AuctionStatusWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_countdown';
	}

	protected function widget_title(): string {
		return __( 'Auction Status Badge', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-status-badge';
	}
}
