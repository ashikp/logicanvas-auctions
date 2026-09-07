<?php
/**
 * Auction dynamic tags (price, state, type, etc.).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\DynamicTags;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

final class AuctionTag extends Tag {

	public function get_name(): string {
		return 'wcap-auction-field';
	}

	public function get_title(): string {
		return __( 'Auction field', 'logicanvas-auctions' );
	}

	public function get_group(): string {
		return 'post';
	}

	public function get_categories(): array {
		return array( Module::TEXT_CATEGORY );
	}

	protected function register_controls(): void {
		$this->add_control(
			'field',
			array(
				'label'   => __( 'Field', 'logicanvas-auctions' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'id'            => __( 'Auction ID', 'logicanvas-auctions' ),
					'current_price' => __( 'Current price', 'logicanvas-auctions' ),
					'starting'      => __( 'Starting price', 'logicanvas-auctions' ),
					'end_time'      => __( 'End time', 'logicanvas-auctions' ),
					'state'         => __( 'Auction state', 'logicanvas-auctions' ),
					'type'          => __( 'Auction type', 'logicanvas-auctions' ),
					'bid_count'     => __( 'Bid count', 'logicanvas-auctions' ),
					'reserve_met'   => __( 'Reserve met', 'logicanvas-auctions' ),
					'holder'        => __( 'Holder name', 'logicanvas-auctions' ),
					'condition'     => __( 'Product condition', 'logicanvas-auctions' ),
				),
				'default' => 'current_price',
			)
		);
	}

	public function render(): void {
		if ( ! is_singular( Config::CPT ) ) {
			return;
		}

		$auction = ( new WpdbAuctionRepository() )->find( get_the_ID() );
		if ( ! $auction ) {
			return;
		}

		$field = $this->get_settings( 'field' );
		$out   = '';
		switch ( $field ) {
			case 'id':
				$out = (string) $auction->id();
				break;
			case 'current_price':
				$out = $auction->current_amount()->formatted();
				break;
			case 'starting':
				$out = $auction->starting_amount()->formatted();
				break;
			case 'end_time':
				$out = (string) $auction->end_at_utc();
				break;
			case 'state':
				$out = $auction->state();
				break;
			case 'type':
				$out = $auction->type();
				break;
			case 'bid_count':
				$out = (string) $auction->bid_count();
				break;
			case 'reserve_met':
				$out = $auction->reserve_met() ? __( 'Yes', 'logicanvas-auctions' ) : __( 'No', 'logicanvas-auctions' );
				break;
			case 'holder':
				$user = get_user_by( 'id', $auction->holder_id() );
				$out  = $user ? $user->display_name : '';
				break;
			case 'condition':
				$out = (string) get_post_meta( $auction->id(), '_wcap_condition', true );
				break;
		}

		echo esc_html( $out );
	}
}
