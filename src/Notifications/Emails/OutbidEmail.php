<?php
/**
 * Outbid email.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Notifications\Emails;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

final class OutbidEmail extends \WC_Email {

	public function __construct() {
		$this->id             = 'wcap_outbid';
		$this->title          = __( 'Auction outbid', 'logicanvas-auctions' );
		$this->description    = __( 'Sent when a bidder is outbid.', 'logicanvas-auctions' );
		$this->template_html  = 'emails/outbid.php';
		$this->template_plain = 'emails/plain/outbid.php';
		$this->template_base  = \LogicanvasAuctions\Config::plugin_dir() . 'templates/';
		parent::__construct();
	}
}
