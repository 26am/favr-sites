<?php
/**
 * The Editor menu: Favr's order in three groups, sentence-case labels, nothing else.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Skin\Skin;

/**
 * Allow-list: entries not listed here are hidden for Editors, so new plugins can't add clutter.
 * Hidden is not locked: screens stay reachable if the Editor has the capability.
 *
 * The menu reads Dashboard, Approvals, then "Website" (the public site), "Organization" (the
 * records behind it) and the Editor's own profile. Each group starts with a separator entry that
 * the skin (assets/skin/base.css) draws as a heading; the words are printed as inline CSS so they
 * stay translatable. A group with no entry has no heading.
 */
final class Menu {

	/** Above the groups: home and the inbox. */
	public const TOP = array(
		'index.php',
		'favr-approvals',
	);

	/** Group name => its entries, in order. */
	public const GROUPS = array(
		'website'      => array(
			'edit.php?post_type=page',
			'edit.php',
			'favr-menus',
			'favr-banner',
			'upload.php',
		),
		'organization' => array(
			'edit.php?post_type=favr_member',
			'edit.php?post_type=favr_business',
			'edit.php?post_type=favr_event',
			'edit.php?post_type=favr_document',
			'edit.php?post_type=favr_person',
			'edit.php?post_type=favr_sponsor',
		),
		'account'      => array(
			'profile.php',
		),
	);

	/** Slug prefix of a group's separator. WordPress treats any slug starting "separator" as one. */
	private const SEPARATOR = 'separator-favr-';

	/** Label filter => the method with Favr's words for it. */
	public const LABELS = array(
		'post_type_labels_post'       => 'newsLabels',
		'post_type_labels_page'       => 'pageLabels',
		'post_type_labels_attachment' => 'mediaLabels',
		'taxonomy_labels_category'    => 'categoryLabels',
		'taxonomy_labels_post_tag'    => 'tagLabels',
	);

	/** Submenu labels by parent slug, then by the entry's own slug. */
	private const SUBMENU = array(
		'edit.php'                => array(
			'edit.php'     => 'news_all',
			'post-new.php' => 'news_add',
		),
		'edit.php?post_type=page' => array(
			'edit.php?post_type=page'     => 'pages_all',
			'post-new.php?post_type=page' => 'pages_add',
		),
		'upload.php'              => array(
			'upload.php'    => 'media_all',
			'media-new.php' => 'media_add',
		),
	);

	/**
	 * Which entries stay (in Favr order, each group behind its separator) and which go.
	 *
	 * @param list<string> $slugs Current top-level slugs.
	 * @return array{keep: list<string>, remove: list<string>}
	 */
	public static function arrange( array $slugs ): array {
		$keep  = array_values( array_intersect( self::TOP, $slugs ) );
		$known = self::TOP;
		foreach ( self::GROUPS as $group => $entries ) {
			$known   = array_merge( $known, array( self::SEPARATOR . $group ), $entries );
			$present = array_values( array_intersect( $entries, $slugs ) );
			if ( $present ) {
				$keep = array_merge( $keep, array( self::SEPARATOR . $group ), $present );
			}
		}
		return array(
			'keep'   => $keep,
			'remove' => array_values( array_diff( $slugs, $known ) ),
		);
	}

	/**
	 * A group's separator as a WordPress menu entry.
	 *
	 * @param string $group Group name (a key of GROUPS).
	 * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
	 */
	public static function separator( string $group ): array {
		return array( '', 'read', self::SEPARATOR . $group, '', 'wp-menu-separator favr-menu-group favr-menu-group--' . $group );
	}

	/**
	 * The menu with exactly the group separators an order asks for: stale ones go, missing ones
	 * are added (WordPress then sorts them into place).
	 *
	 * @param array<int|string, array<int, string>> $menu $GLOBALS['menu'].
	 * @param list<string>                          $keep arrange()['keep'].
	 * @return array<int|string, array<int, string>>
	 */
	public static function withSeparators( array $menu, array $keep ): array {
		$menu = array_filter( $menu, static fn( $item ): bool => ! str_starts_with( (string) ( $item[2] ?? '' ), self::SEPARATOR ) );
		foreach ( array_keys( self::GROUPS ) as $group ) {
			if ( in_array( self::SEPARATOR . $group, $keep, true ) ) {
				$menu[] = self::separator( $group );
			}
		}
		return $menu;
	}

	/**
	 * The words above each group; the Editor's own group has none.
	 *
	 * @return array<string, string>
	 */
	public static function headings(): array {
		return array(
			'website'      => __( 'Website', 'favr-sites' ),
			'organization' => __( 'Organization', 'favr-sites' ),
			'account'      => '',
		);
	}

	/**
	 * The headings as CSS: each word becomes the `content` of its separator.
	 *
	 * @param array<string, string> $labels Group name => heading.
	 */
	public static function headingCss( array $labels ): string {
		$css = '';
		foreach ( $labels as $group => $label ) {
			if ( isset( self::GROUPS[ $group ] ) ) {
				$css .= '#adminmenu li.favr-menu-group--' . $group . '::before{content:' . self::cssString( (string) $label ) . '}';
			}
		}
		return $css;
	}

	/**
	 * Text as a quoted CSS string that is safe inside a style element. Every ASCII character
	 * other than a letter, a digit or a space is written as a CSS escape, so a translation can't
	 * end the string, the rule or the tag.
	 *
	 * @param string $text Text.
	 */
	public static function cssString( string $text ): string {
		$safe = preg_replace_callback(
			'/[^A-Za-z0-9 \x{80}-\x{10FFFF}]/u',
			static fn( array $found ): string => sprintf( '\\%x ', ord( $found[0] ) ),
			$text
		);
		return '"' . ( is_string( $safe ) ? $safe : '' ) . '"';
	}

	/**
	 * Posts are News, and the Pages and Media entries read in sentence case.
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
		$words = array(
			'news_all'  => __( 'All news', 'favr-sites' ),
			'news_add'  => __( 'Add news post', 'favr-sites' ),
			'pages_all' => __( 'All pages', 'favr-sites' ),
			'pages_add' => __( 'Add page', 'favr-sites' ),
			'media_all' => __( 'Library', 'favr-sites' ),
			'media_add' => __( 'Add media', 'favr-sites' ),
		);
		foreach ( self::SUBMENU as $parent => $entries ) {
			foreach ( $submenu[ $parent ] ?? array() as $i => $item ) {
				$slug = (string) ( $item[2] ?? '' );
				if ( isset( $entries[ $slug ] ) ) {
					$submenu[ $parent ][ $i ][0] = $words[ $entries[ $slug ] ];
				}
			}
		}
		return array( $menu, $submenu );
	}

	/**
	 * Posts are "News" wherever an Editor sees them.
	 *
	 * @return array<string, string>
	 */
	public static function newsLabels(): array {
		return array(
			'name'               => __( 'News', 'favr-sites' ),
			'singular_name'      => __( 'News post', 'favr-sites' ),
			'menu_name'          => __( 'News', 'favr-sites' ),
			'name_admin_bar'     => __( 'News post', 'favr-sites' ),
			'add_new'            => __( 'Add news post', 'favr-sites' ),
			'add_new_item'       => __( 'Add news post', 'favr-sites' ),
			'edit_item'          => __( 'Edit news post', 'favr-sites' ),
			'new_item'           => __( 'New news post', 'favr-sites' ),
			'view_item'          => __( 'View news post', 'favr-sites' ),
			'view_items'         => __( 'View news', 'favr-sites' ),
			'all_items'          => __( 'All news', 'favr-sites' ),
			'search_items'       => __( 'Search news', 'favr-sites' ),
			'not_found'          => __( 'No news posts found.', 'favr-sites' ),
			'not_found_in_trash' => __( 'No news posts found in Trash.', 'favr-sites' ),
		);
	}

	/**
	 * Pages keep their name; only the capitals change ("All pages", "Add page").
	 *
	 * @return array<string, string>
	 */
	public static function pageLabels(): array {
		return array(
			'add_new'           => __( 'Add page', 'favr-sites' ),
			'add_new_item'      => __( 'Add page', 'favr-sites' ),
			'edit_item'         => __( 'Edit page', 'favr-sites' ),
			'new_item'          => __( 'New page', 'favr-sites' ),
			'view_item'         => __( 'View page', 'favr-sites' ),
			'view_items'        => __( 'View pages', 'favr-sites' ),
			'all_items'         => __( 'All pages', 'favr-sites' ),
			'search_items'      => __( 'Search pages', 'favr-sites' ),
			'parent_item_colon' => __( 'Parent page:', 'favr-sites' ),
			'archives'          => __( 'Page archives', 'favr-sites' ),
			'attributes'        => __( 'Page attributes', 'favr-sites' ),
		);
	}

	/**
	 * Media files: "Add media", "Edit media".
	 *
	 * @return array<string, string>
	 */
	public static function mediaLabels(): array {
		return array(
			'add_new'      => __( 'Add media', 'favr-sites' ),
			'add_new_item' => __( 'Add media', 'favr-sites' ),
			'edit_item'    => __( 'Edit media', 'favr-sites' ),
			'view_item'    => __( 'View media file', 'favr-sites' ),
			'all_items'    => __( 'Media library', 'favr-sites' ),
		);
	}

	/**
	 * News categories.
	 *
	 * @return array<string, string>
	 */
	public static function categoryLabels(): array {
		return array(
			'all_items'         => __( 'All categories', 'favr-sites' ),
			'add_new_item'      => __( 'Add category', 'favr-sites' ),
			'edit_item'         => __( 'Edit category', 'favr-sites' ),
			'view_item'         => __( 'View category', 'favr-sites' ),
			'update_item'       => __( 'Update category', 'favr-sites' ),
			'new_item_name'     => __( 'New category name', 'favr-sites' ),
			'search_items'      => __( 'Search categories', 'favr-sites' ),
			'parent_item'       => __( 'Parent category', 'favr-sites' ),
			'parent_item_colon' => __( 'Parent category:', 'favr-sites' ),
			'most_used'         => __( 'Most used', 'favr-sites' ),
			'back_to_items'     => __( '← Go to categories', 'favr-sites' ),
		);
	}

	/**
	 * News tags.
	 *
	 * @return array<string, string>
	 */
	public static function tagLabels(): array {
		return array(
			'all_items'     => __( 'All tags', 'favr-sites' ),
			'add_new_item'  => __( 'Add tag', 'favr-sites' ),
			'edit_item'     => __( 'Edit tag', 'favr-sites' ),
			'view_item'     => __( 'View tag', 'favr-sites' ),
			'update_item'   => __( 'Update tag', 'favr-sites' ),
			'new_item_name' => __( 'New tag name', 'favr-sites' ),
			'search_items'  => __( 'Search tags', 'favr-sites' ),
			'popular_items' => __( 'Popular tags', 'favr-sites' ),
			'most_used'     => __( 'Most used', 'favr-sites' ),
			'back_to_items' => __( '← Go to tags', 'favr-sites' ),
		);
	}

	/**
	 * WordPress's own words on the Media screens (it has no label for them) => Favr's, so the
	 * screen says what the menu says.
	 *
	 * @return array<string, string>
	 */
	public static function mediaWords(): array {
		return array(
			'Media Library'    => __( 'Media library', 'favr-sites' ),
			'Add Media File'   => __( 'Add media', 'favr-sites' ),
			'Upload Media'     => __( 'Add media', 'favr-sites' ),
			'Upload New Media' => __( 'Add media', 'favr-sites' ), // The same heading in older WordPress.
			'Search Media'     => __( 'Search media', 'favr-sites' ),
		);
	}

	/**
	 * One of WordPress's own phrases in Favr's words; anything else as it was.
	 *
	 * @param string                $translation What would be shown.
	 * @param string                $text        The phrase in the code.
	 * @param string                $domain      Whose phrase it is ('default' is WordPress).
	 * @param array<string, string> $words       Phrase in the code => Favr's words.
	 */
	public static function reword( string $translation, string $text, string $domain, array $words ): string {
		return 'default' === $domain && isset( $words[ $text ] ) ? $words[ $text ] : $translation;
	}

	/** Hooks. The label filters also run on the public site, where the toolbar uses them. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), PHP_INT_MAX );
		add_filter( 'custom_menu_order', array( $this, 'custom' ) );
		add_filter( 'menu_order', array( $this, 'order' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'headingStyles' ), 21 ); // After Skin\Skin (20) registers the sheet.
		add_action( 'load-upload.php', array( $this, 'mediaScreen' ) );
		add_action( 'load-media-new.php', array( $this, 'mediaScreen' ) );
		foreach ( array_keys( self::LABELS ) as $filter ) {
			add_filter( $filter, array( $this, 'labels' ) );
		}
	}

	/** Remove what isn't allowed; rename Posts, Pages and Media entries. */
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
	 * Favr order for Editors. WordPress asks for the order after it has dropped the entries this
	 * user may not open, so this is the first moment the groups that need a heading are known:
	 * their separators are put into the menu here, and WordPress sorts them into place.
	 *
	 * @param array<string> $order Current order.
	 * @return array<string>
	 */
	public function order( $order ) {
		global $menu;
		if ( ! Audience::current() || ! is_array( $order ) ) {
			return $order;
		}
		$keep = self::arrange( $order )['keep'];
		if ( is_array( $menu ) ) {
			$menu = self::withSeparators( $menu, $keep ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		return $keep;
	}

	/** Print the group headings on the skin's sheet. */
	public function headingStyles(): void {
		if ( Audience::current() ) {
			wp_add_inline_style( Skin::HANDLE, self::headingCss( self::headings() ) );
		}
	}

	/** The Media screens' title, button and search in the menu's words. */
	public function mediaScreen(): void {
		if ( ! Audience::current() ) {
			return;
		}
		$words = self::mediaWords();
		add_filter( 'gettext', static fn( $translation, $text, $domain ) => self::reword( (string) $translation, (string) $text, (string) $domain, $words ), 10, 3 );
	}

	/**
	 * Favr's words for posts, pages, media and the News categories and tags wherever an Editor
	 * sees them.
	 *
	 * @param object $labels Labels.
	 * @return object
	 */
	public function labels( $labels ) {
		$method = self::LABELS[ (string) current_filter() ] ?? '';
		if ( '' === $method || ! is_object( $labels ) || ! Audience::current() ) {
			return $labels;
		}
		foreach ( self::$method() as $key => $label ) {
			$labels->$key = $label;
		}
		return $labels;
	}
}
