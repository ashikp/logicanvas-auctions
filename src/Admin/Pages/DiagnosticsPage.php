<?php
/**
 * Diagnostics.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin\Pages;

use LogicanvasAuctions\Admin\Screen;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Support\Diagnostics;

final class DiagnosticsPage {

	public function render(): void {
		if ( ! current_user_can( Config::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Forbidden.', 'logicanvas-auctions' ) );
		}

		$report = ( new Diagnostics() )->report();
		Screen::open(
			__( 'Diagnostics', 'logicanvas-auctions' ),
			__( 'Environment checks for WooCommerce, HPOS, scheduler jobs, and database tables.', 'logicanvas-auctions' )
		);
		Screen::panel_open();
		echo '<table class="widefat striped">';
		foreach ( $report as $key => $value ) {
			echo '<tr><th>' . esc_html( (string) $key ) . '</th><td>' . esc_html( is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : (string) $value ) . '</td></tr>';
		}
		echo '</table>';
		Screen::panel_close();
		Screen::close();
	}
}
