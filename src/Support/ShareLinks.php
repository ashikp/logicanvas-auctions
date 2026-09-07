<?php
/**
 * Social share URL builders for auction listings.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Support;

final class ShareLinks {

	/**
	 * @return array<string, array{label:string,url:string}>
	 */
	public static function for_auction( string $url, string $title ): array {
		$url   = esc_url( $url );
		$title = sanitize_text_field( $title );

		if ( '' === $url ) {
			return array();
		}

		$encoded_url   = rawurlencode( $url );
		$encoded_title = rawurlencode( $title );
		$wa_text       = rawurlencode( trim( $title . ' ' . $url ) );

		return array(
			'facebook' => array(
				'label' => __( 'Facebook', 'logicanvas-auctions' ),
				'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
			),
			'x'        => array(
				'label' => __( 'X', 'logicanvas-auctions' ),
				'url'   => 'https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title,
			),
			'linkedin' => array(
				'label' => __( 'LinkedIn', 'logicanvas-auctions' ),
				'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encoded_url,
			),
			'whatsapp' => array(
				'label' => __( 'WhatsApp', 'logicanvas-auctions' ),
				'url'   => 'https://wa.me/?text=' . $wa_text,
			),
			'email'    => array(
				'label' => __( 'Email', 'logicanvas-auctions' ),
				'url'   => 'mailto:?subject=' . $encoded_title . '&body=' . rawurlencode( $url ),
			),
		);
	}
}
