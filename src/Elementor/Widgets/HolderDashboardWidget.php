<?php
/**
 * Auction Holder Dashboard Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class HolderDashboardWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_holder_dashboard';
	}

	protected function widget_title(): string {
		return __( 'Auction Holder Dashboard', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-holder-dashboard';
	}

	protected function render(): void {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<div class="wcap-root wcap-dash wcap-elementor-placeholder">';
			echo '<h2 class="wcap-dash__title">' . esc_html__( 'Auction holder dashboard', 'logicanvas-auctions' ) . '</h2>';
			echo '<p>' . esc_html__( 'Editor preview. Open this page on the frontend while logged in to see account status, auctions, and settlements.', 'logicanvas-auctions' ) . '</p>';
			echo '</div>';
			return;
		}

		parent::render();
	}
}
