<?php
/**
 * Create / submit auction form (dashboard and standalone page).
 * Plain textareas on the frontend — Classic Editor is used only on the dedicated edit page.
 *
 * Expects $wcap['can_publish'].
 *
 * @var array<string, mixed> $wcap
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wcap_can_publish  = ! empty( $wcap['can_publish'] );
$wcap_can_existing = current_user_can( 'manage_options' );
$wcap_start        = gmdate( 'Y-m-d\TH:i' );
$wcap_end          = gmdate( 'Y-m-d\TH:i', time() + WEEK_IN_SECONDS );

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
<form class="wcap-form wcap-submit-form lead-form" data-wcap-submit<?php echo $wcap_can_publish ? ' data-wcap-can-publish="1"' : ''; ?>>
	<?php if ( $wcap_can_existing ) : ?>
		<fieldset class="wcap-product-source full">
			<legend><?php esc_html_e( 'Product', 'logicanvas-auctions' ); ?></legend>
			<label>
				<input type="radio" name="product_source" value="new" checked />
				<?php esc_html_e( 'Create a new product', 'logicanvas-auctions' ); ?>
			</label>
			<label>
				<input type="radio" name="product_source" value="existing" />
				<?php esc_html_e( 'Use an existing WooCommerce product', 'logicanvas-auctions' ); ?>
			</label>
		</fieldset>

		<div class="full" data-wcap-source-panel="existing" hidden>
			<label><?php esc_html_e( 'Search catalog', 'logicanvas-auctions' ); ?>
				<input type="search" data-wcap-product-search autocomplete="off" placeholder="<?php esc_attr_e( 'Type a product name or SKU', 'logicanvas-auctions' ); ?>" />
			</label>
			<input type="hidden" name="product_id" value="" />
			<ul class="wcap-product-results" data-wcap-product-results hidden></ul>
			<p class="wcap-selected-product" data-wcap-selected-product></p>
		</div>
	<?php else : ?>
		<input type="hidden" name="product_source" value="new" />
	<?php endif; ?>

	<label class="full wcap-field-category">
		<?php esc_html_e( 'Category', 'logicanvas-auctions' ); ?>
		<select name="category_id" id="wcap_category_id" required>
			<option value=""><?php esc_html_e( 'Select a category', 'logicanvas-auctions' ); ?></option>
			<?php foreach ( $wcap_cat_terms as $wcap_term ) : ?>
				<?php if ( ! $wcap_term instanceof WP_Term ) { continue; } ?>
				<option value="<?php echo esc_attr( (string) $wcap_term->term_id ); ?>"><?php echo esc_html( (string) $wcap_term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>

	<div class="full" data-wcap-source-panel="new">
		<label><?php esc_html_e( 'Title', 'logicanvas-auctions' ); ?>
			<input name="title" type="text" required maxlength="200" />
		</label>
		<label class="full"><?php esc_html_e( 'Short description', 'logicanvas-auctions' ); ?>
			<textarea name="short_description" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Shown on auction cards and listings.', 'logicanvas-auctions' ); ?>"></textarea>
		</label>
		<label class="full"><?php esc_html_e( 'Full description', 'logicanvas-auctions' ); ?>
			<textarea name="description" rows="6" placeholder="<?php esc_attr_e( 'Condition notes, provenance, shipping details…', 'logicanvas-auctions' ); ?>"></textarea>
		</label>
		<label><?php esc_html_e( 'SKU (optional)', 'logicanvas-auctions' ); ?>
			<input name="sku" type="text" />
		</label>
		<label><?php esc_html_e( 'Condition', 'logicanvas-auctions' ); ?>
			<input name="condition" type="text" placeholder="<?php esc_attr_e( 'New, used, refurbished…', 'logicanvas-auctions' ); ?>" />
		</label>
	</div>

	<fieldset class="wcap-media-picker full">
		<legend><?php esc_html_e( 'Photos', 'logicanvas-auctions' ); ?></legend>
		<p class="description"><?php esc_html_e( 'Choose a featured image and optional gallery from the WordPress media library so bidders can see the lot on the auction page and in listings.', 'logicanvas-auctions' ); ?></p>
		<?php if ( current_user_can( 'upload_files' ) ) : ?>
			<div class="wcap-media-field">
				<span class="wcap-media-label"><?php esc_html_e( 'Featured image', 'logicanvas-auctions' ); ?></span>
				<div class="wcap-media-actions">
					<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-featured-media><?php esc_html_e( 'Select from media library', 'logicanvas-auctions' ); ?></button>
				</div>
				<input type="hidden" name="featured_image_id" value="" />
				<div class="wcap-thumbs" data-wcap-featured-preview></div>
			</div>
			<div class="wcap-media-field">
				<span class="wcap-media-label"><?php esc_html_e( 'Gallery (up to 12 images)', 'logicanvas-auctions' ); ?></span>
				<div class="wcap-media-actions">
					<button type="button" class="wcap-btn wcap-btn--secondary" data-wcap-gallery-media><?php esc_html_e( 'Select from media library', 'logicanvas-auctions' ); ?></button>
				</div>
				<input type="hidden" name="gallery_ids" value="" />
				<div class="wcap-thumbs" data-wcap-gallery-preview></div>
			</div>
		<?php else : ?>
			<p class="wcap-empty"><?php esc_html_e( 'Your account cannot use the WordPress media library. Ask an administrator to grant upload permission.', 'logicanvas-auctions' ); ?></p>
		<?php endif; ?>
	</fieldset>

	<label><?php esc_html_e( 'Type', 'logicanvas-auctions' ); ?>
		<select name="type">
			<option value="timed"><?php esc_html_e( 'Timed', 'logicanvas-auctions' ); ?></option>
			<option value="live"><?php esc_html_e( 'Live', 'logicanvas-auctions' ); ?></option>
		</select>
	</label>
	<label><?php esc_html_e( 'Starting price', 'logicanvas-auctions' ); ?>
		<input name="starting_price" type="text" value="1.00" required />
	</label>
	<label><?php esc_html_e( 'Reserve price (optional)', 'logicanvas-auctions' ); ?>
		<input name="reserve_price" type="text" />
	</label>
	<label><?php esc_html_e( 'Buy now price (optional)', 'logicanvas-auctions' ); ?>
		<input name="buy_now" type="text" />
	</label>
	<label><?php esc_html_e( 'Minimum increment', 'logicanvas-auctions' ); ?>
		<input name="min_increment" type="text" value="1.00" />
	</label>
	<label><?php esc_html_e( 'Start (UTC)', 'logicanvas-auctions' ); ?>
		<input name="start_at" type="datetime-local" value="<?php echo esc_attr( $wcap_start ); ?>" />
	</label>
	<label><?php esc_html_e( 'End (UTC, timed auctions)', 'logicanvas-auctions' ); ?>
		<input name="end_at" type="datetime-local" value="<?php echo esc_attr( $wcap_end ); ?>" />
	</label>
	<label><?php esc_html_e( 'Visibility', 'logicanvas-auctions' ); ?>
		<select name="visibility">
			<option value="public"><?php esc_html_e( 'Public — listed for everyone', 'logicanvas-auctions' ); ?></option>
			<option value="unlisted"><?php esc_html_e( 'Unlisted — share link only', 'logicanvas-auctions' ); ?></option>
			<option value="private"><?php esc_html_e( 'Invite only', 'logicanvas-auctions' ); ?></option>
		</select>
	</label>
	<label><?php esc_html_e( 'Fulfilment', 'logicanvas-auctions' ); ?>
		<select name="fulfilment_type">
			<option value="shipping"><?php esc_html_e( 'Shipping', 'logicanvas-auctions' ); ?></option>
			<option value="pickup"><?php esc_html_e( 'Pickup', 'logicanvas-auctions' ); ?></option>
			<option value="digital"><?php esc_html_e( 'Digital', 'logicanvas-auctions' ); ?></option>
			<option value="holder_defined"><?php esc_html_e( 'Arranged with holder', 'logicanvas-auctions' ); ?></option>
		</select>
	</label>
	<label class="full"><?php esc_html_e( 'Shipping / pickup notes', 'logicanvas-auctions' ); ?>
		<textarea name="fulfilment_notes" rows="3" placeholder="<?php esc_attr_e( 'Pickup location, shipping regions, packing notes…', 'logicanvas-auctions' ); ?>"></textarea>
	</label>

	<div class="wcap-form-actions full">
		<button class="wcap-btn wcap-btn--secondary" type="submit" name="intent" value="draft"><?php esc_html_e( 'Save draft', 'logicanvas-auctions' ); ?></button>
		<button class="wcap-btn" type="submit" name="intent" value="submit"><?php esc_html_e( 'Submit for review', 'logicanvas-auctions' ); ?></button>
		<?php if ( $wcap_can_publish ) : ?>
			<button class="wcap-btn" type="submit" name="intent" value="publish"><?php esc_html_e( 'Publish now', 'logicanvas-auctions' ); ?></button>
		<?php endif; ?>
	</div>
	<p class="wcap-form-status full" role="status"></p>
</form>
