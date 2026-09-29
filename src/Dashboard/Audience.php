<?php
/**
 * Who gets the Favr dashboard.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Editors (the role Favr clients are given) get the Favr dashboard; everyone else keeps the stock one.
 */
final class Audience {

	/**
	 * Memoized answer for the current user (checked many times per request).
	 *
	 * @var array<int, bool>
	 */
	private static array $memo = array();

	/** Does the current user get the Favr experience? */
	public static function current(): bool {
		$user = wp_get_current_user();
		$id   = (int) $user->ID;
		if ( ! isset( self::$memo[ $id ] ) ) {
			self::$memo[ $id ] = self::includes( $user );
		}
		return self::$memo[ $id ];
	}

	/** Reset the memo (tests, user switches). */
	public static function flush(): void {
		self::$memo = array();
	}

	/**
	 * Does this user get the Favr dashboard?
	 *
	 * @param \WP_User|null $user User.
	 */
	public static function includes( ?\WP_User $user ): bool {
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$default = in_array( 'editor', (array) $user->roles, true ) && ! user_can( $user, 'manage_options' );
		return (bool) apply_filters( 'favr_sites_dashboard_enabled', $default, $user );
	}
}
