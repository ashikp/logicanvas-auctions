<?php
/**
 * Auction lifecycle: create, submit, moderate, transition.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

use LogicanvasAuctions\Admin\Settings;
use LogicanvasAuctions\Config;
use LogicanvasAuctions\Core\ClockInterface;
use LogicanvasAuctions\Core\SystemClock;
use LogicanvasAuctions\Domain\Holder\HolderService;
use LogicanvasAuctions\Domain\Money\Money;
use LogicanvasAuctions\Infrastructure\Database\Transaction;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuditRepository;
use LogicanvasAuctions\Infrastructure\Database\WpdbEventRepository;
use LogicanvasAuctions\Infrastructure\Media\ImageUploader;
use LogicanvasAuctions\Infrastructure\Scheduler\AuctionScheduler;
use LogicanvasAuctions\Infrastructure\WooCommerce\ProductSync;
use WP_Error;

final class AuctionService {

	public function __construct(
		private AuctionRepositoryInterface $auctions = new WpdbAuctionRepository(),
		private WpdbEventRepository $events = new WpdbEventRepository(),
		private WpdbAuditRepository $audit = new WpdbAuditRepository(),
		private TimedStateMachine $timed = new TimedStateMachine(),
		private LiveStateMachine $live = new LiveStateMachine(),
		private ClockInterface $clock = new SystemClock(),
		private AuctionScheduler $scheduler = new AuctionScheduler(),
		private HolderService $holders = new HolderService()
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @return int|WP_Error
	 */
	public function create( int $user_id, array $input ) {
		$sanitized = AuctionInputSanitizer::sanitize( $input, false );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}
		$input = $sanitized;

		if ( ! user_can( $user_id, Config::CAP_CREATE_AUCTIONS ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot create auctions.', 'logicanvas-auctions' ) );
		}

		if ( ! $this->holders->is_approved( $user_id ) && ! user_can( $user_id, 'manage_options' ) ) {
			return new WP_Error( 'holder_pending', __( 'Your auction holder account is not approved.', 'logicanvas-auctions' ) );
		}

		$type = (string) ( $input['type'] ?? AuctionType::TIMED );
		if ( ! AuctionType::is_valid( $type ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid auction type.', 'logicanvas-auctions' ) );
		}

		$title       = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$source      = sanitize_key( (string) ( $input['product_source'] ?? 'new' ) );
		$existing_id = (int) ( $input['product_id'] ?? 0 );
		$product     = null;

		if ( 'existing' === $source && ! user_can( $user_id, 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'Only administrators can auction an existing WooCommerce product.', 'logicanvas-auctions' ) );
		}

		if ( 'existing' === $source ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $existing_id ) : null;
			if ( ! $product ) {
				return new WP_Error( 'product_required', __( 'Select an existing WooCommerce product, or create a new one.', 'logicanvas-auctions' ) );
			}
			if ( '' === $title ) {
				$title = sanitize_text_field( $product->get_name() );
			}
			if ( empty( $input['description'] ) ) {
				$input['description'] = AuctionInputSanitizer::safe_html( $product->get_description() );
			}
			if ( empty( $input['short_description'] ) ) {
				$input['short_description'] = AuctionInputSanitizer::plain_textarea( $product->get_short_description(), 2000 );
			}
			if ( empty( $input['starting_price'] ) && $product->get_regular_price() ) {
				$price = AuctionInputSanitizer::money_string( (string) $product->get_regular_price() );
				if ( ! is_wp_error( $price ) ) {
					$input['starting_price'] = $price;
				}
			}
			if ( empty( $input['sku'] ) ) {
				$input['sku'] = AuctionInputSanitizer::plain_text( (string) $product->get_sku(), 100 );
			}
			if ( empty( $input['tax_class'] ) ) {
				$input['tax_class'] = sanitize_text_field( (string) $product->get_tax_class() );
			}
		}

		$title = AuctionInputSanitizer::plain_text( (string) $title, 200 );
		if ( '' === $title ) {
			return new WP_Error( 'title', __( 'Title is required (or select an existing product).', 'logicanvas-auctions' ) );
		}

		$settings = Settings::get();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		$now      = $this->clock->utc_mysql();

		$post_id = wp_insert_post(
			array(
				'post_type'    => Config::CPT,
				'post_title'   => $title,
				'post_content' => (string) ( $input['description'] ?? '' ),
				'post_excerpt' => (string) ( $input['short_description'] ?? '' ),
				'post_status'  => 'draft',
				'post_author'  => $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$start = $this->parse_utc( (string) ( $input['start_at'] ?? $now ) );
		$end   = isset( $input['end_at'] ) ? $this->parse_utc( (string) $input['end_at'] ) : gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) + (int) $settings['timed_default_duration'] );
		if ( AuctionType::LIVE === $type ) {
			$end = isset( $input['end_at'] ) ? $this->parse_utc( (string) $input['end_at'] ) : null;
		}

		try {
			$starting = Money::from_string( (string) ( $input['starting_price'] ?? '0.00' ), $currency );
		} catch ( \InvalidArgumentException $e ) {
			wp_delete_post( (int) $post_id, true );
			return new WP_Error( 'starting_price', __( 'Enter a valid starting price.', 'logicanvas-auctions' ) );
		}

		$linked = $this->link_product( (int) $post_id, $user_id, $input, $title, $starting );
		if ( is_wp_error( $linked ) ) {
			wp_delete_post( (int) $post_id, true );
			return $linked;
		}
		$product_id = $linked;

		$unlisted_hash = null;
		$visibility    = (string) ( $input['visibility'] ?? Visibility::PUBLIC_LISTED );
		if ( ! Visibility::is_valid( $visibility ) ) {
			$visibility = Visibility::PUBLIC_LISTED;
		}
		if ( Visibility::UNLISTED === $visibility ) {
			$token         = bin2hex( random_bytes( 32 ) );
			$unlisted_hash = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
			update_post_meta( (int) $post_id, '_wcap_unlisted_token_once', $token );
		}

		$this->auctions->insert_state(
			array(
				'auction_id'              => (int) $post_id,
				'holder_id'               => $user_id,
				'product_id'              => $product_id,
				'type'                    => $type,
				'visibility'              => $visibility,
				'state'                   => AuctionState::DRAFT,
				'currency'                => $currency,
				'starting_amount'         => $starting->amount(),
				'reserve_amount'          => isset( $input['reserve_price'] ) && '' !== $input['reserve_price'] ? Money::from_string( (string) $input['reserve_price'], $currency )->amount() : null,
				'reserve_display'         => sanitize_text_field( (string) ( $input['reserve_display'] ?? $settings['reserve_display'] ) ),
				'current_amount'          => $starting->amount(),
				'min_increment'           => Money::from_string( (string) ( $input['min_increment'] ?? $settings['min_increment'] ), $currency )->amount(),
				'increment_strategy'      => sanitize_text_field( (string) ( $input['increment_strategy'] ?? 'fixed' ) ),
				'buy_now_amount'          => isset( $input['buy_now'] ) && '' !== $input['buy_now'] ? Money::from_string( (string) $input['buy_now'], $currency )->amount() : null,
				'current_leader_id'       => null,
				'bid_count'               => 0,
				'sequence'                => 0,
				'start_at_utc'            => $start,
				'end_at_utc'              => $end,
				'original_end_at_utc'     => $end,
				'extension_count'         => 0,
				'soft_close_window'       => (int) $settings['soft_close_window'],
				'soft_close_extend'       => (int) $settings['soft_close_extend'],
				'soft_close_max'          => (int) $settings['soft_close_max_extensions'],
				'payment_deadline_hours'  => (int) ( $input['payment_deadline_hours'] ?? $settings['payment_deadline_hours'] ),
				'quantity'                => 1,
				'tax_class'               => sanitize_text_field( (string) ( $input['tax_class'] ?? '' ) ),
				'shipping_class'          => sanitize_text_field( (string) ( $input['shipping_class'] ?? '' ) ),
				'fulfilment_type'         => sanitize_key( (string) ( $input['fulfilment_type'] ?? 'shipping' ) ),
				'timezone'                => sanitize_text_field( (string) ( $input['timezone'] ?? wp_timezone_string() ) ),
				'proxy_enabled'           => ! empty( $input['proxy_enabled'] ) && ! empty( $settings['proxy_bidding_enabled'] ) ? 1 : 0,
				'terms_version'           => (string) $settings['terms_version'],
				'unlisted_token_hash'     => $unlisted_hash,
				'created_at_utc'          => $now,
				'updated_at_utc'          => $now,
			)
		);

		update_post_meta( (int) $post_id, 'wcap_product_id', $product_id );
		update_post_meta( (int) $post_id, '_wcap_condition', sanitize_text_field( (string) ( $input['condition'] ?? '' ) ) );
		update_post_meta( (int) $post_id, '_wcap_fulfilment_notes', sanitize_textarea_field( (string) ( $input['fulfilment_notes'] ?? '' ) ) );

		if ( array_key_exists( 'category_id', $input ) ) {
			\LogicanvasAuctions\Support\AuctionCategory::assign( (int) $post_id, (int) $input['category_id'] );
		} else {
			\LogicanvasAuctions\Support\AuctionCategory::assign( (int) $post_id, 0 );
		}

		$this->apply_media( (int) $post_id, $product_id, $user_id, $input );

		/**
		 * Fires after an auction is created.
		 *
		 * @param int $auction_id Auction post ID.
		 * @param int $user_id    Holder.
		 */
		do_action( 'wcap_auction_created', (int) $post_id, $user_id );

		return (int) $post_id;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return true|WP_Error
	 */
	public function update_draft( int $auction_id, int $user_id, array $input ) {
		$sanitized = AuctionInputSanitizer::sanitize( $input, false );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}
		$input = $sanitized;

		$auction = $this->auctions->find( $auction_id );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ) );
		}

		if ( $auction->holder_id() !== $user_id && ! user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit this auction.', 'logicanvas-auctions' ) );
		}

		if ( AuctionState::DRAFT !== $auction->state() && AuctionState::REJECTED !== $auction->state() && AuctionState::PENDING_REVIEW !== $auction->state() && AuctionState::SCHEDULED !== $auction->state() ) {
			return new WP_Error( 'locked', __( 'This auction can no longer be edited from the listing form.', 'logicanvas-auctions' ) );
		}

		$post = array( 'ID' => $auction_id );
		if ( isset( $input['title'] ) ) {
			$post['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['description'] ) ) {
			$post['post_content'] = (string) $input['description'];
		}
		if ( isset( $input['short_description'] ) ) {
			$post['post_excerpt'] = (string) $input['short_description'];
		}
		wp_update_post( $post );

		if ( isset( $input['condition'] ) ) {
			update_post_meta( $auction_id, '_wcap_condition', sanitize_text_field( (string) $input['condition'] ) );
		}
		if ( isset( $input['fulfilment_notes'] ) ) {
			update_post_meta( $auction_id, '_wcap_fulfilment_notes', sanitize_textarea_field( (string) $input['fulfilment_notes'] ) );
		}

		if ( array_key_exists( 'category_id', $input ) ) {
			\LogicanvasAuctions\Support\AuctionCategory::assign( $auction_id, (int) $input['category_id'] );
		}

		$featured = (int) ( $input['featured_image_id'] ?? 0 );
		if ( $featured > 0 ) {
			$uploader = new ImageUploader();
			if ( $uploader->user_can_use( $featured, $user_id ) ) {
				set_post_thumbnail( $auction_id, $featured );
			}
		}

		if ( isset( $input['gallery_ids'] ) ) {
			$raw = $input['gallery_ids'];
			if ( is_string( $raw ) ) {
				$raw = preg_split( '/[\s,]+/', $raw ) ?: array();
			}
			$gallery = ( new ImageUploader() )->sanitize_ids( $raw, $user_id );
			update_post_meta( $auction_id, '_wcap_gallery', $gallery );
		}

		$patch = array( 'updated_at_utc' => $this->clock->utc_mysql() );
		$lock_prices = AuctionState::SCHEDULED === $auction->state() && $auction->bid_count() > 0;

		if ( ! $lock_prices ) {
			foreach ( array( 'starting_price' => 'starting_amount', 'min_increment' => 'min_increment' ) as $in => $col ) {
				if ( isset( $input[ $in ] ) && '' !== (string) $input[ $in ] ) {
					$patch[ $col ] = Money::from_string( (string) $input[ $in ], $auction->currency() )->amount();
				}
			}
			if ( isset( $input['starting_price'] ) && isset( $patch['starting_amount'] ) && $auction->bid_count() < 1 ) {
				$patch['current_amount'] = $patch['starting_amount'];
			}
			if ( isset( $input['reserve_price'] ) ) {
				$reserve = trim( (string) $input['reserve_price'] );
				$patch['reserve_amount'] = '' === $reserve ? null : Money::from_string( $reserve, $auction->currency() )->amount();
			}
		}

		if ( isset( $input['start_at'] ) || isset( $input['start_at_utc'] ) ) {
			$start = sanitize_text_field( (string) ( $input['start_at_utc'] ?? $input['start_at'] ?? '' ) );
			if ( '' !== $start ) {
				$patch['start_at_utc'] = gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) ?: time() );
			}
		}
		if ( isset( $input['end_at'] ) || isset( $input['end_at_utc'] ) ) {
			$end = sanitize_text_field( (string) ( $input['end_at_utc'] ?? $input['end_at'] ?? '' ) );
			$patch['end_at_utc'] = '' === $end ? null : gmdate( 'Y-m-d H:i:s', strtotime( $end . ' UTC' ) ?: time() );
		}
		if ( isset( $input['visibility'] ) && Visibility::is_valid( (string) $input['visibility'] ) ) {
			$patch['visibility'] = sanitize_key( (string) $input['visibility'] );
		}
		if ( isset( $input['fulfilment'] ) || isset( $input['fulfilment_type'] ) ) {
			$patch['fulfilment_type'] = sanitize_key( (string) ( $input['fulfilment_type'] ?? $input['fulfilment'] ?? 'shipping' ) );
		}

		$this->auctions->update_state( $auction_id, $patch );

		$product_id = (int) get_post_meta( $auction_id, 'wcap_product_id', true );
		if ( $product_id < 1 ) {
			$product_id = $auction->product_id();
		}
		$source = (string) get_post_meta( $auction_id, '_wcap_product_source', true );
		$post   = get_post( $auction_id );
		$sync   = new ProductSync();

		if ( $product_id > 0 && 'existing' !== $source && $post ) {
			$sync->create_or_update_product(
				$auction_id,
				array(
					'title'       => (string) $post->post_title,
					'description' => (string) $post->post_content,
					'short'       => (string) $post->post_excerpt,
					'price'       => isset( $patch['starting_amount'] ) ? (string) $patch['starting_amount'] : $auction->starting_amount()->amount(),
					'sku'         => sanitize_text_field( (string) ( $input['sku'] ?? '' ) ),
					'fulfilment'  => sanitize_text_field( (string) ( $patch['fulfilment_type'] ?? $auction->to_array()['fulfilment_type'] ?? 'shipping' ) ),
				)
			);
		}

		$featured_id = (int) get_post_thumbnail_id( $auction_id );
		$gallery     = get_post_meta( $auction_id, '_wcap_gallery', true );
		$gallery     = is_array( $gallery ) ? array_values( array_filter( array_map( 'intval', $gallery ) ) ) : array();
		if ( $product_id > 0 && ( $featured_id > 0 || $gallery ) ) {
			$sync->sync_images( $product_id, $featured_id, $gallery );
		}

		do_action( 'wcap_auction_updated', $auction_id, $user_id );

		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	public function submit( int $auction_id, int $user_id ) {
		return $this->transition( $auction_id, AuctionState::PENDING_REVIEW, $user_id, 'user', 'Submitted for review' );
	}

	/**
	 * @return true|WP_Error
	 */
	public function approve( int $auction_id, int $user_id ) {
		if ( ! user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot moderate auctions.', 'logicanvas-auctions' ) );
		}

		$auction = $this->auctions->find( $auction_id );
		if ( ! $auction ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ) );
		}

		// Admin create/edit often leaves auctions in draft; submit first so approve can run.
		if ( AuctionState::DRAFT === $auction->state() || AuctionState::REJECTED === $auction->state() ) {
			$submitted = $this->transition( $auction_id, AuctionState::PENDING_REVIEW, $user_id, 'user', 'Submitted for approval' );
			if ( is_wp_error( $submitted ) ) {
				return $submitted;
			}
		}

		$result = $this->transition( $auction_id, AuctionState::SCHEDULED, $user_id, 'user', 'Approved' );
		if ( true === $result ) {
			$auction = $this->auctions->find( $auction_id );
			if ( $auction ) {
				wp_update_post(
					array(
						'ID'          => $auction_id,
						'post_status' => 'publish',
					)
				);
				$this->scheduler->schedule_start( $auction_id, $auction->start_at_utc() );
				if ( $auction->is_timed() && $auction->end_at_utc() ) {
					$this->scheduler->schedule_close( $auction_id, $auction->end_at_utc() );
				}
				// If start time is already past (common when approving late), activate now.
				$this->start_if_due( $auction_id );
				do_action( 'wcap_auction_approved', $auction_id );
			}
		}

		return $result;
	}

	/**
	 * @return true|WP_Error
	 */
	public function reject( int $auction_id, int $user_id, string $reason ) {
		if ( ! user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot moderate auctions.', 'logicanvas-auctions' ) );
		}

		return $this->transition( $auction_id, AuctionState::REJECTED, $user_id, 'user', $reason );
	}

	/**
	 * @return true|WP_Error
	 */
	public function transition( int $auction_id, string $to, int $actor_id, string $actor_type, string $reason, ?string $correlation = null ) {
		try {
			Transaction::run(
				function () use ( $auction_id, $to, $actor_id, $actor_type, $reason, $correlation ) {
					$auction = $this->auctions->find_for_update( $auction_id );
					if ( ! $auction ) {
						throw new InvalidTransitionException( 'Auction not found.' );
					}

					$from = $auction->state();
					if ( $from === $to ) {
						return true;
					}

					if ( $auction->is_timed() ) {
						$this->timed->assert( $from, $to );
					} else {
						$this->live->assert( $from, $to );
					}

					$now      = $this->clock->utc_mysql();
					$sequence = $auction->sequence() + 1;

					$this->auctions->update_state(
						$auction_id,
						array(
							'state'          => $to,
							'sequence'       => $sequence,
							'updated_at_utc' => $now,
						)
					);

					$this->events->append(
						$auction_id,
						$sequence,
						'state_changed',
						$actor_id,
						$actor_type,
						array(
							'from'   => $from,
							'to'     => $to,
							'reason' => $reason,
						),
						$now,
						$correlation
					);

					$this->audit->write( $auction_id, 'state_changed', $actor_id, $reason, array( 'from' => $from, 'to' => $to ), $now, $actor_type, $correlation );

					/**
					 * Fires after a committed state change.
					 *
					 * @param int                  $auction_id Auction ID.
					 * @param string               $from       Previous state.
					 * @param string               $to         New state.
					 * @param array<string, mixed> $meta       Metadata.
					 */
					do_action(
						'wcap_auction_state_changed',
						$auction_id,
						$from,
						$to,
						array(
							'actor_id'   => $actor_id,
							'actor_type' => $actor_type,
							'reason'     => $reason,
						)
					);

					return true;
				}
			);
		} catch ( InvalidTransitionException $e ) {
			return new WP_Error( 'invalid_transition', $e->getMessage() );
		}

		return true;
	}

	public function start_if_due( int $auction_id ): void {
		$auction = $this->auctions->find( $auction_id );
		if ( ! $auction || AuctionState::SCHEDULED !== $auction->state() ) {
			return;
		}

		if ( $this->clock->timestamp() < strtotime( $auction->start_at_utc() . ' UTC' ) ) {
			return;
		}

		$to = $auction->is_live() ? AuctionState::LOBBY : AuctionState::ACTIVE;
		$this->transition( $auction_id, $to, 0, 'system', 'Scheduled start' );
		do_action( 'wcap_auction_started', $auction_id );
	}

	/**
	 * Persist auction settings from the wp-admin editor.
	 *
	 * @param array<string, mixed> $input
	 * @return true|WP_Error
	 */
	public function sync_from_editor( int $post_id, int $user_id, array $input ) {
		$post = get_post( $post_id );
		if ( ! $post || Config::CPT !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Auction not found.', 'logicanvas-auctions' ) );
		}

		$existing = $this->auctions->find( $post_id );
		if ( $existing ) {
			if ( $existing->holder_id() !== $user_id && ! user_can( $user_id, Config::CAP_MODERATE_AUCTIONS ) ) {
				return new WP_Error( 'forbidden', __( 'You cannot edit this auction.', 'logicanvas-auctions' ) );
			}
			// Match frontend update_draft: allow edits while draft/rejected/pending/scheduled (no bids yet for scheduled price locks).
			if ( ! in_array(
				$existing->state(),
				array( AuctionState::DRAFT, AuctionState::REJECTED, AuctionState::PENDING_REVIEW, AuctionState::SCHEDULED ),
				true
			) ) {
				return true;
			}
		}

		$title    = $post->post_title;
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		$now      = $this->clock->utc_mysql();
		$settings = Settings::get();

		try {
			$starting = Money::from_string( (string) ( $input['starting_price'] ?? '0.00' ), $currency );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'starting_price', __( 'Enter a valid starting price.', 'logicanvas-auctions' ) );
		}

		$linked = $this->link_product( $post_id, $user_id, $input, $title, $starting );
		if ( is_wp_error( $linked ) ) {
			return $linked;
		}

		$thumb = (int) get_post_thumbnail_id( $post_id );
		$this->apply_media(
			$post_id,
			(int) $linked,
			$user_id,
			array(
				'featured_image_id' => $thumb ?: ( $input['featured_image_id'] ?? 0 ),
				'gallery_ids'       => $input['gallery_ids'] ?? get_post_meta( $post_id, '_wcap_gallery', true ),
			)
		);

		$type = (string) ( $input['type'] ?? AuctionType::TIMED );
		if ( ! AuctionType::is_valid( $type ) ) {
			$type = AuctionType::TIMED;
		}

		$start = $this->parse_utc( (string) ( $input['start_at'] ?? $now ) );
		$end   = isset( $input['end_at'] ) && '' !== $input['end_at'] ? $this->parse_utc( (string) $input['end_at'] ) : gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) + (int) $settings['timed_default_duration'] );
		if ( AuctionType::LIVE === $type && empty( $input['end_at'] ) ) {
			$end = null;
		}

		$visibility = (string) ( $input['visibility'] ?? Visibility::PUBLIC_LISTED );
		if ( ! Visibility::is_valid( $visibility ) ) {
			$visibility = Visibility::PUBLIC_LISTED;
		}

		$buy_now = null;
		if ( isset( $input['buy_now'] ) && '' !== (string) $input['buy_now'] ) {
			try {
				$buy_now = Money::from_string( (string) $input['buy_now'], $currency )->amount();
			} catch ( \InvalidArgumentException $e ) {
				$buy_now = null;
			}
		}

		$fulfilment = sanitize_key( (string) ( $input['fulfilment_type'] ?? $input['fulfilment'] ?? 'shipping' ) );
		if ( '' === $fulfilment ) {
			$fulfilment = 'shipping';
		}

		$row = array(
			'holder_id'               => $existing ? $existing->holder_id() : $user_id,
			'product_id'              => $linked,
			'type'                    => $type,
			'visibility'              => $visibility,
			'currency'                => $currency,
			'starting_amount'         => $starting->amount(),
			'current_amount'          => $existing && $existing->bid_count() > 0 ? $existing->current_amount()->amount() : $starting->amount(),
			'min_increment'           => Money::from_string( (string) ( $input['min_increment'] ?? $settings['min_increment'] ), $currency )->amount(),
			'reserve_amount'          => isset( $input['reserve_price'] ) && '' !== $input['reserve_price'] ? Money::from_string( (string) $input['reserve_price'], $currency )->amount() : null,
			'buy_now_amount'          => $buy_now,
			'start_at_utc'            => $start,
			'end_at_utc'              => $end,
			'original_end_at_utc'     => $end,
			'payment_deadline_hours'  => (int) ( $input['payment_deadline_hours'] ?? $settings['payment_deadline_hours'] ),
			'tax_class'               => sanitize_text_field( (string) ( $input['tax_class'] ?? '' ) ),
			'shipping_class'          => sanitize_text_field( (string) ( $input['shipping_class'] ?? '' ) ),
			'fulfilment_type'        => $fulfilment,
			'updated_at_utc'          => $now,
		);

		if ( isset( $input['condition'] ) ) {
			update_post_meta( $post_id, '_wcap_condition', sanitize_text_field( (string) $input['condition'] ) );
		}
		if ( isset( $input['fulfilment_notes'] ) ) {
			update_post_meta( $post_id, '_wcap_fulfilment_notes', sanitize_textarea_field( (string) $input['fulfilment_notes'] ) );
		}
		update_post_meta( $post_id, '_wcap_fulfilment_type', $fulfilment );

		if ( $existing ) {
			$this->auctions->update_state( $post_id, $row );
			return true;
		}

		$row['auction_id']             = $post_id;
		$row['state']                  = AuctionState::DRAFT;
		$row['reserve_display']        = (string) $settings['reserve_display'];
		$row['increment_strategy']     = 'fixed';
		$row['current_leader_id']      = null;
		$row['bid_count']              = 0;
		$row['sequence']               = 0;
		$row['extension_count']        = 0;
		$row['soft_close_window']      = (int) $settings['soft_close_window'];
		$row['soft_close_extend']      = (int) $settings['soft_close_extend'];
		$row['soft_close_max']         = (int) $settings['soft_close_max_extensions'];
		$row['quantity']               = 1;
		$row['fulfilment_type']        = $fulfilment;
		$row['timezone']               = wp_timezone_string();
		$row['proxy_enabled']          = 0;
		$row['terms_version']          = (string) $settings['terms_version'];
		$row['created_at_utc']         = $now;
		$this->auctions->insert_state( $row );

		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return int|WP_Error
	 */
	private function link_product( int $post_id, int $user_id, array $input, string $title, Money $starting ) {
		$sync   = new ProductSync();
		$source = sanitize_key( (string) ( $input['product_source'] ?? 'new' ) );

		if ( 'existing' === $source ) {
			if ( ! user_can( $user_id, 'manage_options' ) ) {
				return new WP_Error( 'forbidden', __( 'Only administrators can auction an existing WooCommerce product.', 'logicanvas-auctions' ) );
			}

			return $sync->attach_existing( $post_id, (int) ( $input['product_id'] ?? 0 ), $user_id );
		}

		$id = $sync->create_or_update_product(
			$post_id,
			array(
				'title'          => $title,
				'description'    => (string) ( $input['description'] ?? '' ),
				'short'          => (string) ( $input['short_description'] ?? '' ),
				'price'          => $starting->amount(),
				'sku'            => sanitize_text_field( (string) ( $input['sku'] ?? '' ) ),
				'tax_class'      => sanitize_text_field( (string) ( $input['tax_class'] ?? '' ) ),
				'shipping_class' => sanitize_text_field( (string) ( $input['shipping_class'] ?? '' ) ),
				'fulfilment'     => sanitize_text_field( (string) ( $input['fulfilment_type'] ?? '' ) ),
			)
		);

		if ( $id < 1 ) {
			return new WP_Error( 'product_create', __( 'Could not create a WooCommerce product for this auction.', 'logicanvas-auctions' ) );
		}

		return $id;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function apply_media( int $auction_id, int $product_id, int $user_id, array $input ): void {
		$uploader = new ImageUploader();
		$featured = $uploader->sanitize_ids( array( $input['featured_image_id'] ?? 0 ), $user_id, 1 );
		$gallery  = $uploader->sanitize_ids( $input['gallery_ids'] ?? array(), $user_id );

		if ( $featured ) {
			set_post_thumbnail( $auction_id, $featured[0] );
		} elseif ( $product_id ) {
			$from_product = (int) get_post_thumbnail_id( $product_id );
			if ( $from_product ) {
				set_post_thumbnail( $auction_id, $from_product );
				$featured = array( $from_product );
			}
		}

		if ( ! $gallery && $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$gallery = array_map( 'intval', $product->get_gallery_image_ids() );
			}
		}

		if ( $gallery ) {
			update_post_meta( $auction_id, '_wcap_gallery', $gallery );
		}

		$image_id = $featured[0] ?? ( $gallery[0] ?? 0 );
		if ( $product_id && ( $image_id || $gallery ) ) {
			( new ProductSync() )->sync_images( $product_id, (int) $image_id, $gallery );
		}
	}

	private function parse_utc( string $value ): string {
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : $this->clock->utc_mysql();
	}
}
