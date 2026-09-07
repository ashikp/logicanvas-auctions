<?php
/**
 * Frontend edit listing form — Classic Editor + admin-style auction fields.
 *
 * Expects $wcap['edit'], $wcap['auction_id'], $wcap['can_publish'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_edit       = is_array( $wcap['edit'] ?? null ) ? $wcap['edit'] : array();
$wcap_auction_id = (int) ( $wcap['auction_id'] ?? $wcap_edit['id'] ?? 0 );
$wcap_can_publish = ! empty( $wcap['can_publish'] );

$wcap_val = static function ( string $key, string $default = '' ) use ( $wcap_edit ): string {
	if ( ! isset( $wcap_edit[ $key ] ) ) {
		return $default;
	}
	return (string) $wcap_edit[ $key ];
};

$wcap_featured_id  = (int) $wcap_val( 'featured_image_id', '0' );
$wcap_gallery_ids  = $wcap_val( 'gallery_ids' );
$wcap_featured_url = $wcap_val( 'featured_image_url' );
$wcap_gallery_prev = is_array( $wcap_edit['gallery_previews'] ?? null ) ? $wcap_edit['gallery_previews'] : array();
$wcap_type         = $wcap_val( 'type', 'timed' );
$wcap_visibility   = $wcap_val( 'visibility', 'public' );
$wcap_fulfilment  = $wcap_val( 'fulfilment_type', 'shipping' );
$wcap_state        = $wcap_val( 'state', 'draft' );
$wcap_permalink    = $wcap_val( 'permalink' );
$wcap_view_url     = $wcap_permalink ?: get_permalink( $wcap_auction_id );
$wcap_category_id  = (int) $wcap_val( 'category_id', '0' );

$wcap_cat_tax   = \LogicanvasAuctions\Config::TAXONOMY_CAT;
$wcap_cat_terms = array();
if ( taxonomy_exists( $wcap_cat_tax ) ) {
	$wcap_got = get_terms(
		array(
			'taxonomy'   => $wcap_cat_tax,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_array( $wcap_got ) && ! is_wp_error( $wcap_got ) ) {
		$wcap_cat_terms = $wcap_got;
	}
}
?>
<form
	class="wcap-form wcap-edit-form"
	data-wcap-submit
	data-auction-id="<?php echo esc_attr( (string) $wcap_auction_id ); ?>"
	<?php echo $wcap_can_publish ? ' data-wcap-can-publish="1"' : ''; ?>
>
	<input type="hidden" name="product_source" value="new" />

	<div class="wcap-edit-layout">
		<div class="wcap-edit-main">
			<p class="wcap-edit-meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: auction ID, 2: state */
						__( 'Editing listing #%1$d · Status: %2$s', 'logicanvas-auctions' ),
						$wcap_auction_id,
						$wcap_state
					)
				);
				?>
				<?php if ( $wcap_view_url ) : ?>
					· <a href="<?php echo esc_url( (string) $wcap_view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View public page', 'logicanvas-auctions' ); ?></a>
				<?php endif; ?>
			</p>

			<label class="full"><?php esc_html_e( 'Title', 'logicanvas-auctions' ); ?>
				<input name="title" type="text" required maxlength="200" value="<?php echo esc_attr( $wcap_val( 'title' ) ); ?>" />
			</label>

			<label class="full wcap-field-category">
				<?php esc_html_e( 'Category', 'logicanvas-auctions' ); ?>
				<select name="category_id" id="wcap_category_id" required>
					<option value=""><?php esc_html_e( 'Select a category', 'logicanvas-auctions' ); ?></option>
					<?php foreach ( $wcap_cat_terms as $wcap_term ) : ?>
						<?php if ( ! $wcap_term instanceof WP_Term ) { continue; } ?>
						<option value="<?php echo esc_attr( (string) $wcap_term->term_id ); ?>" <?php selected( $wcap_category_id, (int) $wcap_term->term_id ); ?>><?php echo esc_html( (string) $wcap_term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="full"><?php esc_html_e( 'Short description / excerpt', 'logicanvas-auctions' ); ?>
				<textarea name="short_description" rows="3" maxlength="2000"><?php echo esc_textarea( $wcap_val( 'short_description' ) ); ?></textarea>
			</label>

			<div class="full wcap-classic-editor">
				<label for="wcap_auction_description"><?php esc_html_e( 'Full description', 'logicanvas-auctions' ); ?></label>
				<?php
				wp_editor(
					$wcap_val( 'description' ),
					'wcap_auction_description',
					array(
						'textarea_name' => 'description',
						'textarea_rows' => 14,
						'media_buttons' => current_user_can( 'upload_files' ),
						'teeny'         => false,
						'quicktags'     => true,
						'tinymce'       => array(
							'toolbar1'      => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,undo,redo',
							'toolbar2'      => '',
							'content_css'   => false,
							'wpautop'       => true,
							'convert_urls'  => false,
						),
						'editor_class'  => 'wcap-description-editor',
					)
				);
				?>
			</div>

			<fieldset class="wcap-media-picker full">
				<legend><?php esc_html_e( 'Photos', 'logicanvas-auctions' ); ?></legend>
				<p class="description"><?php esc_html_e( 'Featured image and gallery — same media controls as the admin auction editor.', 'logicanvas-auctions' ); ?></p>
				<?php if ( current_user_can( 'upload_files' ) ) : ?>
					<div class="wcap-media-field">
						<span class="wcap-media-label"><?php esc_html_e( 'Featured image', 'logicanvas-auctions' ); ?></span>
						<div class="wcap-media-actions">
							<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-featured-media><?php esc_html_e( 'Select from media library', 'logicanvas-auctions' ); ?></button>
						</div>
						<input type="hidden" name="featured_image_id" value="<?php echo esc_attr( $wcap_featured_id > 0 ? (string) $wcap_featured_id : '' ); ?>" />
						<div class="wcap-thumbs" data-wcap-featured-preview>
							<?php if ( $wcap_featured_id > 0 && $wcap_featured_url ) : ?>
								<div class="wcap-thumb" data-wcap-thumb-id="<?php echo esc_attr( (string) $wcap_featured_id ); ?>">
									<img src="<?php echo esc_url( $wcap_featured_url ); ?>" alt="" />
									<button type="button" aria-label="<?php esc_attr_e( 'Remove', 'logicanvas-auctions' ); ?>">×</button>
								</div>
							<?php endif; ?>
						</div>
					</div>
					<div class="wcap-media-field">
						<span class="wcap-media-label"><?php esc_html_e( 'Gallery (up to 12 images)', 'logicanvas-auctions' ); ?></span>
						<div class="wcap-media-actions">
							<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-gallery-media><?php esc_html_e( 'Select from media library', 'logicanvas-auctions' ); ?></button>
						</div>
						<input type="hidden" name="gallery_ids" value="<?php echo esc_attr( $wcap_gallery_ids ); ?>" />
						<div class="wcap-thumbs" data-wcap-gallery-preview>
							<?php foreach ( $wcap_gallery_prev as $wcap_g ) : ?>
								<?php
								$wcap_gid  = (int) ( $wcap_g['id'] ?? 0 );
								$wcap_gurl = (string) ( $wcap_g['url'] ?? '' );
								if ( $wcap_gid < 1 || '' === $wcap_gurl ) {
									continue;
								}
								?>
								<div class="wcap-thumb" data-wcap-thumb-id="<?php echo esc_attr( (string) $wcap_gid ); ?>">
									<img src="<?php echo esc_url( $wcap_gurl ); ?>" alt="" />
									<button type="button" aria-label="<?php esc_attr_e( 'Remove', 'logicanvas-auctions' ); ?>">×</button>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else : ?>
					<p class="wcap-empty"><?php esc_html_e( 'Your account cannot use the WordPress media library.', 'logicanvas-auctions' ); ?></p>
				<?php endif; ?>
			</fieldset>
		</div>

		<aside class="wcap-edit-side">
			<div class="wcap-edit-card">
				<h3><?php esc_html_e( 'Auction settings', 'logicanvas-auctions' ); ?></h3>
				<label><?php esc_html_e( 'Type', 'logicanvas-auctions' ); ?>
					<select name="type" disabled>
						<option value="timed" <?php selected( $wcap_type, 'timed' ); ?>><?php esc_html_e( 'Timed', 'logicanvas-auctions' ); ?></option>
						<option value="live" <?php selected( $wcap_type, 'live' ); ?>><?php esc_html_e( 'Live', 'logicanvas-auctions' ); ?></option>
					</select>
					<input type="hidden" name="type" value="<?php echo esc_attr( $wcap_type ); ?>" />
				</label>
				<label><?php esc_html_e( 'Visibility', 'logicanvas-auctions' ); ?>
					<select name="visibility">
						<option value="public" <?php selected( $wcap_visibility, 'public' ); ?>><?php esc_html_e( 'Public', 'logicanvas-auctions' ); ?></option>
						<option value="unlisted" <?php selected( $wcap_visibility, 'unlisted' ); ?>><?php esc_html_e( 'Unlisted', 'logicanvas-auctions' ); ?></option>
						<option value="private" <?php selected( $wcap_visibility, 'private' ); ?>><?php esc_html_e( 'Invite only', 'logicanvas-auctions' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Starting price', 'logicanvas-auctions' ); ?>
					<input name="starting_price" type="text" value="<?php echo esc_attr( $wcap_val( 'starting_price', '1.00' ) ); ?>" required />
				</label>
				<label><?php esc_html_e( 'Reserve price', 'logicanvas-auctions' ); ?>
					<input name="reserve_price" type="text" value="<?php echo esc_attr( $wcap_val( 'reserve_price' ) ); ?>" />
				</label>
				<label><?php esc_html_e( 'Buy now', 'logicanvas-auctions' ); ?>
					<input name="buy_now" type="text" value="<?php echo esc_attr( $wcap_val( 'buy_now' ) ); ?>" />
				</label>
				<label><?php esc_html_e( 'Minimum increment', 'logicanvas-auctions' ); ?>
					<input name="min_increment" type="text" value="<?php echo esc_attr( $wcap_val( 'min_increment', '1.00' ) ); ?>" />
				</label>
				<label><?php esc_html_e( 'Start (UTC)', 'logicanvas-auctions' ); ?>
					<input name="start_at" type="datetime-local" value="<?php echo esc_attr( $wcap_val( 'start_at' ) ); ?>" />
				</label>
				<label><?php esc_html_e( 'End (UTC)', 'logicanvas-auctions' ); ?>
					<input name="end_at" type="datetime-local" value="<?php echo esc_attr( $wcap_val( 'end_at' ) ); ?>" />
				</label>
				<label><?php esc_html_e( 'SKU', 'logicanvas-auctions' ); ?>
					<input name="sku" type="text" value="<?php echo esc_attr( $wcap_val( 'sku' ) ); ?>" />
				</label>
				<label><?php esc_html_e( 'Condition', 'logicanvas-auctions' ); ?>
					<input name="condition" type="text" value="<?php echo esc_attr( $wcap_val( 'condition' ) ); ?>" />
				</label>
				<label><?php esc_html_e( 'Fulfilment', 'logicanvas-auctions' ); ?>
					<select name="fulfilment_type">
						<option value="shipping" <?php selected( $wcap_fulfilment, 'shipping' ); ?>><?php esc_html_e( 'Shipping', 'logicanvas-auctions' ); ?></option>
						<option value="pickup" <?php selected( $wcap_fulfilment, 'pickup' ); ?>><?php esc_html_e( 'Pickup', 'logicanvas-auctions' ); ?></option>
						<option value="digital" <?php selected( $wcap_fulfilment, 'digital' ); ?>><?php esc_html_e( 'Digital', 'logicanvas-auctions' ); ?></option>
						<option value="holder_defined" <?php selected( $wcap_fulfilment, 'holder_defined' ); ?>><?php esc_html_e( 'Arranged with holder', 'logicanvas-auctions' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Shipping / pickup notes', 'logicanvas-auctions' ); ?>
					<textarea name="fulfilment_notes" rows="4"><?php echo esc_textarea( $wcap_val( 'fulfilment_notes' ) ); ?></textarea>
				</label>
			</div>

			<div class="wcap-form-actions">
				<button class="wcap-btn" type="submit" name="intent" value="draft"><?php esc_html_e( 'Save changes', 'logicanvas-auctions' ); ?></button>
				<button class="wcap-btn wcap-btn--secondary" type="submit" name="intent" value="submit"><?php esc_html_e( 'Submit for review', 'logicanvas-auctions' ); ?></button>
				<?php if ( $wcap_can_publish ) : ?>
					<button class="wcap-btn" type="submit" name="intent" value="publish"><?php esc_html_e( 'Publish now', 'logicanvas-auctions' ); ?></button>
				<?php endif; ?>
				<a class="wcap-btn wcap-btn--secondary" href="<?php echo esc_url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::url( \LogicanvasAuctions\Frontend\Dashboards\AccountRouter::LISTINGS ) ); ?>"><?php esc_html_e( 'Back to listings', 'logicanvas-auctions' ); ?></a>
			</div>
			<p class="wcap-form-status" role="status"></p>
		</aside>
	</div>
</form>
