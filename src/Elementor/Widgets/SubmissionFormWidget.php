<?php
/**
 * Auction Submission Form Elementor widget.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

final class SubmissionFormWidget extends ShortcodeWidget {

	protected function shortcode_tag(): string {
		return 'wcap_submit_auction';
	}

	protected function widget_title(): string {
		return __( 'Auction Submission Form', 'logicanvas-auctions' );
	}

	protected function widget_name_slug(): string {
		return 'wcap-submission-form';
	}
}
