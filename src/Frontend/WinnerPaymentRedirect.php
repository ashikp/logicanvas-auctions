<?php
/**
 * Send winners from auction/product pages to the payment page.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Award\AwardService;

final class WinnerPaymentRedirect {

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 25 );
	}

	public function maybe_redirect(): void {
		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( $this->is_pay_page() ) {
			return;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		$auction_id = $this->resolve_auction_id();
		if ( $auction_id < 1 ) {
			return;
		}

		$award = ( new AwardService() )->for_auction( $auction_id );
		if ( ! is_array( $award ) ) {
			return;
		}

		if ( (int) ( $award['winner_id'] ?? 0 ) !== get_current_user_id() ) {
			return;
		}

		if ( AwardService::PENDING !== (string) ( $award['status'] ?? '' ) ) {
			return;
		}

		$url = self::pay_url_for_award( (int) $award['id'] );
		if ( '' === $url ) {
			return;
		}

		/**
		 * Filter whether to auto-redirect a winner to the pay page.
		 *
		 * @param bool $redirect   Whether to redirect.
		 * @param int  $auction_id Auction ID.
		 * @param int  $award_id   Award ID.
		 */
		if ( ! apply_filters( 'wcap_redirect_winner_to_pay', true, $auction_id, (int) $award['id'] ) ) {
			return;
		}

		wp_safe_redirect( $url );
		exit;
	}

	public static function pay_url_for_award( int $award_id ): string {
		if ( $award_id < 1 ) {
			return '';
		}

		$url = PluginPages::url( 'pay', array( 'award_id' => $award_id ) );
		if ( '' !== $url ) {
			return $url;
		}

		$purchases = \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url(
			\LogicanvasAuctions\Frontend\Dashboards\AccountRouter::PURCHASES,
			\LogicanvasAuctions\Frontend\Dashboards\AccountRouter::MODE_BUYER
		);

		return is_string( $purchases ) ? $purchases : '';
	}

	private function resolve_auction_id(): int {
		if ( is_singular( Config::CPT ) ) {
			return (int) get_queried_object_id();
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = (int) get_queried_object_id();
			$auction_id = (int) get_post_meta( $product_id, '_wcap_auction_id', true );
			if ( $auction_id > 0 ) {
				return $auction_id;
			}
		}

		return 0;
	}

	private function is_pay_page(): bool {
		$page_id = PluginPages::id( 'pay' );
		if ( $page_id < 1 ) {
			return false;
		}

		return is_page( $page_id );
	}
}
