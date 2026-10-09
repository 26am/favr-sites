<?php
/**
 * The Favr admin colour scheme.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Brand;

use FavrSites\Dashboard\Audience;

/**
 * Registers "Favr" under Profile → Admin Color Scheme for everyone, and makes it the default for
 * Editors who have never picked one. Built from WordPress's own scheme sources
 * (assets/admin-colors/favr/colors.scss → colors.css), so every part of wp-admin is covered.
 */
final class ColorScheme {

	public const SLUG = 'favr';

	/**
	 * The scheme a user sees.
	 *
	 * @param string|false $saved     Their saved admin_color (false/'' when they never chose).
	 * @param bool         $is_editor In the Favr audience.
	 * @return string|false
	 */
	public static function forUser( $saved, bool $is_editor ) {
		return $is_editor && ( false === $saved || '' === $saved ) ? self::SLUG : $saved;
	}

	/**
	 * Pure: does the toolbar need the Favr colours added? On the public site WordPress never loads
	 * a colour scheme, so the toolbar would turn back to WordPress's own dark grey.
	 *
	 * @param string $scheme  The user's admin colour scheme.
	 * @param bool   $admin   This is a wp-admin screen (the whole scheme is loaded there).
	 * @param bool   $showing The toolbar is showing.
	 */
	public static function brandsToolbar( string $scheme, bool $admin, bool $showing ): bool {
		return self::SLUG === $scheme && ! $admin && $showing;
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'toolbar' ), 20 );
		add_filter( 'get_user_option_admin_color', array( $this, 'defaultFor' ), 10, 3 );
	}

	/** Register the scheme. */
	public function register(): void {
		$file = 'assets/admin-colors/favr/colors.css';
		wp_admin_css_color(
			self::SLUG,
			__( 'Favr', 'favr-sites' ),
			FAVR_SITES_URL . $file . '?ver=' . FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file ),
			array( '#3a2738', '#a9472a', '#e3a044', '#fbf6ef' ),
			array(
				'base'    => '#d9c7d2',
				'focus'   => '#fbf6ef',
				'current' => '#fbf6ef',
			)
		);
	}

	/** The Favr colours for the toolbar on the public site. */
	public function toolbar(): void {
		if ( ! is_user_logged_in() || ! self::brandsToolbar( (string) get_user_option( 'admin_color' ), is_admin(), is_admin_bar_showing() ) ) {
			return;
		}
		$file = 'assets/admin-colors/favr/toolbar.css';
		wp_enqueue_style( 'favr-sites-toolbar', FAVR_SITES_URL . $file, array( 'admin-bar' ), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file ) );
	}

	/**
	 * Favr by default for Editors.
	 *
	 * @param mixed    $result Saved value.
	 * @param string   $option Option name.
	 * @param \WP_User $user   User.
	 * @return mixed
	 */
	public function defaultFor( $result, $option, $user ) {
		return $user instanceof \WP_User ? self::forUser( $result, Audience::includes( $user ) ) : $result;
	}
}
