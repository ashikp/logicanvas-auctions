<?php
/**
 * Bidder Dashboard Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class BidderDashboardWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_bidder_dashboard';
	}

	protected function widget_title(): string {
		return __( 'Bidder Dashboard', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-bidder-dashboard';
	}
}
