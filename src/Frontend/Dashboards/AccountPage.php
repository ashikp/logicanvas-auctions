<?php
/**
 * Composes holder + bidder data and renders one account view.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend\Dashboards;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Frontend\Assets;
use LogicanvasAuctions\Frontend\TemplateLoader;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class AccountPage {

	public function render( string $default_view = AccountRouter::OVERVIEW, string $mode = AccountRouter::MODE_SELLER ): void {
		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( get_permalink() ?: home_url( '/' ) );
			echo '<div class="wcap-login-gate"><p>' . esc_html__( 'Log in to view your account.', 'logicanvas-auctions' ) . ' <a href="' . esc_url( $login ) . '">' . esc_html__( 'Log in', 'logicanvas-auctions' ) . '</a></p></div>';
			return;
		}

		AccountRouter::set_context( $mode );

		$user_id     = get_current_user_id();
		$holder      = ( new HolderDashboard() )->data( $user_id );
		$bidder      = ( new BidderDashboard() )->data( $user_id );
		$can_sell    = ! empty( $holder['can_create'] ) || ! empty( $holder['is_admin'] );
		$show_seller = $can_sell && AccountRouter::MODE_SELLER === $mode;
		$router      = new AccountRouter();
		$view        = $router->current( $default_view, $show_seller );
		$profile     = is_array( $holder['profile'] ?? null ) ? $holder['profile'] : AccountProfile::for_user( $user_id );

		$edit_payload = null;
		$auction_id   = 0;

		if ( AccountRouter::CREATE === $view ) {
			Assets::enqueue_media_library();
		}

		if ( AccountRouter::EDIT === $view ) {
			Assets::enqueue_classic_editor();
			$auction_id   = isset( $_GET['auction_id'] ) ? absint( wp_unslash( (string) $_GET['auction_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$edit_payload = $this->resolve_edit( $auction_id, $user_id );
			if ( ! $edit_payload ) {
				$auction_id = 0;
			}
		}

		$action = null;
		if ( $show_seller && AccountRouter::CREATE !== $view && AccountRouter::EDIT !== $view ) {
			$action = array(
				'label' => __( 'Create listing', 'logicanvas-auctions' ),
				'url'   => AccountRouter::url( AccountRouter::CREATE ),
			);
		} elseif ( ! empty( $profile['urls']['archive'] ) ) {
			$action = array(
				'label' => __( 'Browse auctions', 'logicanvas-auctions' ),
				'url'   => (string) $profile['urls']['archive'],
			);
		}

		TemplateLoader::render(
			'account/shell',
			array(
				'view'        => $view,
				'heading'     => AccountRouter::heading( $view, $profile ),
				'eyebrow'     => AccountRouter::eyebrow( $view ),
				'action'      => $action,
				'holder'      => $holder,
				'bidder'      => $bidder,
				'profile'     => $profile,
				'can_sell'    => $can_sell,
				'show_seller' => $show_seller,
				'is_admin'    => ! empty( $holder['is_admin'] ),
				'addresses'   => $this->addresses( $user_id ),
				'seller_url'  => $can_sell && ! $show_seller ? AccountRouter::url( AccountRouter::OVERVIEW, AccountRouter::MODE_SELLER ) : '',
				'buyer_url'   => $show_seller ? AccountRouter::url( AccountRouter::OVERVIEW, AccountRouter::MODE_BUYER ) : '',
				'edit'        => $edit_payload,
				'auction_id'  => $auction_id,
			)
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function resolve_edit( int $auction_id, int $user_id ): ?array {
		if ( $auction_id < 1 ) {
			return null;
		}

		$auction = ( new WpdbAuctionRepository() )->find( $auction_id );
		if ( ! $auction instanceof Auction ) {
			return null;
		}

		if ( $auction->holder_id() !== $user_id && ! user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return null;
		}

		if ( ! in_array( $auction->state(), array( 'draft', 'rejected', 'pending_review', 'scheduled' ), true ) ) {
			return null;
		}

		$post = get_post( $auction_id );
		if ( ! $post ) {
			return null;
		}

		$featured_id  = (int) get_post_thumbnail_id( $auction_id );
		$gallery_raw  = get_post_meta( $auction_id, '_wcap_gallery', true );
		$gallery_ids  = is_array( $gallery_raw ) ? array_values( array_filter( array_map( 'intval', $gallery_raw ) ) ) : array();
		$previews     = array();
		foreach ( $gallery_ids as $gid ) {
			$url = wp_get_attachment_image_url( $gid, 'thumbnail' ) ?: wp_get_attachment_image_url( $gid, 'medium' );
			if ( $url ) {
				$previews[] = array(
					'id'  => $gid,
					'url' => (string) $url,
				);
			}
		}

		$to_local = static function ( ?string $utc ): string {
			if ( ! $utc ) {
				return '';
			}
			$ts = strtotime( $utc . ' UTC' );
			return $ts ? gmdate( 'Y-m-d\TH:i', $ts ) : '';
		};

		$sku = '';
		if ( $auction->product_id() > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $auction->product_id() );
			if ( $product ) {
				$sku = (string) $product->get_sku();
			}
		}

		$row = $auction->to_array();

		return array(
			'id'                 => $auction_id,
			'title'              => (string) $post->post_title,
			'short_description'  => (string) $post->post_excerpt,
			'description'        => (string) $post->post_content,
			'sku'                => $sku,
			'condition'          => (string) get_post_meta( $auction_id, '_wcap_condition', true ),
			'type'               => $auction->type(),
			'starting_price'     => $auction->starting_amount()->amount(),
			'reserve_price'      => $auction->reserve_amount() ? $auction->reserve_amount()->amount() : '',
			'buy_now'            => $auction->buy_now_amount() ? $auction->buy_now_amount()->amount() : '',
			'min_increment'      => $auction->min_increment()->amount(),
			'start_at'           => $to_local( $auction->start_at_utc() ),
			'end_at'             => $to_local( $auction->end_at_utc() ),
			'visibility'         => $auction->visibility(),
			'fulfilment_type'    => sanitize_key( (string) ( $row['fulfilment_type'] ?? 'shipping' ) ),
			'fulfilment_notes'   => (string) get_post_meta( $auction_id, '_wcap_fulfilment_notes', true ),
			'featured_image_id'  => $featured_id,
			'featured_image_url' => $featured_id ? (string) ( wp_get_attachment_image_url( $featured_id, 'thumbnail' ) ?: '' ) : '',
			'gallery_ids'        => implode( ',', $gallery_ids ),
			'gallery_previews'   => $previews,
			'state'              => $auction->state(),
			'permalink'          => (string) ( get_permalink( $auction_id ) ?: '' ),
			'category_id'        => \LogicanvasAuctions\Support\AuctionCategory::primary_id( $auction_id ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function addresses( int $user_id ): array {
		$billing  = '';
		$shipping = '';
		$edit     = '';

		if ( function_exists( 'wc_get_account_formatted_address' ) ) {
			try {
				$billing  = (string) wc_get_account_formatted_address( 'billing', $user_id );
				$shipping = (string) wc_get_account_formatted_address( 'shipping', $user_id );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		if ( function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_endpoint_url' ) ) {
			$account = wc_get_page_permalink( 'myaccount' );
			if ( $account ) {
				$edit = wc_get_endpoint_url( 'edit-address', '', $account );
			}
		}

		return array(
			'billing'  => $billing,
			'shipping' => $shipping,
			'edit_url' => $edit,
		);
	}
}
