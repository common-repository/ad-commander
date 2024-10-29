<?php
namespace ADCmdr;

/**
 * Register PostTypes and Taxonomies
 */
class PostTypes {

	/**
	 * Create hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), apply_filters( 'adcmdr_register_posttypes_taxonomies_priority', 10 ) );
	}

	/**
	 * Register on init
	 *
	 * @return void
	 */
	public function register() {
		$this->register_post_type_ads();
		$this->register_tax_groups();
	}

	/**
	 * Register post types
	 *
	 * @return void
	 */
	public function register_post_type_ads() {
		/**
		 * Ads
		 */
			$labels       = array(
				'name'                  => AdCommander::title(),
				'singular_name'         => AdCommander::title(),
				'menu_name'             => AdCommander::menu_title(),
				'name_admin_bar'        => AdCommander::title(),
				'archives'              => __( 'Ad Archives', 'ad-commander' ),
				'attributes'            => false,
				'parent_item_colon'     => __( 'New Ad', 'ad-commander' ),
				'all_items'             => __( 'Manage Ads', 'ad-commander' ),
				'add_new_item'          => __( 'Create New Ad', 'ad-commander' ),
				'add_new'               => __( 'Add New', 'ad-commander' ),
				'new_item'              => __( 'New Ad', 'ad-commander' ),
				'edit_item'             => __( 'Edit Ad', 'ad-commander' ),
				'update_item'           => __( 'Update Ad', 'ad-commander' ),
				'view_item'             => __( 'View Ad', 'ad-commander' ),
				'view_items'            => __( 'View Ads', 'ad-commander' ),
				'search_items'          => __( 'Search Ads', 'ad-commander' ),
				'not_found'             => __( 'Not found', 'ad-commander' ),
				'not_found_in_trash'    => __( 'Not found in Trash', 'ad-commander' ),
				'featured_image'        => __( 'Banner Image', 'ad-commander' ),
				'set_featured_image'    => __( 'Set banner image', 'ad-commander' ),
				'remove_featured_image' => __( 'Remove banner image', 'ad-commander' ),
				'use_featured_image'    => __( 'Use as banner image', 'ad-commander' ),
				'insert_into_item'      => __( 'Insert into ad', 'ad-commander' ),
				'uploaded_to_this_item' => __( 'Upload this ad', 'ad-commander' ),
				'items_list'            => __( 'Ad list', 'ad-commander' ),
				'items_list_navigation' => __( 'Ads list navigation', 'ad-commander' ),
				'filter_items_list'     => __( 'Filter ads list', 'ad-commander' ),
			);
			$capabilities = array(
				'edit_post'          => AdCommander::capability(),
				// 'read_post'          => 'read_post',
				'delete_post'        => AdCommander::capability(),
				'edit_posts'         => AdCommander::capability(),
				'edit_others_posts'  => AdCommander::capability(),
				'publish_posts'      => AdCommander::capability(),
				'read_private_posts' => AdCommander::capability(),
			);
			$args         = array(
				'label'               => __( 'Ad', 'ad-commander' ),
				'description'         => __( 'An individual advertisement', 'ad-commander' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'thumbnail', 'author' ),
				'taxonomies'          => array( AdCommander::tax_group() ),
				'hierarchical'        => false,
				'public'              => false,
				'show_in_rest'        => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'menu_position'       => 30,
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capabilities'        => $capabilities,
			);
			register_post_type( AdCommander::posttype_ad(), $args );

			/**
			 * Placements
			 */
			$labels       = array(
				'name'                  => _x( 'Automatic Placements', 'Post Type General Name', 'ad-commander' ),
				'singular_name'         => _x( 'Automatic Placement', 'Post Type Singular Name', 'ad-commander' ),
				'menu_name'             => __( 'Automatic Placements', 'ad-commander' ),
				'name_admin_bar'        => __( 'Automatic Placements', 'ad-commander' ),
				'archives'              => __( 'Automatic Placement Archives', 'ad-commander' ),
				'attributes'            => false,
				'parent_item_colon'     => __( 'New Placement', 'ad-commander' ),
				'all_items'             => __( 'Manage Placements', 'ad-commander' ),
				'add_new_item'          => __( 'Create New Placement', 'ad-commander' ),
				'add_new'               => __( 'Add New', 'ad-commander' ),
				'new_item'              => __( 'New Placement', 'ad-commander' ),
				'edit_item'             => __( 'Edit Placement', 'ad-commander' ),
				'update_item'           => __( 'Update Placement', 'ad-commander' ),
				'view_item'             => __( 'View Placement', 'ad-commander' ),
				'view_items'            => __( 'View Placements', 'ad-commander' ),
				'search_items'          => __( 'Search Placements', 'ad-commander' ),
				'not_found'             => __( 'Not found', 'ad-commander' ),
				'not_found_in_trash'    => __( 'Not found in Trash', 'ad-commander' ),
				'insert_into_item'      => __( 'Insert into placement', 'ad-commander' ),
				'uploaded_to_this_item' => __( 'Upload this placement', 'ad-commander' ),
				'items_list'            => __( 'Placement list', 'ad-commander' ),
				'items_list_navigation' => __( 'Placements list navigation', 'ad-commander' ),
				'filter_items_list'     => __( 'Filter Placements list', 'ad-commander' ),
			);
			$capabilities = array(
				'edit_post'          => AdCommander::capability(),
				// 'read_post'          => 'read_post',
				'delete_post'        => AdCommander::capability(),
				'edit_posts'         => AdCommander::capability(),
				'edit_others_posts'  => AdCommander::capability(),
				'publish_posts'      => AdCommander::capability(),
				'read_private_posts' => AdCommander::capability(),
			);
			$args         = array(
				'label'               => __( 'Automatic Placement', 'ad-commander' ),
				'description'         => __( 'Automatic placement of ads or groups.', 'ad-commander' ),
				'labels'              => $labels,
				'supports'            => array( 'title' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_in_rest'        => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capabilities'        => $capabilities,
			);
			register_post_type( AdCommander::posttype_placement(), $args );
	}

	/**
	 * Register taxonomies
	 *
	 * @return void
	 */
	public function register_tax_groups() {
		$labels       = array(
			'name'                       => _x( 'Ad Groups', 'Taxonomy General Name', 'ad-commander' ),
			'singular_name'              => _x( 'Ad Group', 'Taxonomy Singular Name', 'ad-commander' ),
			'menu_name'                  => __( 'Ad Groups', 'ad-commander' ),
			'all_items'                  => __( 'All Groups', 'ad-commander' ),
			'parent_item'                => __( 'Parent Group', 'ad-commander' ),
			'parent_item_colon'          => __( 'Parent Group:', 'ad-commander' ),
			'new_item_name'              => __( 'New Group Name', 'ad-commander' ),
			'add_new_item'               => __( 'Add New Group', 'ad-commander' ),
			'edit_item'                  => __( 'Edit Group', 'ad-commander' ),
			'update_item'                => __( 'Update Group', 'ad-commander' ),
			'view_item'                  => __( 'View Group', 'ad-commander' ),
			'separate_items_with_commas' => __( 'Separate groups with commas. Existing groups can be edited under Ad Commander -> Manage Groups.', 'ad-commander' ),
			'add_or_remove_items'        => __( 'Add or remove items', 'ad-commander' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'ad-commander' ),
			'popular_items'              => __( 'Popular Groups', 'ad-commander' ),
			'search_items'               => __( 'Search Groups', 'ad-commander' ),
			'not_found'                  => __( 'Not Found', 'ad-commander' ),
			'no_terms'                   => __( 'No items', 'ad-commander' ),
			'items_list'                 => __( 'Groups list', 'ad-commander' ),
			'items_list_navigation'      => __( 'Groups list navigation', 'ad-commander' ),
			'name_field_description'     => __( 'The name of your group. Used for admin purposes only.', 'ad-commander' ),
			'back_to_items'              => __( '&larr; Go to Groups', 'ad-commander' ),
		);
		$capabilities = array(
			'manage_terms' => AdCommander::capability(),
			'edit_terms'   => AdCommander::capability(),
			'delete_terms' => AdCommander::capability(),
			'assign_terms' => AdCommander::capability(),
		);
		$args         = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_menu'      => false,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'rewrite'           => false,
			'capabilities'      => $capabilities,
			'show_in_rest'      => false,
		);
		register_taxonomy( AdCommander::tax_group(), array( AdCommander::posttype_ad() ), $args );
	}
}
