<?php
/**
 * The Editor menu: Favr's order, Posts called News, nothing else.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * Allow-list: entries not listed here are hidden for Editors, so new plugins can't add clutter.
 * Hidden is not locked: screens stay reachable if the Editor has the capability.
 */
final class Menu {

	public const ORDER = array(
		'index.php',
		'edit.php?post_type=page',
		'edit.php',
		'favr-menus',
		'upload.php',
		'favr-approvals',
		'edit.php?post_type=favr_business',
		'edit.php?post_type=favr_event',
		'edit.php?post_type=favr_member',
		'profile.php',
	);

	/**
	 * Which entries stay (in Favr order) and which go.
	 *
	 * @param list<string> $slugs Current top-level slugs.
	 * @return array{keep: list<string>, remove: list<string>}
	 */
	public static function arrange( array $slugs ): array {
		return array(
			'keep'   => array_values( array_intersect( self::ORDER, $slugs ) ),
			'remove' => array_values( array_diff( $slugs, self::ORDER ) ),
		);
	}

	/**
	 * Posts → News in the menu arrays.
	 *
	 * @param array<int, array<int, string>>                $menu    $GLOBALS['menu'].
	 * @param array<string, array<int, array<int, string>>> $submenu $GLOBALS['submenu'].
	 * @return array{0: array<int, array<int, string>>, 1: array<string, array<int, array<int, string>>>}
	 */
	public static function relabel( array $menu, array $submenu ): array {
		foreach ( $menu as $i => $item ) {
			if ( 'edit.php' === ( $item[2] ?? '' ) ) {
				$menu[ $i ][0] = __( 'News', 'favr-sites' );
			}
		}
		foreach ( $submenu['edit.php'] ?? array() as $i => $item ) {
			if ( 'edit.php' === ( $item[2] ?? '' ) ) {
				$submenu['edit.php'][ $i ][0] = __( 'All news', 'favr-sites' );
			} elseif ( 'post-new.php' === ( $item[2] ?? '' ) ) {
				$submenu['edit.php'][ $i ][0] = __( 'Add news post', 'favr-sites' );
			}
		}
		return array( $menu, $submenu );
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), PHP_INT_MAX );
		add_filter( 'custom_menu_order', array( $this, 'custom' ) );
		add_filter( 'menu_order', array( $this, 'order' ), PHP_INT_MAX );
		add_filter( 'post_type_labels_post', array( $this, 'labels' ) );
	}

	/** Remove what isn't allowed; rename Posts. */
	public function menu(): void {
		global $menu, $submenu;
		if ( ! Audience::current() || ! is_array( $menu ) ) {
			return;
		}
		$slugs = array_values( array_filter( array_map( static fn( $item ): string => (string) ( $item[2] ?? '' ), $menu ) ) );
		foreach ( self::arrange( $slugs )['remove'] as $slug ) {
			remove_menu_page( $slug );
		}
		list( $menu, $submenu ) = self::relabel( $menu, is_array( $submenu ) ? $submenu : array() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Turn on custom ordering for Editors.
	 *
	 * @param bool $custom Custom.
	 */
	public function custom( $custom ): bool {
		return Audience::current() ? true : (bool) $custom;
	}

	/**
	 * Favr order for Editors.
	 *
	 * @param array<string> $order Current order.
	 * @return array<string>
	 */
	public function order( $order ) {
		return Audience::current() && is_array( $order ) ? self::arrange( $order )['keep'] : $order;
	}

	/**
	 * Posts are "News" wherever an Editor sees them.
	 *
	 * @param object $labels Labels.
	 * @return object
	 */
	public function labels( $labels ) {
		if ( ! Audience::current() ) {
			return $labels;
		}
		$labels->name           = __( 'News', 'favr-sites' );
		$labels->singular_name  = __( 'News post', 'favr-sites' );
		$labels->menu_name      = __( 'News', 'favr-sites' );
		$labels->name_admin_bar = __( 'News post', 'favr-sites' );
		$labels->add_new        = __( 'Add news post', 'favr-sites' );
		$labels->add_new_item   = __( 'Add news post', 'favr-sites' );
		$labels->edit_item      = __( 'Edit news post', 'favr-sites' );
		$labels->new_item       = __( 'New news post', 'favr-sites' );
		$labels->view_item      = __( 'View news post', 'favr-sites' );
		$labels->all_items      = __( 'All news', 'favr-sites' );
		$labels->search_items   = __( 'Search news', 'favr-sites' );
		$labels->not_found      = __( 'No news posts found.', 'favr-sites' );
		return $labels;
	}
}
