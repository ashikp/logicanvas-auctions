<?php
/**
 * Live Auction Room Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class LiveRoomWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_live_room';
	}

	protected function widget_title(): string {
		return __( 'Live Auction Room', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-live-room';
	}
}
