<?php
/**
 * SEO / Yoast compatibility for auction CPT singles.
 *
 * Ensures each auction listing is a real public post Yoast can index,
 * with usable titles, descriptions, and Open Graph images.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Support;

use LogicanvasAuctions\Config;

final class SeoCompat {

	public function register(): void {
		add_filter( 'wpseo_accessible_post_types', array( $this, 'accessible_post_types' ) );
		add_filter( 'wpseo_sitemap_exclude_post_type', array( $this, 'keep_in_sitemap' ), 10, 2 );
		add_filter( 'wpseo_opengraph_image', array( $this, 'opengraph_image' ), 10, 1 );
		add_filter( 'wpseo_twitter_image', array( $this, 'opengraph_image' ), 10, 1 );
		add_filter( 'wpseo_metadesc', array( $this, 'metadesc' ) );
		add_filter( 'wpseo_title', array( $this, 'title' ) );
		add_filter( 'document_title_parts', array( $this, 'document_title_parts' ) );
		add_action( 'template_redirect', array( $this, 'canonical_singular' ), 1 );
	}

	/**
	 * @param mixed $types Post types.
	 * @return mixed
	 */
	public function accessible_post_types( $types ) {
		if ( ! is_array( $types ) ) {
			$types = array();
		}
		$types[ Config::CPT ] = Config::CPT;
		return $types;
	}

	/**
	 * @param mixed  $exclude Whether excluded.
	 * @param string $post_type Post type.
	 * @return mixed
	 */
	public function keep_in_sitemap( $exclude, $post_type ) {
		if ( Config::CPT === $post_type ) {
			return false;
		}
		return $exclude;
	}

	/**
	 * @param mixed $image Image URL.
	 * @return mixed
	 */
	public function opengraph_image( $image ) {
		if ( ! is_singular( Config::CPT ) ) {
			return $image;
		}
		$thumb = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		if ( is_string( $thumb ) && '' !== $thumb ) {
			return $thumb;
		}
		return $image;
	}

	/**
	 * @param mixed $desc Meta description.
	 * @return mixed
	 */
	public function metadesc( $desc ) {
		if ( ! is_singular( Config::CPT ) ) {
			return $desc;
		}
		if ( is_string( $desc ) && '' !== trim( wp_strip_all_tags( $desc ) ) ) {
			return $desc;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post ) {
			return $desc;
		}
		$raw = $post->post_excerpt ?: $post->post_content;
		return wp_trim_words( wp_strip_all_tags( (string) $raw ), 40, '…' );
	}

	/**
	 * @param mixed $title Title.
	 * @return mixed
	 */
	public function title( $title ) {
		if ( ! is_singular( Config::CPT ) ) {
			return $title;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post || '' === $post->post_title ) {
			return $title;
		}
		$site = get_bloginfo( 'name' );
		return $post->post_title . ( $site ? ' - ' . $site : '' );
	}

	/**
	 * @param mixed $parts Title parts.
	 * @return array<string, string>
	 */
	public function document_title_parts( $parts ): array {
		if ( ! is_array( $parts ) ) {
			$parts = array();
		}
		if ( ! is_singular( Config::CPT ) ) {
			return $parts;
		}
		$post = get_post( get_queried_object_id() );
		if ( $post && '' !== $post->post_title ) {
			$parts['title'] = $post->post_title;
		}
		return $parts;
	}

	/**
	 * Prefer the CPT permalink so shortcode shells do not confuse crawlers.
	 */
	public function canonical_singular(): void {
		if ( ! is_singular( Config::CPT ) ) {
			return;
		}
		$expected = get_permalink( get_queried_object_id() );
		if ( ! is_string( $expected ) || '' === $expected ) {
			return;
		}
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$current_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$expected_path = wp_parse_url( $expected, PHP_URL_PATH );
		if ( ! is_string( $expected_path ) || ! is_string( $current_path ) ) {
			return;
		}
		if ( rtrim( $expected_path, '/' ) !== rtrim( $current_path, '/' ) ) {
			wp_safe_redirect( $expected, 301 );
			exit;
		}
	}
}
