<?php
/**
 * Where a menu appears on the site.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Menus;

/**
 * Published Elementor documents with a Nav Menu widget pointing at the menu, plus theme menu
 * locations it's assigned to. Shown to Editors as "Shown in: …".
 */
final class Usage {

	/**
	 * Place names.
	 *
	 * @param \WP_Term $menu Menu.
	 * @return list<string>
	 */
	public static function where( \WP_Term $menu ): array {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '"menu":"' . $menu->slug . '"' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin screen, one query.
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE m.meta_key = '_elementor_data' AND m.meta_value LIKE %s AND p.post_status = 'publish' AND p.post_type <> 'revision'
				ORDER BY p.post_title LIMIT 20",
				$like
			)
		);
		$places = array();
		foreach ( $ids as $id ) {
			$places[] = get_the_title( (int) $id );
		}

		$registered = get_registered_nav_menus();
		foreach ( get_nav_menu_locations() as $location => $menu_id ) {
			if ( (int) $menu_id === (int) $menu->term_id && isset( $registered[ $location ] ) ) {
				$places[] = (string) $registered[ $location ];
			}
		}
		return array_values( array_unique( array_filter( $places ) ) );
	}
}
