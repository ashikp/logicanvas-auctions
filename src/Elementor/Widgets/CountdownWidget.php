<?php
/**
 * Countdown Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class CountdownWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_countdown';
	}

	protected function widget_title(): string {
		return __( 'Countdown', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-countdown';
	}
}
