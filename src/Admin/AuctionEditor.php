<?php
/**
 * wp-admin auction editor: product, gallery, and auction settings.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Admin;

use LogicanvasAuctions\Config;
use LogicanvasAuctions\Domain\Auction\AuctionService;
use LogicanvasAuctions\Infrastructure\Database\WpdbAuctionRepository;

final class AuctionEditor {

	public function register(): void {
		add_action( 'add_meta_boxes_' . Config::CPT, array( $this, 'meta_boxes' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_' . Config::CPT, array( $this, 'save' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'default_hidden_meta_boxes', array( $this, 'unhide_meta_boxes' ), 10, 2 );
		add_filter( 'hidden_meta_boxes', array( $this, 'unhide_meta_boxes' ), 10, 2 );
	}

	/**
	 * Keep auction meta boxes visible (not tucked away under Screen Options).
	 *
	 * @param string[]   $hidden Hidden box IDs.
	 * @param \WP_Screen $screen Current screen.
	 * @return string[]
	 */
	public function unhide_meta_boxes( array $hidden, $screen ): array {
		if ( ! $screen instanceof \WP_Screen || Config::CPT !== $screen->post_type ) {
			return $hidden;
		}

		$keep = array( 'wcap_auction_product', 'wcap_auction_gallery', 'wcap_auction_settings' );
		return array_values( array_diff( $hidden, $keep ) );
	}

	public function notices(): void {
		if ( empty( $_GET['wcap_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( (string) $_GET['wcap_error'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public function meta_boxes(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_meta_box(
			'wcap_auction_product',
			__( 'Auction product', 'logicanvas-auctions' ),
			array( $this, 'render_product' ),
			Config::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'wcap_auction_gallery',
			__( 'Auction gallery', 'logicanvas-auctions' ),
			array( $this, 'render_gallery' ),
			Config::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'wcap_auction_settings',
			__( 'Auction settings', 'logicanvas-auctions' ),
			array( $this, 'render_settings' ),
			Config::CPT,
			'side',
			'high'
		);
	}

	public function scripts( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Config::CPT !== $screen->post_type ) {
			return;
		}

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'wcap-admin',
			plugins_url( 'assets/dist/css/admin.css', Config::plugin_file() ),
			array(),
			Config::VERSION
		);

		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		$handle = 'wcap-auction-editor';
		wp_register_script( $handle, false, array( 'jquery' ), Config::VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, $this->editor_js() );
	}

	private function editor_js(): string {
		return <<<'JS'
(function($){
	function syncSource(){
		var source=document.querySelector('input[name="wcap_product_source"]:checked')||document.querySelector('input[name="wcap_product_source"]');
		var value=source?source.value:'new';
		document.querySelectorAll('[data-wcap-source-panel]').forEach(function(el){
			el.hidden=el.getAttribute('data-wcap-source-panel')!==value;
		});
	}
	document.querySelectorAll('input[name="wcap_product_source"]').forEach(function(el){
		el.addEventListener('change',syncSource);
	});
	syncSource();

	var input=document.getElementById('wcap_gallery_ids');
	var preview=document.getElementById('wcap-gallery-preview');
	var addBtn=document.getElementById('wcap-gallery-add');
	if(!input||!preview||!addBtn||typeof wp==='undefined'||!wp.media){return;}

	function ids(){
		return String(input.value||'').split(',').map(function(v){return parseInt(v,10)||0;}).filter(Boolean);
	}
	function write(list){
		input.value=list.join(',');
	}
	function bindRemove(btn){
		btn.addEventListener('click',function(){
			var id=parseInt(btn.getAttribute('data-id')||'0',10);
			write(ids().filter(function(v){return v!==id;}));
			var card=btn.closest('.wcap-gallery-admin__item');
			if(card){card.remove();}
		});
	}
	preview.querySelectorAll('[data-wcap-gallery-remove]').forEach(bindRemove);

	addBtn.addEventListener('click',function(e){
		e.preventDefault();
		var frame=wp.media({
			title: addBtn.getAttribute('data-title')||'Select gallery images',
			button:{text: addBtn.getAttribute('data-button')||'Add to gallery'},
			multiple:true,
			library:{type:'image'}
		});
		frame.on('select',function(){
			var selection=frame.state().get('selection');
			var list=ids();
			selection.each(function(attachment){
				var data=attachment.toJSON();
				var id=parseInt(data.id,10)||0;
				if(!id||list.indexOf(id)!==-1||list.length>=20){return;}
				list.push(id);
				var url=(data.sizes&&data.sizes.thumbnail&&data.sizes.thumbnail.url)||data.url||'';
				var item=document.createElement('div');
				item.className='wcap-gallery-admin__item';
				item.innerHTML='<img src="'+url+'" alt="" /><button type="button" class="button-link wcap-gallery-admin__remove" data-wcap-gallery-remove data-id="'+id+'" aria-label="Remove">&times;</button>';
				preview.appendChild(item);
				bindRemove(item.querySelector('[data-wcap-gallery-remove]'));
			});
			write(list);
		});
		frame.open();
	});
})(jQuery);
JS;
	}

	public function render_product( \WP_Post $post ): void {
		wp_nonce_field( 'wcap_auction_editor', 'wcap_editor_nonce' );
		$auction = ( new WpdbAuctionRepository() )->find( $post->ID );
		$source  = (string) get_post_meta( $post->ID, '_wcap_product_source', true );
		$pid     = $auction ? $auction->product_id() : (int) get_post_meta( $post->ID, 'wcap_product_id', true );
		if ( '' === $source ) {
			$source = $pid ? 'existing' : 'new';
		}

		$product      = $pid && function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
		$can_existing = current_user_can( 'manage_options' );
		?>
		<p><?php echo $can_existing ? esc_html__( 'Choose a WooCommerce product to auction. You can create a new product or attach one that already exists in the catalog.', 'logicanvas-auctions' ) : esc_html__( 'A new WooCommerce product is created from this auction and hidden from the shop catalog.', 'logicanvas-auctions' ); ?></p>
		<?php if ( $can_existing ) : ?>
		<fieldset class="wcap-product-source">
			<legend class="screen-reader-text"><?php esc_html_e( 'Product source', 'logicanvas-auctions' ); ?></legend>
			<label>
				<input type="radio" name="wcap_product_source" value="new" <?php checked( $source, 'new' ); ?> />
				<?php esc_html_e( 'Create a new product', 'logicanvas-auctions' ); ?>
			</label>
			<label>
				<input type="radio" name="wcap_product_source" value="existing" <?php checked( $source, 'existing' ); ?> />
				<?php esc_html_e( 'Use an existing product', 'logicanvas-auctions' ); ?>
			</label>
		</fieldset>
		<?php else : ?>
			<input type="hidden" name="wcap_product_source" value="new" />
		<?php endif; ?>

		<div class="wcap-product-new" data-wcap-source-panel="new">
			<p>
				<label for="wcap_sku"><?php esc_html_e( 'SKU (optional)', 'logicanvas-auctions' ); ?></label><br />
				<input class="regular-text" type="text" id="wcap_sku" name="wcap_sku" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_wcap_sku', true ) ); ?>" />
			</p>
			<p class="description"><?php esc_html_e( 'The auction title, content, featured image, and gallery on this screen become the WooCommerce product media.', 'logicanvas-auctions' ); ?></p>
		</div>

		<?php if ( $can_existing ) : ?>
		<div class="wcap-product-existing" data-wcap-source-panel="existing">
			<p>
				<label for="wcap_product_id"><?php esc_html_e( 'WooCommerce product', 'logicanvas-auctions' ); ?></label><br />
				<select class="wc-product-search" id="wcap_product_id" name="wcap_product_id" data-placeholder="<?php esc_attr_e( 'Search products…', 'logicanvas-auctions' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" style="width:100%">
					<?php if ( $product ) : ?>
						<option value="<?php echo esc_attr( (string) $product->get_id() ); ?>" selected="selected"><?php echo esc_html( $product->get_formatted_name() ); ?></option>
					<?php endif; ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'The selected product is reserved for this auction until it ends. Gallery edits below still update this product.', 'logicanvas-auctions' ); ?></p>
		</div>
		<?php endif; ?>
		<?php
	}

	public function render_gallery( \WP_Post $post ): void {
		$ids = get_post_meta( $post->ID, '_wcap_gallery', true );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			$product_id = (int) get_post_meta( $post->ID, 'wcap_product_id', true );
			if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$ids = array_values( array_filter( array_map( 'intval', $product->get_gallery_image_ids() ) ) );
				}
			}
		}
		?>
		<p class="description"><?php esc_html_e( 'Add extra photos for the auction page. The featured image (right sidebar) is the main photo; these appear as gallery thumbnails and sync to the linked WooCommerce product.', 'logicanvas-auctions' ); ?></p>
		<input type="hidden" name="wcap_gallery_ids" id="wcap_gallery_ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" />
		<div class="wcap-gallery-admin" id="wcap-gallery-preview">
			<?php foreach ( $ids as $attachment_id ) : ?>
				<?php
				$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
				if ( ! $url ) {
					continue;
				}
				?>
				<div class="wcap-gallery-admin__item">
					<img src="<?php echo esc_url( $url ); ?>" alt="" />
					<button type="button" class="button-link wcap-gallery-admin__remove" data-wcap-gallery-remove data-id="<?php echo esc_attr( (string) $attachment_id ); ?>" aria-label="<?php esc_attr_e( 'Remove image', 'logicanvas-auctions' ); ?>">&times;</button>
				</div>
			<?php endforeach; ?>
		</div>
		<p>
			<button
				type="button"
				class="button"
				id="wcap-gallery-add"
				data-title="<?php esc_attr_e( 'Select gallery images', 'logicanvas-auctions' ); ?>"
				data-button="<?php esc_attr_e( 'Add to gallery', 'logicanvas-auctions' ); ?>"
			><?php esc_html_e( 'Add gallery images', 'logicanvas-auctions' ); ?></button>
		</p>
		<?php
	}

	public function render_settings( \WP_Post $post ): void {
		$auction     = ( new WpdbAuctionRepository() )->find( $post->ID );
		$type        = $auction ? $auction->type() : 'timed';
		$vis         = $auction ? $auction->visibility() : 'public';
		$start       = $auction ? str_replace( ' ', 'T', $auction->start_at_utc() ) : '';
		$end         = $auction && $auction->end_at_utc() ? str_replace( ' ', 'T', $auction->end_at_utc() ) : '';
		$starting    = $auction ? $auction->starting_amount()->amount() : '';
		$reserve     = $auction && $auction->reserve_amount() ? $auction->reserve_amount()->amount() : '';
		$buy_now     = $auction && $auction->buy_now_amount() ? $auction->buy_now_amount()->amount() : (string) get_post_meta( $post->ID, '_wcap_buy_now', true );
		$inc         = $auction ? $auction->min_increment()->amount() : '1.00';
		$condition   = (string) get_post_meta( $post->ID, '_wcap_condition', true );
		$fulfilment = (string) get_post_meta( $post->ID, '_wcap_fulfilment_type', true );
		if ( '' === $fulfilment && $auction ) {
			$row         = $auction->to_array();
			$fulfilment = (string) ( $row['fulfilment_type'] ?? 'shipping' );
		}
		if ( '' === $fulfilment ) {
			$fulfilment = 'shipping';
		}
		$notes = (string) get_post_meta( $post->ID, '_wcap_fulfilment_notes', true );
		?>
		<p>
			<label for="wcap_type"><?php esc_html_e( 'Type', 'logicanvas-auctions' ); ?></label><br />
			<select id="wcap_type" name="wcap_type" class="widefat">
				<option value="timed" <?php selected( $type, 'timed' ); ?>><?php esc_html_e( 'Timed', 'logicanvas-auctions' ); ?></option>
				<option value="live" <?php selected( $type, 'live' ); ?>><?php esc_html_e( 'Live', 'logicanvas-auctions' ); ?></option>
			</select>
		</p>
		<p>
			<label for="wcap_visibility"><?php esc_html_e( 'Visibility', 'logicanvas-auctions' ); ?></label><br />
			<select id="wcap_visibility" name="wcap_visibility" class="widefat">
				<option value="public" <?php selected( $vis, 'public' ); ?>><?php esc_html_e( 'Public', 'logicanvas-auctions' ); ?></option>
				<option value="unlisted" <?php selected( $vis, 'unlisted' ); ?>><?php esc_html_e( 'Unlisted', 'logicanvas-auctions' ); ?></option>
				<option value="private" <?php selected( $vis, 'private' ); ?>><?php esc_html_e( 'Invite only', 'logicanvas-auctions' ); ?></option>
			</select>
		</p>
		<p>
			<label for="wcap_starting_price"><?php esc_html_e( 'Starting price', 'logicanvas-auctions' ); ?></label><br />
			<input class="widefat" type="text" id="wcap_starting_price" name="wcap_starting_price" value="<?php echo esc_attr( $starting ); ?>" />
		</p>
		<p>
			<label for="wcap_reserve_price"><?php esc_html_e( 'Reserve price', 'logicanvas-auctions' ); ?></label><br />
			<input class="widefat" type="text" id="wcap_reserve_price" name="wcap_reserve_price" value="<?php echo esc_attr( $reserve ); ?>" />
		</p>
		<p>
			<label for="wcap_buy_now"><?php esc_html_e( 'Buy now', 'logicanvas-auctions' ); ?></label><br />
			<input class="widefat" type="text" id="wcap_buy_now" name="wcap_buy_now" value="<?php echo esc_attr( $buy_now ); ?>" />
		</p>
		<p>
			<label for="wcap_min_increment"><?php esc_html_e( 'Minimum increment', 'logicanvas-auctions' ); ?></label><br />
			<input class="widefat" type="text" id="wcap_min_increment" name="wcap_min_increment" value="<?php echo esc_attr( $inc ); ?>" />
		</p>
		<p>
			<label for="wcap_start_at"><?php esc_html_e( 'Start (UTC)', 'logicanvas-auctions' ); ?></label><br />
			<input class="widefat" type="datetime-local" id="wcap_start_at" name="wcap_start_at" value="<?php echo esc_attr( $start ); ?>" />
		</p>
		<p>
			<label for="wcap_end_at"><?php esc_html_e( 'End (UTC)', 'logicanvas-auctions' ); ?></label><br />
			<input class="widefat" type="datetime-local" id="wcap_end_at" name="wcap_end_at" value="<?php echo esc_attr( $end ); ?>" />
		</p>
		<p>
			<label for="wcap_condition"><?php esc_html_e( 'Condition', 'logicanvas-auctions' ); ?></label><br />
			<input class="widefat" type="text" id="wcap_condition" name="wcap_condition" value="<?php echo esc_attr( $condition ); ?>" />
		</p>
		<p>
			<label for="wcap_fulfilment_type"><?php esc_html_e( 'Fulfilment', 'logicanvas-auctions' ); ?></label><br />
			<select id="wcap_fulfilment_type" name="wcap_fulfilment_type" class="widefat">
				<option value="shipping" <?php selected( $fulfilment, 'shipping' ); ?>><?php esc_html_e( 'Shipping', 'logicanvas-auctions' ); ?></option>
				<option value="pickup" <?php selected( $fulfilment, 'pickup' ); ?>><?php esc_html_e( 'Pickup', 'logicanvas-auctions' ); ?></option>
				<option value="digital" <?php selected( $fulfilment, 'digital' ); ?>><?php esc_html_e( 'Digital', 'logicanvas-auctions' ); ?></option>
				<option value="holder_defined" <?php selected( $fulfilment, 'holder_defined' ); ?>><?php esc_html_e( 'Arranged with holder', 'logicanvas-auctions' ); ?></option>
			</select>
		</p>
		<p>
			<label for="wcap_fulfilment_notes"><?php esc_html_e( 'Shipping / pickup notes', 'logicanvas-auctions' ); ?></label><br />
			<textarea class="widefat" rows="3" id="wcap_fulfilment_notes" name="wcap_fulfilment_notes"><?php echo esc_textarea( $notes ); ?></textarea>
		</p>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wcap_editor_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['wcap_editor_nonce'] ) ), 'wcap_auction_editor' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$source = sanitize_key( (string) wp_unslash( $_POST['wcap_product_source'] ?? 'new' ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			$source = 'new';
		}
		update_post_meta( $post_id, '_wcap_product_source', $source );
		update_post_meta( $post_id, '_wcap_sku', sanitize_text_field( (string) wp_unslash( $_POST['wcap_sku'] ?? '' ) ) );
		update_post_meta( $post_id, '_wcap_condition', sanitize_text_field( (string) wp_unslash( $_POST['wcap_condition'] ?? '' ) ) );
		update_post_meta( $post_id, '_wcap_fulfilment_type', sanitize_key( (string) wp_unslash( $_POST['wcap_fulfilment_type'] ?? 'shipping' ) ) );
		update_post_meta( $post_id, '_wcap_fulfilment_notes', sanitize_textarea_field( (string) wp_unslash( $_POST['wcap_fulfilment_notes'] ?? '' ) ) );
		update_post_meta( $post_id, '_wcap_buy_now', sanitize_text_field( (string) wp_unslash( $_POST['wcap_buy_now'] ?? '' ) ) );

		$gallery_raw = sanitize_text_field( (string) wp_unslash( $_POST['wcap_gallery_ids'] ?? '' ) );
		$gallery_ids = array_values(
			array_filter(
				array_map(
					'absint',
					preg_split( '/\s*,\s*/', $gallery_raw ) ?: array()
				)
			)
		);

		$input = array(
			'product_source'     => $source,
			'product_id'         => absint( wp_unslash( $_POST['wcap_product_id'] ?? 0 ) ),
			'sku'                => sanitize_text_field( (string) wp_unslash( $_POST['wcap_sku'] ?? '' ) ),
			'type'               => sanitize_key( (string) wp_unslash( $_POST['wcap_type'] ?? 'timed' ) ),
			'visibility'         => sanitize_key( (string) wp_unslash( $_POST['wcap_visibility'] ?? 'public' ) ),
			'starting_price'     => sanitize_text_field( (string) wp_unslash( $_POST['wcap_starting_price'] ?? '' ) ),
			'reserve_price'      => sanitize_text_field( (string) wp_unslash( $_POST['wcap_reserve_price'] ?? '' ) ),
			'buy_now'            => sanitize_text_field( (string) wp_unslash( $_POST['wcap_buy_now'] ?? '' ) ),
			'min_increment'      => sanitize_text_field( (string) wp_unslash( $_POST['wcap_min_increment'] ?? '1.00' ) ),
			'start_at'           => sanitize_text_field( (string) wp_unslash( $_POST['wcap_start_at'] ?? '' ) ),
			'end_at'             => sanitize_text_field( (string) wp_unslash( $_POST['wcap_end_at'] ?? '' ) ),
			'condition'          => sanitize_text_field( (string) wp_unslash( $_POST['wcap_condition'] ?? '' ) ),
			'fulfilment_type'   => sanitize_key( (string) wp_unslash( $_POST['wcap_fulfilment_type'] ?? 'shipping' ) ),
			'fulfilment_notes'  => sanitize_textarea_field( (string) wp_unslash( $_POST['wcap_fulfilment_notes'] ?? '' ) ),
			'description'        => (string) $post->post_content,
			'short_description'  => (string) $post->post_excerpt,
			'gallery_ids'        => $gallery_ids,
			'featured_image_id'  => (int) get_post_thumbnail_id( $post_id ),
		);

		$sanitized = \LogicanvasAuctions\Domain\Auction\AuctionInputSanitizer::sanitize( $input, false );
		if ( is_wp_error( $sanitized ) ) {
			add_filter(
				'redirect_post_location',
				static function ( string $location ) use ( $sanitized ): string {
					return add_query_arg( 'wcap_error', rawurlencode( $sanitized->get_error_message() ), $location );
				}
			);
			return;
		}

		$result = ( new AuctionService() )->sync_from_editor( $post_id, get_current_user_id(), $sanitized );
		if ( is_wp_error( $result ) ) {
			add_filter(
				'redirect_post_location',
				static function ( string $location ) use ( $result ): string {
					return add_query_arg( 'wcap_error', rawurlencode( $result->get_error_message() ), $location );
				}
			);
		}
	}
}
