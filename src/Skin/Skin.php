<?php
/**
 * The Favr look on every wp-admin screen an Editor sees.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Skin;

use FavrSites\Dashboard\Audience;

/**
 * Editors only: adds the `favr-skin` body class and loads assets/skin/*.css, which restyle
 * WordPress's own screens (lists, edit screens, media) and the sibling plugins' screens with the
 * brand tokens. The Favr screens (dashboard, menus, banner, profile) keep their own sheets; the
 * skin leaves anything inside `.favr-dash-screen` alone.
 */
final class Skin {

	public const HANDLE = 'favr-sites-skin';

	/**
	 * Pure: the sheets a screen gets, in load order. The block editor draws its own interface, so
	 * it only gets the shell and the media window.
	 *
	 * @param bool $block_editor The screen is the block editor.
	 * @return list<string>
	 */
	public static function sheetsFor( bool $block_editor ): array {
		return $block_editor
			? array( 'base', 'media' )
			: array( 'base', 'lists', 'edit', 'fields', 'screens', 'media' );
	}

	/**
	 * Pure: a sheet's path inside the plugin.
	 *
	 * @param string $sheet Sheet name.
	 */
	public static function file( string $sheet ): string {
		return 'assets/skin/' . $sheet . '.css';
	}

	/**
	 * Pure: the body classes with `favr-skin` added once.
	 *
	 * @param string $classes Current classes.
	 */
	public static function bodyClass( string $classes ): string {
		if ( in_array( 'favr-skin', preg_split( '/\s+/', $classes ) ?: array(), true ) ) {
			return $classes;
		}
		return ltrim( $classes . ' favr-skin' );
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'admin_body_class', array( $this, 'body' ) );
	}

	/** Load the skin for Editors. */
	public function enqueue(): void {
		if ( ! Audience::current() ) {
			return;
		}
		$tokens = 'assets/dashboard/tokens.css';
		wp_enqueue_style( 'favr-sites-tokens', FAVR_SITES_URL . $tokens, array(), self::version( $tokens ) );

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$deps   = array( 'favr-sites-tokens' );
		foreach ( self::sheetsFor( (bool) ( $screen && $screen->is_block_editor() ) ) as $sheet ) {
			$handle = 'base' === $sheet ? self::HANDLE : self::HANDLE . '-' . $sheet;
			wp_enqueue_style( $handle, FAVR_SITES_URL . self::file( $sheet ), $deps, self::version( self::file( $sheet ) ) );
			$deps = array( self::HANDLE );
		}
	}

	/**
	 * The body class every skin rule hangs on.
	 *
	 * @param string $classes Classes.
	 */
	public function body( $classes ): string {
		return Audience::current() ? self::bodyClass( (string) $classes ) : (string) $classes;
	}

	/**
	 * Cache-busting version: plugin version + file time.
	 *
	 * @param string $file Path inside the plugin.
	 */
	private static function version( string $file ): string {
		return FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file );
	}
}
