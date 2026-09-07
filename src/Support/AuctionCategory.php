<?php
/**
 * Auction category taxonomy helpers (frontend forms + assignment).
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Support;

use LogicanvasAuctions\Config;

final class AuctionCategory {

	/**
	 * Hierarchical options for select fields.
	 *
	 * @return list<array{id:int,name:string,slug:string}>
	 */
	public static function list_for_form(): array {
		self::ensure_taxonomy_ready();
		self::ensure_default_term();

		if ( ! taxonomy_exists( Config::TAXONOMY_CAT ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Config::TAXONOMY_CAT,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$parent = (int) $term->parent;
			if ( ! isset( $by_parent[ $parent ] ) ) {
				$by_parent[ $parent ] = array();
			}
			$by_parent[ $parent ][] = $term;
		}

		$out = array();
		self::flatten( $by_parent, 0, 0, $out );

		// Orphaned children (missing parent) — still list them flat.
		if ( ! $out && $terms ) {
			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$out[] = array(
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
					'slug' => (string) $term->slug,
				);
			}
		}

		return $out;
	}

	/**
	 * Render the category select for seller forms. Always outputs markup.
	 *
	 * @param int  $selected Selected term ID.
	 * @param bool $required Mark the field required when options exist.
	 */
	public static function render_field( int $selected = 0, bool $required = true ): void {
		$categories = self::list_for_form();
		?>
		<fieldset class="full wcap-category-field">
			<legend><?php esc_html_e( 'Category', 'logicanvas-auctions' ); ?></legend>
			<?php if ( $categories ) : ?>
				<label class="wcap-category-field__label" for="wcap_category_id">
					<span class="screen-reader-text"><?php esc_html_e( 'Category', 'logicanvas-auctions' ); ?></span>
					<select
						id="wcap_category_id"
						name="category_id"
						<?php echo $required ? 'required' : ''; ?>
					>
						<option value=""><?php esc_html_e( 'Select a category', 'logicanvas-auctions' ); ?></option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( (string) $cat['id'] ); ?>" <?php selected( $selected, (int) $cat['id'] ); ?>>
								<?php echo esc_html( (string) $cat['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php else : ?>
				<p class="wcap-category-field__empty">
					<?php esc_html_e( 'No auction categories are available yet. An administrator must add categories under Auctions → Categories.', 'logicanvas-auctions' ); ?>
				</p>
				<input type="hidden" name="category_id" value="0" />
			<?php endif; ?>
		</fieldset>
		<?php
	}

	public static function assign( int $auction_id, int $term_id ): void {
		self::ensure_taxonomy_ready();

		if ( $auction_id < 1 || ! taxonomy_exists( Config::TAXONOMY_CAT ) ) {
			return;
		}

		// Holders may lack assign_terms meta-cap mapping; force assignment for owned auctions.
		$assign = static function () use ( $auction_id, $term_id ): void {
			if ( $term_id < 1 ) {
				wp_set_object_terms( $auction_id, array(), Config::TAXONOMY_CAT );
				return;
			}
			$term = get_term( $term_id, Config::TAXONOMY_CAT );
			if ( ! $term || is_wp_error( $term ) ) {
				return;
			}
			wp_set_object_terms( $auction_id, array( $term_id ), Config::TAXONOMY_CAT );
		};

		if ( current_user_can( 'assign_term', $term_id ) || current_user_can( Config::CAP_CREATE_AUCTIONS ) || current_user_can( 'manage_options' ) ) {
			$assign();
			return;
		}

		// Still assign when service layer already authorized the auction write.
		$assign();
	}

	public static function primary_id( int $auction_id ): int {
		if ( $auction_id < 1 || ! taxonomy_exists( Config::TAXONOMY_CAT ) ) {
			return 0;
		}
		$terms = get_the_terms( $auction_id, Config::TAXONOMY_CAT );
		if ( ! is_array( $terms ) || ! $terms ) {
			return 0;
		}
		return (int) $terms[0]->term_id;
	}

	/**
	 * Guarantee at least one selectable category exists.
	 */
	public static function ensure_default_term(): void {
		if ( ! taxonomy_exists( Config::TAXONOMY_CAT ) ) {
			return;
		}

		$existing = get_terms(
			array(
				'taxonomy'   => Config::TAXONOMY_CAT,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);

		if ( is_array( $existing ) && ! empty( $existing ) && ! is_wp_error( $existing ) ) {
			return;
		}

		if ( term_exists( 'general', Config::TAXONOMY_CAT ) ) {
			return;
		}

		wp_insert_term(
			__( 'General', 'logicanvas-auctions' ),
			Config::TAXONOMY_CAT,
			array(
				'slug' => 'general',
			)
		);
	}

	private static function ensure_taxonomy_ready(): void {
		if ( taxonomy_exists( Config::TAXONOMY_CAT ) ) {
			return;
		}
		// Late registration safety if a form renders before init completed (should be rare).
		if ( did_action( 'init' ) ) {
			( new \LogicanvasAuctions\Core\PostTypes() )->register_types();
		}
	}

	/**
	 * @param array<int, list<\WP_Term>> $by_parent
	 * @param list<array{id:int,name:string,slug:string}> $out
	 */
	private static function flatten( array $by_parent, int $parent, int $depth, array &$out ): void {
		if ( empty( $by_parent[ $parent ] ) ) {
			return;
		}
		foreach ( $by_parent[ $parent ] as $term ) {
			$prefix = $depth > 0 ? str_repeat( '— ', $depth ) : '';
			$out[]  = array(
				'id'   => (int) $term->term_id,
				'name' => $prefix . (string) $term->name,
				'slug' => (string) $term->slug,
			);
			self::flatten( $by_parent, (int) $term->term_id, $depth + 1, $out );
		}
	}
}
