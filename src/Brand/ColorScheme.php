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

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
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
