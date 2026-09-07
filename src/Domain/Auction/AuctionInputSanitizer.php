<?php
/**
 * Sanitize and type-cast auction create/update payloads.
 *
 * Strips scripts and unknown keys. Money stays decimal strings (never floats).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Domain\Auction;

use LogicanvasAuctions\Domain\Money\Money;
use WP_Error;

final class AuctionInputSanitizer {

	/**
	 * Allowed fulfilment types (fixed enum).
	 *
	 * @var list<string>
	 */
	public const FULFILMENT_TYPES = array( 'shipping', 'pickup', 'digital', 'holder_defined' );

	/**
	 * Allowed product sources.
	 *
	 * @var list<string>
	 */
	public const PRODUCT_SOURCES = array( 'new', 'existing' );

	/**
	 * Allowed reserve display policies.
	 *
	 * @var list<string>
	 */
	public const RESERVE_DISPLAY = array( 'met_only', 'always', 'never' );

	/**
	 * Allowed increment strategies.
	 *
	 * @var list<string>
	 */
	public const INCREMENT_STRATEGIES = array( 'fixed', 'percent', 'table' );

	/**
	 * Keys accepted on create/update. Everything else is discarded.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_KEYS = array(
		'title',
		'description',
		'short_description',
		'type',
		'visibility',
		'product_source',
		'product_id',
		'sku',
		'condition',
		'starting_price',
		'reserve_price',
		'min_increment',
		'buy_now',
		'start_at',
		'start_at_utc',
		'end_at',
		'end_at_utc',
		'fulfilment',
		'fulfilment_type',
		'fulfilment_notes',
		'tax_class',
		'shipping_class',
		'timezone',
		'reserve_display',
		'increment_strategy',
		'payment_deadline_hours',
		'proxy_enabled',
		'featured_image_id',
		'gallery_ids',
	);

	/**
	 * Sanitize a raw request array for auction create/update.
	 *
	 * @param array<string, mixed> $raw Raw input.
	 * @param bool                 $require_title When true, empty title is an error.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sanitize( array $raw, bool $require_title = false ) {
		$out = array();

		foreach ( self::ALLOWED_KEYS as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			$out[ $key ] = $raw[ $key ];
		}

		if ( isset( $out['title'] ) ) {
			$out['title'] = self::plain_text( $out['title'], 200 );
		}
		if ( $require_title && ( ! isset( $out['title'] ) || '' === $out['title'] ) ) {
			return new WP_Error( 'title', __( 'Title is required.', 'logicanvas-auctions' ) );
		}

		if ( isset( $out['description'] ) ) {
			$out['description'] = self::safe_html( $out['description'] );
		}

		if ( isset( $out['short_description'] ) ) {
			// Cards/excerpts: plain text only (no HTML/script).
			$out['short_description'] = self::plain_textarea( $out['short_description'], 2000 );
		}

		if ( isset( $out['condition'] ) ) {
			$out['condition'] = self::plain_text( $out['condition'], 120 );
		}

		if ( isset( $out['fulfilment_notes'] ) ) {
			$out['fulfilment_notes'] = self::plain_textarea( $out['fulfilment_notes'], 2000 );
		}

		if ( isset( $out['sku'] ) ) {
			$out['sku'] = self::plain_text( $out['sku'], 100 );
		}

		if ( isset( $out['tax_class'] ) ) {
			$out['tax_class'] = sanitize_text_field( (string) $out['tax_class'] );
		}

		if ( isset( $out['shipping_class'] ) ) {
			$out['shipping_class'] = sanitize_text_field( (string) $out['shipping_class'] );
		}

		if ( isset( $out['timezone'] ) ) {
			$out['timezone'] = sanitize_text_field( (string) $out['timezone'] );
		}

		if ( isset( $out['type'] ) ) {
			$type = sanitize_key( (string) $out['type'] );
			if ( ! AuctionType::is_valid( $type ) ) {
				return new WP_Error( 'invalid_type', __( 'Invalid auction type.', 'logicanvas-auctions' ) );
			}
			$out['type'] = $type;
		}

		if ( isset( $out['visibility'] ) ) {
			$vis = sanitize_key( (string) $out['visibility'] );
			if ( ! Visibility::is_valid( $vis ) ) {
				return new WP_Error( 'invalid_visibility', __( 'Invalid visibility.', 'logicanvas-auctions' ) );
			}
			$out['visibility'] = $vis;
		}

		if ( isset( $out['product_source'] ) ) {
			$src = sanitize_key( (string) $out['product_source'] );
			if ( ! in_array( $src, self::PRODUCT_SOURCES, true ) ) {
				$src = 'new';
			}
			$out['product_source'] = $src;
		}

		if ( isset( $out['product_id'] ) ) {
			$out['product_id'] = absint( $out['product_id'] );
		}

		if ( isset( $out['featured_image_id'] ) ) {
			$out['featured_image_id'] = absint( $out['featured_image_id'] );
		}

		if ( isset( $out['gallery_ids'] ) ) {
			$out['gallery_ids'] = self::int_list( $out['gallery_ids'], 12 );
		}

		foreach ( array( 'starting_price', 'reserve_price', 'min_increment', 'buy_now' ) as $money_key ) {
			if ( ! isset( $out[ $money_key ] ) ) {
				continue;
			}
			$money = self::money_string( $out[ $money_key ] );
			if ( is_wp_error( $money ) ) {
				$money->add_data( array( 'status' => 400 ) );
				return $money;
			}
			$out[ $money_key ] = $money;
		}

		foreach ( array( 'start_at', 'start_at_utc', 'end_at', 'end_at_utc' ) as $date_key ) {
			if ( ! isset( $out[ $date_key ] ) ) {
				continue;
			}
			$out[ $date_key ] = self::datetime_string( $out[ $date_key ] );
		}

		if ( isset( $out['fulfilment'] ) || isset( $out['fulfilment_type'] ) ) {
			$fulfilment = sanitize_key( (string) ( $out['fulfilment_type'] ?? $out['fulfilment'] ?? 'shipping' ) );
			if ( ! in_array( $fulfilment, self::FULFILMENT_TYPES, true ) ) {
				$fulfilment = 'shipping';
			}
			$out['fulfilment_type'] = $fulfilment;
			$out['fulfilment']      = $fulfilment;
		}

		if ( isset( $out['reserve_display'] ) ) {
			$rd = sanitize_key( (string) $out['reserve_display'] );
			$out['reserve_display'] = in_array( $rd, self::RESERVE_DISPLAY, true ) ? $rd : 'met_only';
		}

		if ( isset( $out['increment_strategy'] ) ) {
			$st = sanitize_key( (string) $out['increment_strategy'] );
			$out['increment_strategy'] = in_array( $st, self::INCREMENT_STRATEGIES, true ) ? $st : 'fixed';
		}

		if ( isset( $out['payment_deadline_hours'] ) ) {
			$hours = absint( $out['payment_deadline_hours'] );
			$out['payment_deadline_hours'] = min( 8760, max( 1, $hours ) );
		}

		if ( isset( $out['proxy_enabled'] ) ) {
			$out['proxy_enabled'] = self::to_bool( $out['proxy_enabled'] );
		}

		return $out;
	}

	/**
	 * REST sanitize_callback for a full JSON body (returns array or leaves errors to controller).
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_rest_params( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$result = self::sanitize( $value, false );
		return is_wp_error( $result ) ? array() : $result;
	}

	/**
	 * Strip tags/scripts; single-line plain text.
	 */
	public static function plain_text( mixed $value, int $max_length = 255 ): string {
		$text = sanitize_text_field( wp_strip_all_tags( (string) $value ) );
		if ( $max_length > 0 && strlen( $text ) > $max_length ) {
			$text = substr( $text, 0, $max_length );
		}
		return $text;
	}

	/**
	 * Plain multiline text (no HTML/script).
	 */
	public static function plain_textarea( mixed $value, int $max_length = 5000 ): string {
		$text = sanitize_textarea_field( wp_strip_all_tags( (string) $value ) );
		if ( $max_length > 0 && strlen( $text ) > $max_length ) {
			$text = substr( $text, 0, $max_length );
		}
		return $text;
	}

	/**
	 * Allow safe post HTML only (scripts, iframes, and event handlers removed by wp_kses_post).
	 */
	public static function safe_html( mixed $value ): string {
		$html = wp_kses_post( (string) $value );
		// Defense in depth: strip leftover script/style blocks if any filter re-introduced them.
		$html = (string) preg_replace( '#<\s*(script|style|iframe|object|embed|link|meta)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html );
		$html = (string) preg_replace( '#<\s*(script|style|iframe|object|embed|link|meta)[^>]*/?\s*>#is', '', $html );
		$html = (string) preg_replace( '/\son[a-z]+\s*=\s*("|\').*?\1/iu', '', $html );
		$html = (string) preg_replace( '/\s(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/iu', '', $html );
		return trim( $html );
	}

	/**
	 * Normalize money to a decimal string, or empty string when blank.
	 *
	 * @return string|WP_Error
	 */
	public static function money_string( mixed $value ) {
		if ( null === $value ) {
			return '';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			// Never trust binary floats for money — cast via string formatting.
			$value = is_int( $value ) ? (string) $value : number_format( (float) $value, 2, '.', '' );
		}
		$raw = trim( (string) $value );
		$raw = str_replace( array( ',', ' ' ), array( '', '' ), $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! preg_match( '/^\d+(\.\d{1,6})?$/', $raw ) ) {
			return new WP_Error( 'invalid_money', __( 'Enter a valid amount (numbers only).', 'logicanvas-auctions' ) );
		}
		try {
			return Money::from_string( $raw, 'USD' )->amount();
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'invalid_money', __( 'Enter a valid amount (numbers only).', 'logicanvas-auctions' ) );
		}
	}

	/**
	 * @param mixed $value CSV string or list.
	 * @return list<int>
	 */
	public static function int_list( mixed $value, int $max_items = 12 ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value ) ?: array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			$id = absint( $item );
			if ( $id > 0 ) {
				$out[] = $id;
			}
			if ( count( $out ) >= $max_items ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function datetime_string( mixed $value ): string {
		$text = sanitize_text_field( (string) $value );
		$text = str_replace( 'T', ' ', $text );
		return trim( $text );
	}

	public static function to_bool( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (int) $value === 1;
		}
		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true );
	}
}
