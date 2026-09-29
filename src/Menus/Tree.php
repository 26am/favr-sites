<?php
/**
 * Menu rules for Favr Menus (pure).
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Menus;

/**
 * Turns WordPress nav menu items into ordered rows with levels, and a submitted list of rows into
 * the operations that make the menu match it. Editors get one dropdown level; existing deeper
 * items are kept as they are.
 */
final class Tree {

	private const SCHEMES = array( 'http', 'https', 'mailto', 'tel' );

	/**
	 * Ordered rows with levels.
	 *
	 * @param array<object> $items Nav menu items.
	 * @return list<array{id: int, level: int, title: string, url: string, type: string, object_id: int}>
	 */
	public static function rows( array $items ): array {
		usort( $items, static fn( $a, $b ): int => (int) $a->menu_order <=> (int) $b->menu_order );
		$parents = array();
		foreach ( $items as $item ) {
			$parents[ (int) $item->ID ] = (int) $item->menu_item_parent;
		}
		$rows = array();
		foreach ( $items as $item ) {
			$level  = 0;
			$parent = (int) $item->menu_item_parent;
			while ( $parent && isset( $parents[ $parent ] ) && $level < 10 ) {
				++$level;
				$parent = $parents[ $parent ];
			}
			$rows[] = array(
				'id'        => (int) $item->ID,
				'level'     => $level,
				'title'     => (string) $item->title,
				'url'       => (string) $item->url,
				'type'      => self::type( (string) $item->type, (string) $item->object ),
				'object_id' => (int) $item->object_id,
			);
		}
		return $rows;
	}

	/**
	 * Operations that make the menu match the submitted rows (in display order).
	 *
	 * @param list<array<string, mixed>> $existing  Tree::rows() of the menu.
	 * @param array<mixed>               $submitted Rows: id (0 = new), level, title, type, url, object_id.
	 * @return array{rows: list<array<string, mixed>>, delete: list<int>, errors: list<string>}
	 */
	public static function plan( array $existing, array $submitted ): array {
		$known = array();
		foreach ( $existing as $row ) {
			$known[ (int) $row['id'] ] = $row;
		}
		$rows       = array();
		$errors     = array();
		$kept       = array();
		$last_at    = array(); // Level => ref of the latest row at that level.
		$prev_level = -1;
		$new        = 0;

		foreach ( $submitted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id    = (int) ( $row['id'] ?? 0 );
			$title = self::title( $row['title'] ?? '' );
			if ( $id && ! isset( $known[ $id ] ) ) {
				continue; // Not an item of this menu.
			}

			if ( $id ) {
				$base = $known[ $id ];
				$type = (string) $base['type'];
				$url  = (string) $base['url'];
				$obj  = (int) $base['object_id'];
			} else {
				$type = 'page' === ( $row['type'] ?? '' ) ? 'page' : 'custom';
				$obj  = 'page' === $type ? (int) ( $row['object_id'] ?? 0 ) : 0;
				$url  = 'custom' === $type ? self::cleanUrl( (string) ( $row['url'] ?? '' ) ) : '';
				if ( 'page' === $type && ! $obj ) {
					continue;
				}
				if ( 'custom' === $type ) {
					if ( '' === $title ) {
						$errors[] = __( 'A link needs a label.', 'favr-sites' );
						continue;
					}
					if ( '' === $url ) {
						/* translators: %s: link label. */
						$errors[] = sprintf( __( '“%s” has an address we can’t use.', 'favr-sites' ), $title );
						continue;
					}
				}
			}

			$allowed = $id && (int) $known[ $id ]['level'] > 1 ? (int) $known[ $id ]['level'] : 1;
			$level   = max( 0, min( (int) ( $row['level'] ?? 0 ), $allowed, $prev_level + 1 ) );
			$ref     = $id ? (string) $id : 'new:' . ( ++$new );

			$rows[]            = array(
				'ref'        => $ref,
				'id'         => $id,
				'level'      => $level,
				'parent_ref' => $level > 0 ? (string) ( $last_at[ $level - 1 ] ?? '' ) : '',
				'position'   => count( $rows ) + 1,
				'title'      => $title,
				'url'        => $url,
				'type'       => $type,
				'object_id'  => $obj,
			);
			$last_at[ $level ] = $ref;
			$prev_level        = $level;
			if ( $id ) {
				$kept[] = $id;
			}
		}

		return array(
			'rows'   => $rows,
			'delete' => array_values( array_diff( array_keys( $known ), $kept ) ),
			'errors' => $errors,
		);
	}

	/**
	 * A link address Editors may use: http(s), mailto, tel, or a site path starting with "/".
	 *
	 * @param string $url Raw.
	 */
	public static function cleanUrl( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '/' ) ) {
			return ! str_starts_with( $url, '//' ) && preg_match( '#^/[^\s"\'<>\\\\]*$#', $url ) ? $url : '';
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, self::SCHEMES, true ) ) {
			return '';
		}
		return (string) esc_url_raw( $url, self::SCHEMES );
	}

	/**
	 * Published pages that aren't in any menu yet.
	 *
	 * @param list<array{id: int, title: string}> $pages        Pages.
	 * @param list<int>                           $ids_in_menus Page ids used by menu items.
	 * @return list<array{id: int, title: string}>
	 */
	public static function unplacedPages( array $pages, array $ids_in_menus ): array {
		return array_values( array_filter( $pages, static fn( array $page ): bool => ! in_array( (int) $page['id'], $ids_in_menus, true ) ) );
	}

	/**
	 * Item kind.
	 *
	 * @param string $type Nav item type.
	 * @param string $kind Nav item object (post type or taxonomy).
	 */
	private static function type( string $type, string $kind ): string {
		if ( 'post_type' === $type && 'page' === $kind ) {
			return 'page';
		}
		return 'custom' === $type ? 'custom' : 'other';
	}

	/**
	 * Plain-text title.
	 *
	 * @param mixed $title Raw.
	 */
	private static function title( $title ): string {
		return is_scalar( $title ) ? (string) sanitize_text_field( (string) $title ) : '';
	}
}
