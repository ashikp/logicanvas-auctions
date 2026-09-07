<?php
/**
 * Social share icon row for the Share tab.
 *
 * Expects $wcap keys: share_url, share_title.
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_share_url   = (string) ( $wcap['share_url'] ?? '' );
$wcap_share_title = (string) ( $wcap['share_title'] ?? '' );
$wcap_links       = \LogicanvasAuctions\Support\ShareLinks::for_auction( $wcap_share_url, $wcap_share_title );

if ( ! $wcap_links ) {
	return;
}
?>
<div class="wcap-share-social">
	<span class="wcap-share-social__label"><?php esc_html_e( 'Share on social media', 'logicanvas-auctions' ); ?></span>
	<div class="wcap-share-social__icons" role="list">
		<?php foreach ( $wcap_links as $wcap_network => $wcap_link ) : ?>
			<a
				class="wcap-share-social__btn wcap-share-social__btn--<?php echo esc_attr( $wcap_network ); ?>"
				role="listitem"
				href="<?php echo esc_url( $wcap_link['url'] ); ?>"
				target="_blank"
				rel="noopener noreferrer"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %s: network name */ __( 'Share on %s', 'logicanvas-auctions' ), $wcap_link['label'] ) ); ?>"
			>
				<?php if ( 'facebook' === $wcap_network ) : ?>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 8.5V6.7c0-.8.6-1 1-1h2.5V3h-3.4C12.8 3 11 4.8 11 7.2V8.5H8v3h3v9h3v-9h2.6l.4-3H14z"/></svg>
				<?php elseif ( 'x' === $wcap_network ) : ?>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16.6 3h3.1l-6.8 7.8L21 21h-6.2l-4.8-6.2L4.4 21H1.3l7.3-8.3L3 3h6.3l4.3 5.7L16.6 3zm-1.1 16.2h1.7L7.7 4.8H6l9.5 14.4z"/></svg>
				<?php elseif ( 'linkedin' === $wcap_network ) : ?>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.5 8.7V21h-3V8.7h3zM5 3a1.8 1.8 0 110 3.6A1.8 1.8 0 015 3zm4.5 5.7H6.5V21H9.5v-6c0-1.6.3-3.1 2.3-3.1 2 0 2 1.9 2 3.1V21h3v-6.4c0-3.2-1.7-4.7-4.1-4.7-1.9 0-2.7 1-3.2 1.7V8.7z"/></svg>
				<?php elseif ( 'whatsapp' === $wcap_network ) : ?>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 00-8.7 15l-1.3 4.8 4.9-1.3A10 10 0 1012 2zm0 18.2c-1.6 0-3.1-.4-4.4-1.2l-.3-.2-2.9.8.8-2.8-.2-.3A8.2 8.2 0 1112 20.2zm4.5-6.1c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.7.9-.3.2-.5.1a6.1 6.1 0 01-1.8-1.1 6.8 6.8 0 01-1.2-1.6c-.1-.2 0-.3.1-.4.1-.1.2-.3.3-.4.1-.1.1-.2.2-.3 0-.1 0-.2 0-.3s-.5-1.2-.7-1.6-.3-.4-.5-.4h-.4c-.1 0-.3.1-.5.3s-.7.8-.7 2 .7 2.3.8 2.5 1.4 2.1 3.3 2.9c.5.2.8.3 1.1.4.5.2.9.1 1.2.1.4-.1 1.4-.6 1.6-1.1.2-.5.2-.9.1-1 0-.1-.2-.1-.4-.2z"/></svg>
				<?php else : ?>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5.5A2.5 2.5 0 015.5 3h13A2.5 2.5 0 0121 5.5v13A2.5 2.5 0 0118.5 21h-13A2.5 2.5 0 013 18.5v-13zm2.2 0L12 11.3 18.8 5.5H5.2zM5 18.5h14V8.3l-6.2 6.2a1 1 0 01-1.4 0L5 8.3v10.2z"/></svg>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>
