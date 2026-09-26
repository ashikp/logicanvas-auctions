<?php
/**
 * Frontend design themes and customization tokens.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Frontend;

use LogicanvasAuctions\Admin\Settings;

final class Design {

	public const THEME_CLASSIC       = 'classic';
	public const THEME_MIDNIGHT      = 'midnight';
	public const THEME_AUCTION_HOUSE = 'auction_house';
	public const THEME_OCEAN         = 'ocean';
	public const THEME_MINIMAL       = 'minimal';
	public const THEME_EMBER         = 'ember';

	/**
	 * @return array<string, array{label: string, description: string, tokens: array<string, string>}>
	 */
	public static function themes(): array {
		return array(
			self::THEME_CLASSIC       => array(
				'label'       => __( 'Classic', 'logicanvas-auctions' ),
				'description' => __( 'Indigo accents on a clean light surface — the default look.', 'logicanvas-auctions' ),
				'tokens'      => array(
					'--wcap-accent'       => '#4f46e5',
					'--wcap-accent-hover' => '#4338ca',
					'--wcap-ink'          => '#0f172a',
					'--wcap-muted'        => '#64748b',
					'--wcap-line'         => '#e2e8f0',
					'--wcap-bg'           => '#f8fafc',
					'--wcap-card'         => '#ffffff',
					'--wcap-hero-from'    => '#0f172a',
					'--wcap-hero-mid'     => '#1e1b4b',
					'--wcap-hero-to'      => '#312e81',
					'--wcap-side'         => '#0f172a',
					'--wcap-hero-em'      => '#a5b4fc',
				),
			),
			self::THEME_MIDNIGHT      => array(
				'label'       => __( 'Midnight', 'logicanvas-auctions' ),
				'description' => __( 'Dark charcoal surfaces with cool sky accents for live rooms.', 'logicanvas-auctions' ),
				'tokens'      => array(
					'--wcap-accent'       => '#38bdf8',
					'--wcap-accent-hover' => '#0ea5e9',
					'--wcap-ink'          => '#e2e8f0',
					'--wcap-muted'        => '#94a3b8',
					'--wcap-line'         => '#334155',
					'--wcap-bg'           => '#0f172a',
					'--wcap-card'         => '#1e293b',
					'--wcap-hero-from'    => '#020617',
					'--wcap-hero-mid'     => '#0f172a',
					'--wcap-hero-to'      => '#164e63',
					'--wcap-side'         => '#020617',
					'--wcap-hero-em'      => '#7dd3fc',
					'--wcap-shadow'       => '0 12px 32px rgba(0,0,0,.35)',
				),
			),
			self::THEME_AUCTION_HOUSE => array(
				'label'       => __( 'Auction house', 'logicanvas-auctions' ),
				'description' => __( 'Deep green and gold — traditional gallery energy.', 'logicanvas-auctions' ),
				'tokens'      => array(
					'--wcap-accent'       => '#b45309',
					'--wcap-accent-hover' => '#92400e',
					'--wcap-ink'          => '#14532d',
					'--wcap-muted'        => '#4d7c5a',
					'--wcap-line'         => '#d8e2d4',
					'--wcap-bg'           => '#f4f7f2',
					'--wcap-card'         => '#ffffff',
					'--wcap-hero-from'    => '#052e16',
					'--wcap-hero-mid'     => '#14532d',
					'--wcap-hero-to'      => '#166534',
					'--wcap-side'         => '#052e16',
					'--wcap-hero-em'      => '#fbbf24',
				),
			),
			self::THEME_OCEAN         => array(
				'label'       => __( 'Ocean', 'logicanvas-auctions' ),
				'description' => __( 'Teal and soft blue for a calm marketplace feel.', 'logicanvas-auctions' ),
				'tokens'      => array(
					'--wcap-accent'       => '#0d9488',
					'--wcap-accent-hover' => '#0f766e',
					'--wcap-ink'          => '#134e4a',
					'--wcap-muted'        => '#5b7c7a',
					'--wcap-line'         => '#cce3e0',
					'--wcap-bg'           => '#f0fdfa',
					'--wcap-card'         => '#ffffff',
					'--wcap-hero-from'    => '#042f2e',
					'--wcap-hero-mid'     => '#115e59',
					'--wcap-hero-to'      => '#0e7490',
					'--wcap-side'         => '#042f2e',
					'--wcap-hero-em'      => '#5eead4',
				),
			),
			self::THEME_MINIMAL       => array(
				'label'       => __( 'Minimal', 'logicanvas-auctions' ),
				'description' => __( 'Near-black type, hairline borders, little decoration.', 'logicanvas-auctions' ),
				'tokens'      => array(
					'--wcap-accent'       => '#111827',
					'--wcap-accent-hover' => '#030712',
					'--wcap-ink'          => '#111827',
					'--wcap-muted'        => '#6b7280',
					'--wcap-line'         => '#e5e7eb',
					'--wcap-bg'           => '#ffffff',
					'--wcap-card'         => '#ffffff',
					'--wcap-hero-from'    => '#111827',
					'--wcap-hero-mid'     => '#1f2937',
					'--wcap-hero-to'      => '#374151',
					'--wcap-side'         => '#111827',
					'--wcap-hero-em'      => '#d1d5db',
					'--wcap-radius'       => '8px',
					'--wcap-shadow'       => 'none',
				),
			),
			self::THEME_EMBER         => array(
				'label'       => __( 'Ember', 'logicanvas-auctions' ),
				'description' => __( 'Warm copper accents for energetic bid panels.', 'logicanvas-auctions' ),
				'tokens'      => array(
					'--wcap-accent'       => '#ea580c',
					'--wcap-accent-hover' => '#c2410c',
					'--wcap-ink'          => '#1c1917',
					'--wcap-muted'        => '#78716c',
					'--wcap-line'         => '#e7e5e4',
					'--wcap-bg'           => '#fafaf9',
					'--wcap-card'         => '#ffffff',
					'--wcap-hero-from'    => '#1c1917',
					'--wcap-hero-mid'     => '#7c2d12',
					'--wcap-hero-to'      => '#c2410c',
					'--wcap-side'         => '#1c1917',
					'--wcap-hero-em'      => '#fdba74',
				),
			),
		);
	}

	/**
	 * @return string[]
	 */
	public static function theme_slugs(): array {
		return array_keys( self::themes() );
	}

	/**
	 * Classes for the `.wcap-root` shell.
	 */
	public static function root_classes( string $extra = '' ): string {
		$settings = Settings::get();
		$theme    = self::sanitize_theme( (string) ( $settings['design_theme'] ?? self::THEME_CLASSIC ) );
		$classes  = array( 'wcap-root', 'wcap-theme--' . $theme );

		$density = sanitize_key( (string) ( $settings['design_density'] ?? 'comfortable' ) );
		if ( 'compact' === $density ) {
			$classes[] = 'wcap-density--compact';
		}

		$cards = sanitize_key( (string) ( $settings['design_card_style'] ?? 'elevated' ) );
		if ( in_array( $cards, array( 'elevated', 'flat', 'outlined' ), true ) ) {
			$classes[] = 'wcap-cards--' . $cards;
		}

		$radius = sanitize_key( (string) ( $settings['design_radius'] ?? 'medium' ) );
		if ( in_array( $radius, array( 'soft', 'medium', 'sharp' ), true ) ) {
			$classes[] = 'wcap-radius--' . $radius;
		}

		$extra = trim( $extra );
		if ( '' !== $extra ) {
			foreach ( preg_split( '/\s+/', $extra ) ?: array() as $piece ) {
				$piece = sanitize_html_class( $piece );
				if ( '' !== $piece ) {
					$classes[] = $piece;
				}
			}
		}

		/**
		 * Filter CSS classes on the auction UI root wrapper.
		 *
		 * @param string[]             $classes  Class list.
		 * @param array<string, mixed> $settings Plugin settings.
		 */
		$classes = apply_filters( 'wcap_design_root_classes', $classes, $settings );

		$classes = array_values( array_unique( array_filter( array_map( 'strval', (array) $classes ) ) ) );

		return implode( ' ', $classes );
	}

	/**
	 * Open a design-aware root wrapper (escaped).
	 */
	public static function open_root( string $extra = '' ): void {
		echo '<div class="' . esc_attr( self::root_classes( $extra ) ) . '">';
	}

	public static function close_root(): void {
		echo '</div>';
	}

	/**
	 * Inline CSS that applies the chosen theme + customization overrides.
	 */
	public static function inline_css(): string {
		$settings = Settings::get();
		$theme    = self::sanitize_theme( (string) ( $settings['design_theme'] ?? self::THEME_CLASSIC ) );
		$themes   = self::themes();
		$tokens   = $themes[ $theme ]['tokens'];

		$accent = self::sanitize_hex( (string) ( $settings['design_accent'] ?? '' ) );
		if ( '' !== $accent ) {
			$tokens['--wcap-accent']       = $accent;
			$tokens['--wcap-accent-hover'] = self::darken_hex( $accent, 12 );
		}

		$radius = sanitize_key( (string) ( $settings['design_radius'] ?? 'medium' ) );
		$radius_map = array(
			'soft'   => '20px',
			'medium' => '16px',
			'sharp'  => '6px',
		);
		if ( isset( $radius_map[ $radius ] ) ) {
			$tokens['--wcap-radius'] = $radius_map[ $radius ];
		}

		$parts = array();
		foreach ( $tokens as $prop => $value ) {
			$parts[] = $prop . ':' . $value;
		}

		$css  = '.wcap-root.wcap-theme--' . $theme . '{' . implode( ';', $parts ) . '}';
		$css .= self::modifier_css();

		$custom = self::sanitize_custom_css( (string) ( $settings['design_custom_css'] ?? '' ) );
		if ( '' !== $custom ) {
			$css .= "\n" . $custom;
		}

		/**
		 * Filter generated design CSS printed after the main stylesheet.
		 *
		 * @param string               $css      CSS string.
		 * @param array<string, mixed> $settings Plugin settings.
		 */
		return (string) apply_filters( 'wcap_design_inline_css', $css, $settings );
	}

	public static function sanitize_theme( string $theme ): string {
		$theme = sanitize_key( $theme );
		return in_array( $theme, self::theme_slugs(), true ) ? $theme : self::THEME_CLASSIC;
	}

	public static function sanitize_hex( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
			if ( 4 === strlen( $value ) ) {
				return '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
			}
			return strtolower( $value );
		}
		return '';
	}

	/**
	 * Allowlist-ish CSS cleanup for optional custom rules.
	 */
	public static function sanitize_custom_css( string $css ): string {
		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '/@import\b[^;]*;/i', '', $css ) ?? '';
		$css = preg_replace( '/expression\s*\(/i', '', $css ) ?? '';
		$css = preg_replace( '/javascript\s*:/i', '', $css ) ?? '';
		$css = preg_replace( '/behavior\s*:/i', '', $css ) ?? '';
		$css = preg_replace( '/-moz-binding\s*:/i', '', $css ) ?? '';
		return trim( $css );
	}

	private static function modifier_css(): string {
		return '.wcap-root.wcap-density--compact{font-size:15px}'
			. '.wcap-root.wcap-density--compact .wcap-btn,'
			. '.wcap-root.wcap-density--compact a.wcap-btn,'
			. '.wcap-root.wcap-density--compact button.wcap-btn{min-height:36px;padding:0 14px}'
			. '.wcap-root.wcap-density--compact .wcap-card,'
			. '.wcap-root.wcap-density--compact .wcap-event,'
			. '.wcap-root.wcap-density--compact .wcap-panel{padding:14px}'
			. '.wcap-root.wcap-cards--flat .wcap-card,'
			. '.wcap-root.wcap-cards--flat .wcap-event,'
			. '.wcap-root.wcap-cards--flat .wcap-panel,'
			. '.wcap-root.wcap-cards--flat .lot-card{box-shadow:none !important}'
			. '.wcap-root.wcap-cards--outlined .wcap-card,'
			. '.wcap-root.wcap-cards--outlined .wcap-event,'
			. '.wcap-root.wcap-cards--outlined .wcap-panel,'
			. '.wcap-root.wcap-cards--outlined .lot-card{box-shadow:none !important;border:1px solid var(--wcap-line)}'
			. '.wcap-root.wcap-radius--sharp{--wcap-radius:6px}'
			. '.wcap-root.wcap-radius--soft{--wcap-radius:20px}'
			. '.wcap-root.wcap-radius--medium{--wcap-radius:16px}';
	}

	private static function darken_hex( string $hex, int $percent ): string {
		$hex = ltrim( self::sanitize_hex( $hex ), '#' );
		if ( 6 !== strlen( $hex ) ) {
			return '#' . $hex;
		}
		$r = max( 0, min( 255, (int) round( hexdec( substr( $hex, 0, 2 ) ) * ( 1 - ( $percent / 100 ) ) ) ) );
		$g = max( 0, min( 255, (int) round( hexdec( substr( $hex, 2, 2 ) ) * ( 1 - ( $percent / 100 ) ) ) ) );
		$b = max( 0, min( 255, (int) round( hexdec( substr( $hex, 4, 2 ) ) * ( 1 - ( $percent / 100 ) ) ) ) );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}
}
