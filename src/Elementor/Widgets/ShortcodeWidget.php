<?php
/**
 * Shared Elementor widget that renders a shortcode. No privileged data in editor preview.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor\Widgets;

use LogicanvasAuctions\Elementor\Category;
use LogicanvasAuctions\Frontend\Assets;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;

abstract class ShortcodeWidget extends Widget_Base {

	abstract protected function shortcode_tag(): string;

	abstract protected function widget_title(): string;

	abstract protected function widget_name_slug(): string;

	public function get_name(): string {
		return $this->widget_name_slug();
	}

	public function get_title(): string {
		return $this->widget_title();
	}

	public function get_icon(): string {
		return 'eicon-products';
	}

	public function get_categories(): array {
		return array( Category::SLUG );
	}

	public function get_style_depends(): array {
		Assets::register_frontend();
		return array( 'wcap-frontend' );
	}

	public function get_script_depends(): array {
		Assets::register_frontend();
		return array( 'wcap-frontend' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'content',
			array(
				'label' => __( 'Content', 'logicanvas-auctions' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'auction_id',
			array(
				'label' => __( 'Auction ID (optional)', 'logicanvas-auctions' ),
				'type'  => Controls_Manager::NUMBER,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style',
			array(
				'label' => __( 'Style', 'logicanvas-auctions' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text color', 'logicanvas-auctions' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => 'color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<div class="wcap-root wcap-elementor-placeholder"><p class="wcap-badge">' . esc_html( $this->get_title() ) . '</p><p>' . esc_html__( 'Auction widget — layout renders on the live page.', 'logicanvas-auctions' ) . '</p></div>';
			return;
		}

		$id  = absint( $this->get_settings_for_display( 'auction_id' ) );
		$tag = sanitize_key( $this->shortcode_tag() );
		$allowed = array(
			'wcap_auction_grid',
			'wcap_single_auction',
			'wcap_live_room',
			'wcap_live_host',
			'wcap_submit_auction',
			'wcap_holder_dashboard',
			'wcap_bidder_dashboard',
			'wcap_my_bids',
			'wcap_my_wins',
			'wcap_pay_award',
			'wcap_holder_apply',
			'wcap_login',
			'wcap_countdown',
			'wcap_bid_panel',
			'wcap_bid_history',
		);
		if ( ! in_array( $tag, $allowed, true ) ) {
			return;
		}

		$att = $id ? ' id="' . $id . '"' : '';
		echo '<div class="wcap-root">';
		echo do_shortcode( '[' . $tag . $att . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	protected function content_template(): void {
		?>
		<div class="wcap-root wcap-elementor-placeholder">
			<p class="wcap-badge"><?php echo esc_html( $this->widget_title() ); ?></p>
			<p><?php esc_html_e( 'Auction widget — layout renders on the live page.', 'logicanvas-auctions' ); ?></p>
		</div>
		<?php
	}
}
