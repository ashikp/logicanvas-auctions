<?php
/**
 * Bid accepted email class (WooCommerce mailer compatible).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Notifications\Emails;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

final class BidAcceptedEmail extends \WC_Email {

	public function __construct() {
		$this->id          = 'wcap_bid_accepted';
		$this->title       = __( 'Auction bid accepted', 'logicanvas-auctions' );
		$this->description = __( 'Sent when a bid is accepted.', 'logicanvas-auctions' );
		$this->template_html  = 'emails/bid-accepted.php';
		$this->template_plain = 'emails/plain/bid-accepted.php';
		$this->template_base  = \LogicanvasAuctions\Config::plugin_dir() . 'templates/';
		parent::__construct();
	}

	public function trigger( int $user_id, string $message ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}
		$this->recipient = $user->user_email;
		$this->send( $this->get_recipient(), $this->get_subject(), $message, $this->get_headers(), array() );
	}
}
