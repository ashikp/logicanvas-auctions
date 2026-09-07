<?php
/**
 * Keep auction shortcodes visible on Elementor pages and load assets in the editor.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Elementor;

use LogicanvasAuctions\Frontend\Assets;

final class Compatibility {

	public function register(): void {
		add_filter( 'the_content', array( $this, 'restore_empty_builder_content' ), 10 );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue' ) );
	}

	/**
	 * Opening a wizard page in Elementor marks it as a builder document.
	 * An empty canvas then replaces the shortcode and the frontend looks blank/broken.
	 *
	 * @param mixed $content Filtered post content.
	 */
	public function restore_empty_builder_content( $content ): string {
		$content = is_string( $content ) ? $content : (string) $content;

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return $content;
		}

		if ( ! preg_match( '/\[wcap_[a-z0-9_]+/', (string) $post->post_content ) ) {
			return $content;
		}

		$plain = trim( wp_strip_all_tags( $content ) );
		if ( '' !== $plain ) {
			return $content;
		}

		return wp_kses_post( (string) $post->post_content );
	}

	public function enqueue(): void {
		Assets::enqueue_frontend();
	}
}
