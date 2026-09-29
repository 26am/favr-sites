<?php
/**
 * Comments off, site-wide, for everyone. Nothing is deleted.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Comments;

/**
 * Comments only attract spam on Favr sites: no forms, no admin screens, no ways in.
 */
final class Off {

	/**
	 * Drop the comments REST routes.
	 *
	 * @param array<string, mixed> $endpoints Routes.
	 * @return array<string, mixed>
	 */
	public static function endpoints( $endpoints ) {
		foreach ( array_keys( (array) $endpoints ) as $route ) {
			if ( str_starts_with( (string) $route, '/wp/v2/comments' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Drop pingbacks from XML-RPC.
	 *
	 * @param array<string, mixed> $methods Methods.
	 * @return array<string, mixed>
	 */
	public static function xmlrpc( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Drop the Comments column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( $columns ) {
		unset( $columns['comments'] );
		return $columns;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'comments_open', '__return_false', PHP_INT_MAX );
		add_filter( 'pings_open', '__return_false', PHP_INT_MAX );
		add_filter( 'comments_array', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'get_comments_number', '__return_zero', PHP_INT_MAX );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		add_filter( 'rest_endpoints', array( self::class, 'endpoints' ) );
		add_filter( 'xmlrpc_methods', array( self::class, 'xmlrpc' ) );
		add_action( 'init', array( $this, 'removeSupport' ), PHP_INT_MAX );
		add_action( 'admin_menu', array( $this, 'menu' ), PHP_INT_MAX );
		add_action( 'admin_bar_menu', array( $this, 'adminBar' ), PHP_INT_MAX );
		add_action( 'load-edit-comments.php', array( $this, 'redirect' ) );
		add_action( 'load-options-discussion.php', array( $this, 'redirect' ) );
		add_filter( 'manage_posts_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_filter( 'manage_pages_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_filter( 'manage_media_columns', array( self::class, 'columns' ), PHP_INT_MAX );
	}

	/** No comment or trackback support on any post type (removes the Discussion box). */
	public function removeSupport(): void {
		foreach ( get_post_types() as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
		}
	}

	/** No Comments menu or Discussion settings. */
	public function menu(): void {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * No comments bubble in the admin bar.
	 *
	 * @param \WP_Admin_Bar $bar Bar.
	 */
	public function adminBar( $bar ): void {
		$bar->remove_node( 'comments' );
	}

	/** Comment screens go to the dashboard. */
	public function redirect(): void {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
