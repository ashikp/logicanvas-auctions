<?php
/**
 * Public/safe auction presenter.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Application;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Domain\Auction\Auction;
use LogicanvasAuctions\Domain\Auction\Visibility;
use LogicanvasAuctions\Domain\Bidding\IncrementCalculator;
use LogicanvasAuctions\Domain\Invitation\AccessService;
use LogicanvasAuctions\Frontend\Dashboards\AccountRouter;
use LogicanvasAuctions\Infrastructure\Scheduler\AuctionScheduler;

final class AuctionPresenter {

	public function public_state( Auction $auction, int $viewer_id = 0, string $invite_token = '' ): array {
		$scheduler = new AuctionScheduler();
		$scheduler->maybe_start_if_due( $auction->id() );
		$scheduler->maybe_close_if_ended( $auction->id() );
		$auction = ( new \LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository() )->find( $auction->id() ) ?? $auction;

		if ( '' === $invite_token && isset( $_GET['invite'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$invite_token = sanitize_text_field( wp_unslash( (string) $_GET['invite'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$invite_token = sanitize_text_field( $invite_token );
		}

		$post    = get_post( $auction->id() );
		$next    = ( new IncrementCalculator() )->minimum_next( $auction );
		$reserve = $auction->reserve_amount();
		$display = (string) ( $auction->to_array()['reserve_display'] ?? 'met_only' );
		$access  = new AccessService();
		$text    = $this->listing_text( $auction->id(), $post );

		$payload = array(
			'id'               => $auction->id(),
			'type'             => $auction->type(),
			'state'            => $auction->state(),
			'visibility'       => $auction->visibility(),
			'title'            => $post ? $post->post_title : '',
			'slug'             => $post ? $post->post_name : '',
			'unique_id'        => \LogicanvasAuctions\Support\AuctionQr::unique_id( $auction->id() ),
			'content'          => \LogicanvasAuctions\Support\ListingContent::format_body( $text['content'] ),
			'excerpt'          => \LogicanvasAuctions\Support\ListingContent::format_excerpt( $text['excerpt'] ),
			'featured_image'   => get_the_post_thumbnail_url( $auction->id(), 'large' ) ?: $this->first_gallery_url( $auction->id(), 'large' ),
			'gallery'          => $this->gallery_urls( $auction->id() ),
			'gallery_items'    => $this->gallery_items( $auction->id() ),
			'holder'           => $this->holder_name( $auction->holder_id() ),
			'currency'         => $auction->currency(),
			'starting_price'   => $auction->starting_amount()->to_rest(),
			'current_price'    => $auction->current_amount()->to_rest(),
			'min_increment'    => $auction->min_increment()->to_rest(),
			'next_min_bid'     => $next->to_rest(),
			'bid_count'        => $auction->bid_count(),
			'sequence'         => $auction->sequence(),
			'start_at_utc'     => $auction->start_at_utc(),
			'end_at_utc'       => $auction->end_at_utc(),
			'server_time_utc'  => gmdate( 'Y-m-d H:i:s' ),
			'server_ts'        => time(),
			'reserve_met'      => $auction->reserve_met(),
			'accepts_bids'     => $auction->accepts_bids(),
			'is_leading'       => $viewer_id > 0 && $auction->current_leader_id() === $viewer_id,
			'can_bid'          => $viewer_id > 0 && ! $auction->is_holder( $viewer_id ) && $auction->accepts_bids(),
			'can_accept_bid'   => $this->can_accept_highest( $auction, $viewer_id ),
			'can_edit'         => $this->can_edit( $auction, $viewer_id ),
			'edit_url'         => $this->edit_listing_url( $auction, $viewer_id ),
			'condition'        => sanitize_text_field( (string) get_post_meta( $auction->id(), '_wcap_condition', true ) ),
			'fulfilment'       => sanitize_key( (string) ( $auction->to_array()['fulfilment_type'] ?? 'shipping' ) ),
			'fulfilment_notes' => sanitize_textarea_field( (string) get_post_meta( $auction->id(), '_wcap_fulfilment_notes', true ) ),
			'category'         => $this->primary_category( $auction->id() ),
			'permalink'        => get_permalink( $auction->id() ),
			'realtime_mode'    => (string) Settings::get()['realtime_mode'],
		);

		if ( 'always' === $display && $reserve ) {
			$payload['reserve_price'] = $reserve->to_rest();
		}

		$award = ( new \LogicanvasAuctions\Domain\Award\AwardService() )->for_auction( $auction->id() );
		$winner_id = 0;
		if ( is_array( $award ) && ! empty( $award['winner_id'] ) ) {
			$winner_id = (int) $award['winner_id'];
		} elseif (
			$auction->current_leader_id()
			&& in_array(
				$auction->state(),
				array( 'payment_pending', 'sold_payment_pending', 'paid', 'completed', 'ended', 'closing' ),
				true
			)
		) {
			$winner_id = (int) $auction->current_leader_id();
		}

		if ( $winner_id > 0 ) {
			$payload['winner_key']  = self::bidder_key( $winner_id );
			$payload['has_winner']  = true;
			$payload['winner_name'] = $this->bidder_display_name( $winner_id );
		} else {
			$payload['winner_key'] = '';
			$payload['has_winner'] = false;
		}

		if ( $award && $viewer_id === (int) $award['winner_id'] ) {
			$payload['award'] = array(
				'id'       => (int) $award['id'],
				'status'   => $award['status'],
				'amount'   => $award['amount'],
				'deadline' => $award['payment_deadline_utc'],
				'pay_url'  => \LogicanvasAuctions\Frontend\WinnerPaymentRedirect::pay_url_for_award( (int) $award['id'] ),
			);
		}

		$payload['accessible'] = $access->can_view( $auction, $viewer_id, $invite_token );

		$share_url = \LogicanvasAuctions\Support\AuctionQr::share_url( $auction->id() );
		if ( Visibility::UNLISTED === $auction->visibility() ) {
			$token = (string) get_post_meta( $auction->id(), '_wcap_unlisted_token_once', true );
			if ( '' !== $token && is_string( $share_url ) && '' !== $share_url ) {
				$share_url = add_query_arg( 'invite', $token, $share_url );
			}
		}
		$payload['qr'] = array(
			'share_url' => $share_url,
			'details'   => \LogicanvasAuctions\Support\AuctionQr::details_text( $auction->id(), $payload ),
		);

		return $payload;
	}

	/**
	 * Opaque public key for a bidder (matches bid leaderboard keys).
	 */
	public static function bidder_key( int $user_id ): string {
		if ( $user_id < 1 ) {
			return '';
		}

		return substr( hash_hmac( 'sha256', (string) $user_id, wp_salt( 'auth' ) ), 0, 16 );
	}

	public function bidder_display_name( int $user_id ): string {
		$mode = (string) Settings::get()['bidder_alias_mode'];
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return __( 'Bidder', 'logicanvas-auctions' );
		}

		$alias = get_user_meta( $user_id, 'wcap_bidder_alias', true );
		if ( is_string( $alias ) && '' !== $alias ) {
			$name = $alias;
		} elseif ( 'first_last_initial' === $mode ) {
			$last = $user->last_name ? substr( $user->last_name, 0, 1 ) . '.' : '';
			$name = trim( $user->first_name . ' ' . $last );
			if ( '' === $name ) {
				$name = $user->display_name;
			}
		} else {
			$name = $user->display_name;
		}

		/**
		 * Filter the public bidder display name.
		 *
		 * @param string $name    Display name.
		 * @param int    $user_id User ID.
		 */
		$name = (string) apply_filters( 'wcap_public_bidder_display_name', $name, $user_id );

		return sanitize_text_field( $name );
	}

	private function holder_name( int $user_id ): string {
		$user = get_user_by( 'id', $user_id );
		return $user ? sanitize_text_field( $user->display_name ) : '';
	}

	private function can_accept_highest( Auction $auction, int $viewer_id ): bool {
		if ( $viewer_id < 1 || ! $auction->is_timed() ) {
			return false;
		}

		if ( $auction->holder_id() !== $viewer_id && ! user_can( $viewer_id, \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ) ) {
			return false;
		}

		if ( ! in_array( $auction->state(), array( 'active', 'paused_admin' ), true ) ) {
			return false;
		}

		return $auction->bid_count() > 0;
	}

	/**
	 * @return array{content:string,excerpt:string}
	 */
	private function listing_text( int $auction_id, $post ): array {
		$content = $post instanceof \WP_Post ? (string) $post->post_content : '';
		$excerpt = $post instanceof \WP_Post ? (string) $post->post_excerpt : '';

		$product_id = (int) get_post_meta( $auction_id, '_wcap_product_id', true );
		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$p_content = (string) $product->get_description();
				$p_excerpt = (string) $product->get_short_description();
				if ( '' === trim( wp_strip_all_tags( $content ) ) && '' !== trim( $p_content ) ) {
					$content = $p_content;
				}
				if ( '' === trim( wp_strip_all_tags( $excerpt ) ) && '' !== trim( $p_excerpt ) ) {
					$excerpt = $p_excerpt;
				}
			}
		}

		return array(
			'content' => $content,
			'excerpt' => $excerpt,
		);
	}

	/**
	 * @return list<array{id:int,full:string,thumb:string,alt:string}>
	 */
	private function gallery_items( int $auction_id ): array {
		$ids = get_post_meta( $auction_id, '_wcap_gallery', true );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$thumb_id = (int) get_post_thumbnail_id( $auction_id );
		if ( $thumb_id > 0 && ! in_array( $thumb_id, array_map( 'intval', $ids ), true ) ) {
			array_unshift( $ids, $thumb_id );
		}

		$items = array();
		$seen  = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id < 1 || isset( $seen[ $id ] ) ) {
				continue;
			}
			$full = wp_get_attachment_image_url( $id, 'full' ) ?: wp_get_attachment_image_url( $id, 'large' );
			if ( ! $full ) {
				continue;
			}
			$seen[ $id ] = true;
			$items[]     = array(
				'id'    => $id,
				'full'  => (string) $full,
				'thumb' => (string) ( wp_get_attachment_image_url( $id, 'medium' ) ?: $full ),
				'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			);
		}

		return $items;
	}

	private function can_edit( Auction $auction, int $viewer_id ): bool {
		if ( $viewer_id < 1 ) {
			return false;
		}
		if ( $auction->holder_id() !== $viewer_id && ! user_can( $viewer_id, \LogicanvasAuctions\Config::CAP_MODERATE_AUCTIONS ) ) {
			return false;
		}
		return in_array(
			$auction->state(),
			array( 'draft', 'rejected', 'pending_review', 'scheduled' ),
			true
		);
	}

	private function edit_listing_url( Auction $auction, int $viewer_id ): string {
		if ( ! $this->can_edit( $auction, $viewer_id ) ) {
			return '';
		}
		return AccountRouter::url(
			AccountRouter::EDIT,
			AccountRouter::MODE_SELLER,
			array( 'auction_id' => $auction->id() )
		);
	}

	/**
	 * @return string[]
	 */
	private function gallery_urls( int $auction_id ): array {
		return array_values(
			array_map(
				static fn( array $item ): string => (string) $item['full'],
				$this->gallery_items( $auction_id )
			)
		);
	}

	private function first_gallery_url( int $auction_id, string $size ): string {
		$items = $this->gallery_items( $auction_id );
		if ( ! $items ) {
			return '';
		}
		if ( 'large' === $size || 'full' === $size ) {
			return (string) $items[0]['full'];
		}
		return (string) $items[0]['thumb'];
	}

	/**
	 * @return array{id:int,name:string,slug:string,url:string}
	 */
	private function primary_category( int $auction_id ): array {
		$empty = array(
			'id'   => 0,
			'name' => '',
			'slug' => '',
			'url'  => '',
		);
		$terms = get_the_terms( $auction_id, \LogicanvasAuctions\Config::TAXONOMY_CAT );
		if ( ! is_array( $terms ) || ! $terms ) {
			return $empty;
		}
		$term = $terms[0];
		$link = get_term_link( $term );
		return array(
			'id'   => (int) $term->term_id,
			'name' => (string) $term->name,
			'slug' => (string) $term->slug,
			'url'  => is_wp_error( $link ) ? '' : (string) $link,
		);
	}
}
