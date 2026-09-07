<?php
/**
 * Custom post type and taxonomy.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Core;

use LogicanvasAuctions\Admin\PermalinkSettings;
use LogicanvasAuctions\Config;

final class PostTypes {

	public function register(): void {
		add_action( 'init', array( $this, 'register_types' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
	}

	/**
	 * Force the Classic Editor (TinyMCE) for auctions — not Gutenberg.
	 *
	 * @param bool   $use       Whether to use the block editor.
	 * @param string $post_type Post type.
	 */
	public function disable_block_editor( bool $use, string $post_type ): bool {
		if ( Config::CPT === $post_type ) {
			return false;
		}

		return $use;
	}

	public function register_types(): void {
		$auction_base  = PermalinkSettings::auction_base();
		$category_base = PermalinkSettings::category_base();

		register_post_type(
			Config::CPT,
			array(
				'labels'              => array(
					'name'               => __( 'Auctions', 'logicanvas-auctions' ),
					'singular_name'      => __( 'Auction', 'logicanvas-auctions' ),
					'add_new'            => __( 'Add Auction', 'logicanvas-auctions' ),
					'add_new_item'       => __( 'Add Auction', 'logicanvas-auctions' ),
					'edit_item'          => __( 'Edit Auction', 'logicanvas-auctions' ),
					'new_item'           => __( 'New Auction', 'logicanvas-auctions' ),
					'view_item'          => __( 'View Auction', 'logicanvas-auctions' ),
					'search_items'       => __( 'Search Auctions', 'logicanvas-auctions' ),
					'not_found'          => __( 'No auctions found.', 'logicanvas-auctions' ),
					'not_found_in_trash' => __( 'No auctions found in Trash.', 'logicanvas-auctions' ),
					'menu_name'          => __( 'Auctions', 'logicanvas-auctions' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => $auction_base,
					'with_front' => false,
				),
				// Classic editor only (block editor disabled via use_block_editor_for_post_type).
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'author' ),
				'taxonomies'          => array( Config::TAXONOMY_CAT ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => false,
			)
		);

		register_taxonomy(
			Config::TAXONOMY_CAT,
			Config::CPT,
			array(
				'labels'            => array(
					'name'              => __( 'Auction Categories', 'logicanvas-auctions' ),
					'singular_name'     => __( 'Auction Category', 'logicanvas-auctions' ),
					'search_items'      => __( 'Search Categories', 'logicanvas-auctions' ),
					'all_items'         => __( 'All Categories', 'logicanvas-auctions' ),
					'parent_item'       => __( 'Parent Category', 'logicanvas-auctions' ),
					'parent_item_colon' => __( 'Parent Category:', 'logicanvas-auctions' ),
					'edit_item'         => __( 'Edit Category', 'logicanvas-auctions' ),
					'update_item'       => __( 'Update Category', 'logicanvas-auctions' ),
					'add_new_item'      => __( 'Add New Category', 'logicanvas-auctions' ),
					'new_item_name'     => __( 'New Category Name', 'logicanvas-auctions' ),
					'menu_name'         => __( 'Categories', 'logicanvas-auctions' ),
					'not_found'         => __( 'No categories found.', 'logicanvas-auctions' ),
					'view_item'         => __( 'View Category', 'logicanvas-auctions' ),
					'back_to_items'     => __( '← Back to Categories', 'logicanvas-auctions' ),
				),
				'public'            => true,
				'publicly_queryable'=> true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'show_tagcloud'     => false,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'         => $category_base,
					'with_front'   => false,
					'hierarchical' => true,
				),
				'capabilities'      => array(
					'manage_terms' => Config::CAP_MODERATE_AUCTIONS,
					'edit_terms'   => Config::CAP_MODERATE_AUCTIONS,
					'delete_terms' => Config::CAP_MODERATE_AUCTIONS,
					'assign_terms' => Config::CAP_EDIT_OWN_AUCTIONS,
				),
			)
		);
	}
}
