<?php
/**
 * Winner email.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Notifications\Emails;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

final class WinnerEmail extends \WC_Email {

	public function __construct() {
		$this->id             = 'wcap_winner';
		$this->title          = __( 'Auction winner', 'logicanvas-auctions' );
		$this->description    = __( 'Sent to the winning bidder.', 'logicanvas-auctions' );
		$this->template_html  = 'emails/winner.php';
		$this->template_plain = 'emails/plain/winner.php';
		$this->template_base  = \LogicanvasAuctions\Config::plugin_dir() . 'templates/';
		parent::__construct();
	}
}
