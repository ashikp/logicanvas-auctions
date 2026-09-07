<?php
/**
 * Optional Elementor integration. Loaded only when Elementor is present.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor;

use LogicanvasAuctions\Elementor\Widgets\AuctionDetailsWidget;
use LogicanvasAuctions\Elementor\Widgets\AuctionGalleryWidget;
use LogicanvasAuctions\Elementor\Widgets\AuctionGridWidget;
use LogicanvasAuctions\Elementor\Widgets\AuctionSearchWidget;
use LogicanvasAuctions\Elementor\Widgets\AuctionShareWidget;
use LogicanvasAuctions\Elementor\Widgets\AuctionStatusWidget;
use LogicanvasAuctions\Elementor\Widgets\BidHistoryWidget;
use LogicanvasAuctions\Elementor\Widgets\BidPanelWidget;
use LogicanvasAuctions\Elementor\Widgets\BidderDashboardWidget;
use LogicanvasAuctions\Elementor\Widgets\CountdownWidget;
use LogicanvasAuctions\Elementor\Widgets\CurrentPriceWidget;
use LogicanvasAuctions\Elementor\Widgets\HolderDashboardWidget;
use LogicanvasAuctions\Elementor\Widgets\LiveHostWidget;
use LogicanvasAuctions\Elementor\Widgets\LiveRoomWidget;
use LogicanvasAuctions\Elementor\Widgets\LoginWidget;
use LogicanvasAuctions\Elementor\Widgets\MyAuctionsWidget;
use LogicanvasAuctions\Elementor\Widgets\MyBidsWidget;
use LogicanvasAuctions\Elementor\Widgets\MyWinsWidget;
use LogicanvasAuctions\Elementor\Widgets\SingleAuctionWidget;
use LogicanvasAuctions\Elementor\Widgets\SubmissionFormWidget;

final class Loader {

	public function register(): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this, 'category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( $this, 'tags' ) );
		( new Compatibility() )->register();
	}

	public function category( $elements_manager ): void {
		( new Category() )->register( $elements_manager );
	}

	public function widgets( $widgets_manager ): void {
		$classes = array(
			AuctionGridWidget::class,
			AuctionSearchWidget::class,
			SingleAuctionWidget::class,
			AuctionGalleryWidget::class,
			AuctionDetailsWidget::class,
			CurrentPriceWidget::class,
			CountdownWidget::class,
			BidPanelWidget::class,
			BidHistoryWidget::class,
			LiveRoomWidget::class,
			LiveHostWidget::class,
			HolderDashboardWidget::class,
			BidderDashboardWidget::class,
			SubmissionFormWidget::class,
			LoginWidget::class,
			MyAuctionsWidget::class,
			MyBidsWidget::class,
			MyWinsWidget::class,
			AuctionStatusWidget::class,
			AuctionShareWidget::class,
		);

		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$widgets_manager->register( new $class() );
			}
		}
	}

	public function tags( $dynamic_tags ): void {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
			return;
		}

		$dynamic_tags->register( new DynamicTags\AuctionTag() );
	}
}
