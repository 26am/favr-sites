<?php
/**
 * Who gets the Favr dashboard.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Editors and below get the Favr dashboard; Administrators keep the stock one.
 */
final class Audience {

	/**
	 * Does this user get the Favr dashboard?
	 *
	 * @param \WP_User|null $user User.
	 */
	public static function includes( ?\WP_User $user ): bool {
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$default = user_can( $user, 'read' ) && ! user_can( $user, 'manage_options' );
		return (bool) apply_filters( 'favr_sites_dashboard_enabled', $default, $user );
	}
}
