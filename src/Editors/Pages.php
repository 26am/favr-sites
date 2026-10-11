<?php
/**
 * Adding and renaming pages by name, for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * For Editors, when Elementor runs: "Add page" first asks for the page's name on a small Favr
 * screen, then creates the page with that name and address and opens Elementor (no more drafts
 * called "Elementor #4413"). Under each page they may edit, "Rename" opens the same small screen
 * with the name and the address. Locked pages ("Managed by Favr") are refused.
 */
final class Pages {

	/** The row action's key and the screen's action in post.php. */
	public const RENAME = 'favr-rename';

	/** Longest name kept. */
	public const NAME_MAX = 120;

	/** The id of the two small screens. */
	public const SCREEN = 'favr-page-name';

	private const ADD_ACTION    = 'favr_sites_add_page';
	private const RENAME_ACTION = 'favr_sites_rename_page';
	private const BUILDER_ROUTE = '/elementor/v1/site-navigation/add-new-post';
	private const PROBLEM       = 'favr-problem';
	private const RENAMED       = 'favr-renamed';

	/**
	 * Pure: a page name as plain text on one line, cut at NAME_MAX.
	 *
	 * @param string $raw What was typed.
	 */
	public static function cleanName( string $raw ): string {
		$name = (string) sanitize_text_field( $raw );
		return function_exists( 'mb_substr' ) ? trim( mb_substr( $name, 0, self::NAME_MAX ) ) : trim( substr( $name, 0, self::NAME_MAX ) );
	}

	/**
	 * Pure: the last part of a page's address, from what was typed (a whole pasted address keeps
	 * its last part) or else from the name.
	 *
	 * @param string                   $raw     What was typed.
	 * @param string                   $name    The page's name.
	 * @param callable(string): string $slugify Turns text into an address part (sanitize_title).
	 */
	public static function cleanAddress( string $raw, string $name, callable $slugify ): string {
		$part = trim( (string) preg_replace( '/[?#].*$/', '', trim( $raw ) ), "/ \t\n\r" );
		$cut  = strrpos( $part, '/' );
		if ( false !== $cut ) {
			$part = substr( $part, $cut + 1 );
		}
		$slug = (string) $slugify( $part );
		return '' !== $slug ? $slug : (string) $slugify( $name );
	}

	/**
	 * Pure: is "Rename" offered for this post?
	 *
	 * @param string $post_type Post type.
	 * @param bool   $can_edit  The user may edit it.
	 * @param bool   $locked    It is locked ("Managed by Favr").
	 */
	public static function offersRename( string $post_type, bool $can_edit, bool $locked ): bool {
		return 'page' === $post_type && $can_edit && ! $locked;
	}

	/**
	 * Pure: a page's row actions with "Rename" first.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param string                $link    The Rename link.
	 * @return array<string, string>
	 */
	public static function withRename( array $actions, string $link ): array {
		unset( $actions[ self::RENAME ] );
		return array( self::RENAME => $link ) + $actions;
	}

	/**
	 * Pure: what went wrong, in plain words ('' for nothing or an unknown code).
	 *
	 * @param string $code Code from the address.
	 */
	public static function problem( string $code ): string {
		if ( 'name' === $code ) {
			return __( 'Give the page a name.', 'favr-sites' );
		}
		if ( 'failed' === $code ) {
			return __( 'The page could not be saved. Please try again.', 'favr-sites' );
		}
		return '';
	}

	/**
	 * Pure: is this a name the builder made up for a page nobody named ("Elementor Page #4449")?
	 *
	 * @param string $title Page title.
	 */
	public static function isBuilderName( string $title ): bool {
		return 1 === preg_match( '/^Elementor( \S.*)? #\d+$/', $title );
	}

	/** Where "Add page" lives. */
	public static function addUrl(): string {
		return admin_url( 'post-new.php?post_type=page' );
	}

	/**
	 * Where a page is renamed.
	 *
	 * @param int $post_id Page.
	 */
	public static function renameUrl( int $post_id ): string {
		return add_query_arg(
			array(
				'post'   => $post_id,
				'action' => self::RENAME,
			),
			admin_url( 'post.php' )
		);
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'load-post-new.php', array( $this, 'addScreen' ) );
		add_action( 'admin_action_elementor_new_post', array( $this, 'askFirst' ), 1 ); // Before Elementor creates one.
		add_action( 'post_action_' . self::RENAME, array( $this, 'renameScreen' ) );
		add_action( 'admin_post_' . self::ADD_ACTION, array( $this, 'add' ) );
		add_action( 'admin_post_' . self::RENAME_ACTION, array( $this, 'rename' ) );
		add_filter( 'page_row_actions', array( $this, 'rowAction' ), PHP_INT_MAX, 2 );
		add_action( 'admin_notices', array( $this, 'renamedNotice' ) );
		add_filter( 'removable_query_args', array( $this, 'removableArgs' ) );
		add_filter( 'rest_request_after_callbacks', array( $this, 'nameBuilderPage' ), 10, 3 );
	}

	/** Do the page screens apply for this request? */
	private static function active(): bool {
		return did_action( 'elementor/loaded' ) && Audience::current();
	}

	/** "Add page" (post-new.php?post_type=page) asks for the name. */
	public function addScreen(): void {
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'page' !== $type || ! self::active() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		self::screen(
			array(
				'heading' => __( 'Add page', 'favr-sites' ),
				'intro'   => __( 'Name the page, then design it. You can rename it later from the Pages list.', 'favr-sites' ),
				'action'  => self::ADD_ACTION,
				'nonce'   => self::ADD_ACTION,
				'post'    => 0,
				'name'    => '',
				'address' => null,
				'prefix'  => '',
				'submit'  => __( 'Create page', 'favr-sites' ),
			),
			'post-new.php?post_type=page'
		);
	}

	/** Elementor's own "new page" link (its document menu) asks for the name too. */
	public function askFirst(): void {
		$type  = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$other = isset( $_GET['template_type'] ) && '' !== $_GET['template_type']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'page' !== $type || $other || ! self::active() ) {
			return;
		}
		wp_safe_redirect( self::addUrl() );
		exit;
	}

	/**
	 * "Rename" (post.php?post=N&action=favr-rename).
	 *
	 * @param mixed $post_id Post.
	 */
	public function renameScreen( $post_id ): void {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof \WP_Post || ! self::mayRename( $post ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to rename this page.', 'favr-sites' ), 403 );
		}
		$parent = $post->post_parent ? trailingslashit( (string) get_page_uri( $post->post_parent ) ) : '';
		self::screen(
			array(
				'heading' => __( 'Rename page', 'favr-sites' ),
				'intro'   => '',
				'action'  => self::RENAME_ACTION,
				'nonce'   => self::RENAME_ACTION . '_' . $post->ID,
				'post'    => (int) $post->ID,
				'name'    => (string) $post->post_title,
				'address' => '' !== $post->post_name ? (string) $post->post_name : sanitize_title( $post->post_title ),
				'prefix'  => (string) preg_replace( '#^https?://#', '', trailingslashit( home_url() ) ) . $parent,
				'submit'  => __( 'Save', 'favr-sites' ),
			),
			'edit.php?post_type=page'
		);
	}

	/**
	 * Print one of the two small screens inside wp-admin and stop.
	 *
	 * @param array<string, mixed> $view    View model for templates/page-name.php.
	 * @param string               $current The Pages submenu entry to mark as current.
	 */
	private static function screen( array $view, string $current ): void {
		global $title, $parent_file, $submenu_file;
		$title        = (string) $view['heading']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the screen's <title>, as wp-admin screens set it.
		$parent_file  = 'edit.php?post_type=page'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- marks Pages in the menu.
		$submenu_file = $current; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- marks the entry in the menu.

		// A screen of its own: nothing that belongs to the page edit form loads or is expected here.
		set_current_screen( self::SCREEN );
		$type            = get_post_type_object( 'page' );
		$view['back']    = $type ? (string) $type->labels->all_items : __( 'All pages', 'favr-sites' );
		$view['problem'] = self::problem( isset( $_GET[ self::PROBLEM ] ) ? sanitize_key( wp_unslash( $_GET[ self::PROBLEM ] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		$view['cancel']  = admin_url( 'edit.php?post_type=page' );

		$sheet = 'assets/editor/pages.css';
		add_action(
			'admin_enqueue_scripts',
			static function () use ( $sheet ): void {
				wp_enqueue_style( 'favr-sites-pages', FAVR_SITES_URL . $sheet, array(), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $sheet ) );
			},
			30
		);

		require_once ABSPATH . 'wp-admin/admin-header.php';
		include FAVR_SITES_PATH . 'templates/page-name.php';
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}

	/** Create the page from its name and open Elementor. */
	public function add(): void {
		check_admin_referer( self::ADD_ACTION );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to add pages.', 'favr-sites' ), 403 );
		}
		$name = self::cleanName( isset( $_POST['page_name'] ) ? (string) wp_unslash( $_POST['page_name'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by cleanName().
		if ( '' === $name ) {
			self::back( self::addUrl(), 'name' );
		}
		$post_id = self::create( $name );
		if ( ! $post_id ) {
			self::back( self::addUrl(), 'failed' );
		}
		wp_safe_redirect( did_action( 'elementor/loaded' ) ? Routing::elementorUrl( $post_id ) : (string) get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * A draft page with this name and address, built with Elementor when it runs.
	 *
	 * @param string $name Name.
	 * @return int Page id, 0 on failure.
	 */
	private static function create( string $name ): int {
		$data = wp_slash(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => $name,
				'post_name'   => sanitize_title( $name ),
			)
		);
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			// Elementor's page document ('wp-page', what it records when a page is saved); its plain one if that is ever renamed.
			$documents = \Elementor\Plugin::$instance->documents;
			$type      = $documents->get_document_type( 'wp-page', false ) ? 'wp-page' : 'post';
			$document  = $documents->create( $type, $data );
			return is_object( $document ) && ! is_wp_error( $document ) && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
		}
		$post_id = wp_insert_post( $data, true );
		return is_wp_error( $post_id ) ? 0 : (int) $post_id;
	}

	/** Save a page's name and address. */
	public function rename(): void {
		$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		check_admin_referer( self::RENAME_ACTION . '_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! self::mayRename( $post ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to rename this page.', 'favr-sites' ), 403 );
		}
		$name = self::cleanName( isset( $_POST['page_name'] ) ? (string) wp_unslash( $_POST['page_name'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by cleanName().
		if ( '' === $name ) {
			self::back( self::renameUrl( $post_id ), 'name' );
		}
		$address = self::cleanAddress( isset( $_POST['page_address'] ) ? (string) wp_unslash( $_POST['page_address'] ) : '', $name, 'sanitize_title' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by cleanAddress().
		$result  = wp_update_post(
			wp_slash(
				array(
					'ID'         => $post_id,
					'post_title' => $name,
					'post_name'  => $address,
				)
			),
			true
		);
		if ( is_wp_error( $result ) || ! $result ) {
			self::back( self::renameUrl( $post_id ), 'failed' );
		}
		wp_safe_redirect( add_query_arg( self::RENAMED, $post_id, admin_url( 'edit.php?post_type=page' ) ) );
		exit;
	}

	/**
	 * May the current user rename this page? Not a locked page, not one in the trash.
	 *
	 * @param \WP_Post $post Page.
	 */
	private static function mayRename( \WP_Post $post ): bool {
		return 'trash' !== $post->post_status && self::offersRename( (string) $post->post_type, current_user_can( 'edit_post', $post->ID ), PageLock::lockedPost( $post ) );
	}

	/**
	 * Go back to a screen and say what went wrong.
	 *
	 * @param string $url  Screen.
	 * @param string $code Problem code.
	 * @return never
	 */
	private static function back( string $url, string $code ): void {
		wp_safe_redirect( add_query_arg( self::PROBLEM, $code, $url ) );
		exit;
	}

	/**
	 * "Rename" under each page an Editor may edit.
	 *
	 * @param mixed $actions Row actions.
	 * @param mixed $post    Post.
	 * @return mixed
	 */
	public function rowAction( $actions, $post ) {
		if ( ! is_array( $actions ) || ! $post instanceof \WP_Post || ! self::active() || ! self::mayRename( $post ) ) {
			return $actions;
		}
		/* translators: %s: page name. */
		$label = sprintf( __( 'Rename “%s”', 'favr-sites' ), _draft_or_post_title( $post ) );
		$link  = '<a href="' . esc_url( self::renameUrl( (int) $post->ID ) ) . '" aria-label="' . esc_attr( $label ) . '">' . esc_html__( 'Rename', 'favr-sites' ) . '</a>';
		return self::withRename( $actions, $link );
	}

	/** "Page renamed." on the Pages list. */
	public function renamedNotice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-page' !== $screen->id || ! isset( $_GET[ self::RENAMED ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
			return;
		}
		echo '<div class="notice notice-success is-dismissible" role="status"><p>' . esc_html__( 'Page renamed.', 'favr-sites' ) . '</p></div>';
	}

	/**
	 * Inside the page editor, Elementor's own "Add new page" creates a page without asking, called
	 * "Elementor Page #4449". For Editors it is called "Untitled page" instead; "Rename" does the rest.
	 *
	 * @param mixed $response Response of the route.
	 * @param mixed $handler  Route handler.
	 * @param mixed $request  Request.
	 * @return mixed
	 */
	public function nameBuilderPage( $response, $handler, $request ) {
		if ( ! $request instanceof \WP_REST_Request || self::BUILDER_ROUTE !== $request->get_route() || 'POST' !== $request->get_method() || ! Audience::current() ) {
			return $response;
		}
		$data = $response instanceof \WP_REST_Response ? $response->get_data() : $response;
		$post = is_array( $data ) && ! empty( $data['id'] ) ? get_post( (int) $data['id'] ) : null;
		if ( $post instanceof \WP_Post && 'page' === $post->post_type && self::isBuilderName( (string) $post->post_title ) && current_user_can( 'edit_post', $post->ID ) ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'         => $post->ID,
						'post_title' => __( 'Untitled page', 'favr-sites' ),
					)
				)
			);
		}
		return $response;
	}

	/**
	 * The flags leave the address bar once shown.
	 *
	 * @param mixed $args Query args.
	 * @return mixed
	 */
	public function removableArgs( $args ) {
		return is_array( $args ) ? array_merge( $args, array( self::RENAMED, self::PROBLEM ) ) : $args;
	}
}
