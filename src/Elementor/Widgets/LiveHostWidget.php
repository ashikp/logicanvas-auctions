<?php
/**
 * Live Host Console Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class LiveHostWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_live_host';
	}

	protected function widget_title(): string {
		return __( 'Live Host Console', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-live-host';
	}
}
