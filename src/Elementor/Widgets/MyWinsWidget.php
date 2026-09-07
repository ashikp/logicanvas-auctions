<?php
/**
 * My Won Auctions Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class MyWinsWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_my_wins';
	}

	protected function widget_title(): string {
		return __( 'My Won Auctions', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-my-wins';
	}
}
