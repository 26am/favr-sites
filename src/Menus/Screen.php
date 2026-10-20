<?php
/**
 * Favr Menus: Editors manage the Header and Footer menus.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Menus;

use FavrSites\Editors\Routing;
use FavrSites\Site\Credit;
use FavrSites\Site\Protect;
use FavrSites\Site\Setup;

/**
 * A Favr screen (Header & footer) instead of Appearance → Menus: reorder, rename, add a page or link, remove,
 * one dropdown level. Edits the real WordPress menus connected to the Header and Footer slots, so
 * Elementor Nav Menu widgets and theme locations update on save.
 */
final class Screen {

	public const PAGE   = 'favr-menus';
	private const SAVE  = 'favr_sites_save_menu';
	private const NONCE = 'favr_sites_save_menu_';

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_' . self::SAVE, array( $this, 'save' ) );
	}

	/** Menu entry. */
	public function menu(): void {
		$hook = add_menu_page( __( 'Header & footer', 'favr-sites' ), __( 'Header & footer', 'favr-sites' ), 'edit_pages', self::PAGE, array( $this, 'render' ), 'dashicons-menu-alt3', 21 );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'load' ) );
		}
	}

	/** Assets and screen chrome. */
	public function load(): void {
		foreach ( array( 'tokens', 'dashboard' ) as $name ) {
			$file = 'assets/dashboard/' . $name . '.css';
			wp_enqueue_style( 'favr-sites-' . $name, FAVR_SITES_URL . $file, 'dashboard' === $name ? array( 'favr-sites-tokens' ) : array(), self::version( $file ) );
		}
		wp_enqueue_style( 'favr-sites-menus', FAVR_SITES_URL . 'assets/menus/menus.css', array( 'favr-sites-tokens' ), self::version( 'assets/menus/menus.css' ) );
		wp_enqueue_script( 'favr-sites-menus', FAVR_SITES_URL . 'assets/menus/menus.js', array( 'jquery', 'jquery-ui-sortable' ), self::version( 'assets/menus/menus.js' ), true );
		wp_localize_script(
			'favr-sites-menus',
			'favrMenus',
			array(
				'i18n' => array(
					'page'       => __( 'Page', 'favr-sites' ),
					'choosePage' => __( 'Choose a page to add.', 'favr-sites' ),
					'needsLabel' => __( 'Give the link a label.', 'favr-sites' ),
					'badUrl'     => __( 'Use a web address (https://…), an email (mailto:…), a phone number (tel:…) or a path on this site (/…).', 'favr-sites' ),
					'unsaved'    => __( 'You have unsaved menu changes.', 'favr-sites' ),
					'saveBoth'   => __( 'Save both menus', 'favr-sites' ),
					'inHeader'   => __( 'Unsaved changes in the header menu', 'favr-sites' ),
					'inFooter'   => __( 'Unsaved changes in the footer menu', 'favr-sites' ),
				),
			)
		);
		add_filter( 'admin_body_class', static fn( string $classes ): string => $classes . ' favr-dash-screen favr-menus-screen' );
		add_filter( 'admin_footer_text', '__return_empty_string', PHP_INT_MAX );
		add_filter( 'update_footer', '__return_empty_string', PHP_INT_MAX );
	}

	/** Page. */
	public function render(): void {
		$connected = Slots::current();
		$in_menus  = array();
		$slots     = array();
		$templates = Setup::pro() ? Protect::ids() : array();
		foreach ( Slots::SLOTS as $slot ) {
			$design = isset( $templates[ $slot ] ) ? Routing::elementorUrl( $templates[ $slot ] ) : '';
			$menu   = $connected[ $slot ] ? wp_get_nav_menu_object( $connected[ $slot ] ) : false;
			if ( ! $menu instanceof \WP_Term ) {
				$slots[ $slot ] = array(
					'menu'   => null,
					'design' => $design,
				);
				continue;
			}
			$items   = (array) wp_get_nav_menu_items( $menu->term_id );
			$invalid = array();
			foreach ( $items as $item ) {
				if ( ! empty( $item->_invalid ) ) {
					$invalid[ (int) $item->ID ] = true;
				}
				if ( 'post_type' === $item->type && 'page' === $item->object ) {
					$in_menus[] = (int) $item->object_id;
				}
			}
			$slots[ $slot ] = array(
				'menu'    => $menu,
				'rows'    => Tree::rows( $items ),
				'invalid' => $invalid,
				'usage'   => Usage::where( $menu ),
				'design'  => $design,
			);
		}

		$pages = array();
		$query = array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- every page must be pickable; sites have far fewer.
			'orderby'     => 'title',
			'order'       => 'ASC',
		);
		foreach ( get_posts( $query ) as $page ) {
			$pages[] = array(
				'id'    => (int) $page->ID,
				'title' => '' !== $page->post_title ? $page->post_title : __( '(no title)', 'favr-sites' ),
			);
		}

		$errors = get_transient( self::errorsKey() );
		delete_transient( self::errorsKey() );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags.
		$view = array(
			'slots'        => $slots,
			'pages'        => $pages,
			'unplaced'     => Tree::unplacedPages( $pages, $in_menus ),
			'saved'        => self::savedSlots( isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '' ),
			'errors'       => self::errorsBySlot( $errors ),
			'action'       => self::SAVE,
			'nonce'        => self::NONCE,
			// The footer credit's switch (FavrSites\Site\Credit) lives on this screen too.
			'credit'       => Credit::on(),
			'credit_saved' => isset( $_GET['credit'] ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		include FAVR_SITES_PATH . 'templates/menus.php';
	}

	/**
	 * The menus one Save press carries: the pressed menu's rows and, each proven by its own nonce,
	 * the other menu's unsaved rows. So "Save footer menu" never throws away a change made to the
	 * header a moment before.
	 *
	 * @param int                  $menu_id The pressed menu.
	 * @param array<string, mixed> $post    The submitted fields, unslashed.
	 * @param array<string, ?int>  $slots   Slot => connected menu id.
	 * @return array<string, string> Slot => rows as JSON, the pressed menu first.
	 */
	public static function batch( int $menu_id, array $post, array $slots ): array {
		$pressed = array_search( $menu_id, $slots, true );
		if ( ! is_string( $pressed ) ) {
			return array();
		}
		$out  = array( $pressed => is_string( $post['rows'] ?? null ) ? $post['rows'] : '' );
		$also = is_array( $post['also'] ?? null ) ? $post['also'] : array();
		foreach ( $also as $id => $extra ) {
			$slot = array_search( (int) $id, $slots, true );
			if ( ! is_string( $slot ) || isset( $out[ $slot ] ) || ! is_array( $extra ) || ! is_string( $extra['rows'] ?? null ) ) {
				continue;
			}
			if ( ! wp_verify_nonce( (string) ( $extra['nonce'] ?? '' ), self::NONCE . (int) $id ) ) {
				continue;
			}
			$out[ $slot ] = $extra['rows'];
		}
		return $out;
	}

	/**
	 * The "saved" flag for the address after a save: "header", "footer" or "header-footer".
	 *
	 * @param list<string> $saved Saved slots.
	 */
	public static function savedFlag( array $saved ): string {
		return implode( '-', array_values( array_intersect( Slots::SLOTS, $saved ) ) );
	}

	/**
	 * The slots a "saved" flag names.
	 *
	 * @param string $flag Flag from the address.
	 * @return list<string>
	 */
	public static function savedSlots( string $flag ): array {
		return array_values( array_intersect( Slots::SLOTS, explode( '-', $flag ) ) );
	}

	/**
	 * The confirmation after a save. It names every menu that went live, because it is printed in
	 * the card whose button was pressed.
	 *
	 * @param list<string> $saved Saved slots.
	 */
	public static function savedNotice( array $saved ): string {
		if ( count( $saved ) > 1 ) {
			return __( 'Header and footer menus saved. They’re live on the site.', 'favr-sites' );
		}
		if ( ! $saved ) {
			return '';
		}
		/* translators: %s: "Header menu" or "Footer menu". */
		return sprintf( __( '%s saved. It’s live on the site.', 'favr-sites' ), Slots::label( (string) reset( $saved ) ) );
	}

	/**
	 * Save errors by slot, from the stored value.
	 *
	 * @param mixed $stored Stored errors.
	 * @return array<string, list<string>>
	 */
	public static function errorsBySlot( $stored ): array {
		$out = array();
		foreach ( is_array( $stored ) ? $stored : array() as $slot => $errors ) {
			if ( in_array( $slot, Slots::SLOTS, true ) && is_array( $errors ) && $errors ) {
				$out[ $slot ] = array_values( array_map( 'strval', $errors ) );
			}
		}
		return $out;
	}

	/** Save the pressed menu, and the other one when it has unsaved changes too. */
	public function save(): void {
		$menu_id = isset( $_POST['menu_id'] ) ? absint( wp_unslash( $_POST['menu_id'] ) ) : 0;
		check_admin_referer( self::NONCE . $menu_id );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit menus.', 'favr-sites' ), 403 );
		}
		$slots = Slots::current();
		$slot  = $menu_id ? array_search( $menu_id, $slots, true ) : false;
		if ( ! is_string( $slot ) ) {
			wp_die( esc_html__( 'That menu isn’t one of this site’s Favr menus.', 'favr-sites' ), 403 );
		}

		$saved  = array();
		$errors = array();
		foreach ( self::batch( $menu_id, wp_unslash( $_POST ), $slots ) as $name => $json ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; every field is cleaned by Tree::plan().
			$failed = self::saveMenu( (int) $slots[ $name ], $json );
			if ( $failed ) {
				$errors[ $name ] = $failed;
			} else {
				$saved[] = $name;
			}
		}

		// Elementor caches rendered widgets; Nav Menu widgets must show the new menu.
		if ( $saved && did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		self::back( $slot, $saved, $errors );
	}

	/**
	 * Save one menu from its submitted rows.
	 *
	 * @param int    $menu_id Menu.
	 * @param string $json    Rows as JSON.
	 * @return list<string> Errors; empty when the menu was saved.
	 */
	private static function saveMenu( int $menu_id, string $json ): array {
		$submitted = json_decode( $json, true );
		if ( ! is_array( $submitted ) ) {
			return array( __( 'Nothing was saved. Reload the page and try again.', 'favr-sites' ) );
		}

		$items = array();
		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
			$items[ (int) $item->ID ] = $item;
		}
		$plan = Tree::plan( Tree::rows( array_values( $items ) ), $submitted );
		if ( $plan['errors'] ) {
			return array_values( $plan['errors'] );
		}

		foreach ( $plan['delete'] as $id ) {
			if ( isset( $items[ $id ] ) ) {
				wp_delete_post( $id, true );
			}
		}
		$ids    = array(); // Row ref => saved item id.
		$failed = false;
		foreach ( $plan['rows'] as $row ) {
			$args = self::args( $row, $items[ $row['id'] ] ?? null, $ids );
			if ( null === $args ) {
				continue;
			}
			$saved = wp_update_nav_menu_item( $menu_id, (int) $row['id'], wp_slash( $args ) ); // Core unslashes post fields and meta.
			if ( is_wp_error( $saved ) ) {
				$failed = true;
				continue;
			}
			$ids[ $row['ref'] ] = (int) $saved;
		}

		// Page caches purge on this, as they do when a menu is saved in Appearance → Menus.
		$menu = wp_get_nav_menu_object( $menu_id );
		/** This action is documented in wp-includes/nav-menu.php */
		do_action( 'wp_update_nav_menu', $menu_id, array( 'menu-name' => $menu ? $menu->name : '' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.

		return $failed ? array( __( 'Some items couldn’t be saved. Check the menu and try again.', 'favr-sites' ) ) : array();
	}

	/**
	 * Item data for wp_update_nav_menu_item(): an existing item keeps everything it has (target,
	 * classes, description…); only its title, position and parent change.
	 *
	 * @param array<string, mixed> $row  Planned row.
	 * @param \WP_Post|null        $item Existing item.
	 * @param array<string, int>   $ids  Saved ids by ref.
	 * @return array<string, mixed>|null Null to skip the row.
	 */
	private static function args( array $row, ?\WP_Post $item, array $ids ): ?array {
		if ( $item ) {
			if ( 'post_type' === $item->type && ! get_post( (int) $item->object_id ) ) {
				return null; // Its page is gone; leave it to WordPress.
			}
			$args = array(
				'menu-item-object-id'   => (int) $item->object_id,
				'menu-item-object'      => (string) $item->object,
				'menu-item-type'        => (string) $item->type,
				'menu-item-url'         => (string) $item->url,
				'menu-item-description' => (string) $item->post_content,
				'menu-item-attr-title'  => (string) $item->post_excerpt,
				'menu-item-target'      => (string) $item->target,
				'menu-item-classes'     => implode( ' ', array_filter( (array) $item->classes ) ),
				'menu-item-xfn'         => (string) $item->xfn,
			);
		} elseif ( 'page' === $row['type'] ) {
			$page = get_post( (int) $row['object_id'] );
			if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
				return null;
			}
			$args = array(
				'menu-item-object-id' => (int) $page->ID,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
			);
		} else {
			$args = array(
				'menu-item-object' => 'custom',
				'menu-item-type'   => 'custom',
				'menu-item-url'    => (string) $row['url'],
			);
		}

		$title = (string) $row['title'];
		if ( $item && ! empty( $row['keep_title'] ) ) {
			$title = (string) $item->post_title; // Unedited: exactly as stored (blank follows the page title).
		} elseif ( 'post_type' === $args['menu-item-type'] ) {
			$title = self::syncedTitle( $title, (int) $args['menu-item-object-id'] );
		} elseif ( '' === $title && $item ) {
			$title = (string) $item->post_title; // A link keeps its label.
		}

		return $args + array(
			'menu-item-title'     => $title,
			'menu-item-position'  => (int) $row['position'],
			'menu-item-parent-id' => '' !== $row['parent_ref'] ? (int) ( $ids[ $row['parent_ref'] ] ?? 0 ) : 0,
			'menu-item-status'    => 'publish',
		);
	}

	/**
	 * A page item whose label matches the page title stays synced (WordPress stores it blank).
	 *
	 * @param string $title   Submitted label.
	 * @param int    $post_id Linked post.
	 */
	private static function syncedTitle( string $title, int $post_id ): string {
		$raw   = (string) get_post_field( 'post_title', $post_id );
		$shown = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
		return '' === $title || $title === $shown || $title === $raw ? $raw : $title;
	}

	/**
	 * Back to the screen, at the card whose button was pressed, with what was saved and any errors.
	 *
	 * @param string                      $slot   The pressed slot.
	 * @param list<string>                $saved  Saved slots.
	 * @param array<string, list<string>> $errors Errors by slot.
	 */
	private static function back( string $slot, array $saved, array $errors ): never {
		if ( $errors ) {
			set_transient( self::errorsKey(), $errors, 5 * MINUTE_IN_SECONDS );
		}
		$flag = self::savedFlag( $saved );
		$url  = add_query_arg(
			array(
				'page'  => self::PAGE,
				'saved' => '' !== $flag ? $flag : false,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url . '#favr-menu-' . $slot );
		exit;
	}

	/** Per-user key for save errors. */
	private static function errorsKey(): string {
		return 'favr_sites_menu_errors_' . get_current_user_id();
	}

	/**
	 * Cache-busting version for an asset.
	 *
	 * @param string $file Path from the plugin root.
	 */
	private static function version( string $file ): string {
		return FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file );
	}
}
