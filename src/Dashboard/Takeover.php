<?php
/**
 * Replace wp-admin/index.php for the Favr audience.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Runs on load-index.php, before WordPress sets up dashboard widgets: renders the Favr screen
 * inside the normal admin frame and stops, so no core or plugin widget is ever built.
 */
final class Takeover {

	/** Hooks. */
	public function hook(): void {
		add_action( 'load-index.php', array( $this, 'maybeTakeOver' ) );
	}

	/** Take over when the user is in the audience. */
	public function maybeTakeOver(): void {
		if ( ! Audience::includes( wp_get_current_user() ) ) {
			return;
		}

		foreach ( array( 'tokens', 'dashboard' ) as $name ) {
			$file = 'assets/dashboard/' . $name . '.css';
			wp_enqueue_style( 'favr-sites-' . $name, FAVR_SITES_URL . $file, 'dashboard' === $name ? array( 'favr-sites-tokens' ) : array(), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file ) );
		}
		add_filter( 'admin_body_class', static fn( string $classes ): string => $classes . ' favr-dash-screen' );
		add_filter( 'admin_footer_text', '__return_empty_string', PHP_INT_MAX );
		add_filter( 'update_footer', '__return_empty_string', PHP_INT_MAX );

		// Plugin notices ("rate us", licence nags) don't belong on the Favr screen.
		add_action(
			'in_admin_header',
			static function (): void {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
				remove_all_actions( 'network_admin_notices' );
			},
			PHP_INT_MAX
		);

		// Globals admin-header.php reads (index.php would set them after this hook).
		$GLOBALS['title']       = __( 'Dashboard', 'favr-sites' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['parent_file'] = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		require_once ABSPATH . 'wp-admin/admin-header.php';
		( new Screen() )->render();
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}
}
