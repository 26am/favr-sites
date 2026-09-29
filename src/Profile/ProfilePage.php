<?php
/**
 * "Your profile" for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Profile;

use FavrSites\Brand\ColorScheme;
use FavrSites\Dashboard\Audience;

/**
 * Replaces wp-admin/profile.php for Editors with a short Favr page: name, email, password, photo
 * and a compact colour-scheme picker. Saving goes through WordPress's own profile update
 * (personal_options_update + edit_user), so email confirmation and password checks are core's.
 *
 * Settings Editors no longer see are pinned: toolbar on, Media infinite scrolling on, application
 * passwords off, Elementor AI off (it stays off until Favr's AI controls), comment shortcuts off.
 */
final class ProfilePage {

	/**
	 * Display name and nickname from first/last name; blanks keep what's there.
	 *
	 * @param string $first            First name.
	 * @param string $last             Last name.
	 * @param string $current_display  Current display name.
	 * @param string $current_nickname Current nickname.
	 * @return array{display_name: string, nickname: string}
	 */
	public static function names( string $first, string $last, string $current_display, string $current_nickname ): array {
		$first = trim( $first );
		$last  = trim( $last );
		$full  = trim( $first . ' ' . $last );
		if ( '' === $full ) {
			return array(
				'display_name' => $current_display,
				'nickname'     => '' !== $current_nickname ? $current_nickname : $current_display,
			);
		}
		return array(
			'display_name' => $full,
			'nickname'     => '' !== $first ? $first : $last,
		);
	}

	/**
	 * Colour schemes for the picker: Favr first, then WordPress's order; one marked current.
	 *
	 * @param array<string, mixed> $registered $GLOBALS['_wp_admin_css_colors'].
	 * @param string               $current    The user's scheme.
	 * @return list<array{slug: string, name: string, colors: list<string>, current: bool}>
	 */
	public static function schemes( array $registered, string $current ): array {
		$out = array();
		foreach ( $registered as $slug => $scheme ) {
			if ( ! is_object( $scheme ) || empty( $scheme->colors ) ) {
				continue;
			}
			$out[ (string) $slug ] = array(
				'slug'    => (string) $slug,
				'name'    => (string) ( $scheme->name ?? $slug ),
				'colors'  => array_values( array_slice( (array) $scheme->colors, 0, 4 ) ),
				'current' => false,
			);
		}
		if ( isset( $out[ ColorScheme::SLUG ] ) ) {
			$out = array( ColorScheme::SLUG => $out[ ColorScheme::SLUG ] ) + $out;
		}
		$selected = isset( $out[ $current ] ) ? $current : ( isset( $out[ ColorScheme::SLUG ] ) ? ColorScheme::SLUG : (string) array_key_first( $out ) );
		if ( isset( $out[ $selected ] ) ) {
			$out[ $selected ]['current'] = true;
		}
		return array_values( $out );
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'load-profile.php', array( $this, 'maybeTakeOver' ) );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'appPasswords' ), 10, 2 );
		add_filter( 'show_admin_bar', array( $this, 'toolbar' ), PHP_INT_MAX );
		add_filter( 'media_library_infinite_scrolling', array( $this, 'infiniteScrolling' ), PHP_INT_MAX );
	}

	/**
	 * No application passwords for Editors.
	 *
	 * @param bool     $available Available.
	 * @param \WP_User $user      User.
	 */
	public function appPasswords( $available, $user ): bool {
		return $user instanceof \WP_User && Audience::includes( $user ) ? false : (bool) $available;
	}

	/**
	 * The toolbar always shows for Editors on the public site.
	 *
	 * @param bool $show Show.
	 */
	public function toolbar( $show ): bool {
		return is_user_logged_in() && Audience::current() ? true : (bool) $show;
	}

	/**
	 * Media library infinite scrolling is always on for Editors.
	 *
	 * @param bool $on On.
	 */
	public function infiniteScrolling( $on ): bool {
		return Audience::current() ? true : (bool) $on;
	}

	/** Render (and save) the Favr profile for Editors. */
	public function maybeTakeOver(): void {
		if ( ! Audience::current() ) {
			return;
		}
		// Let core handle its own GET actions (new-email confirmation links, dismissing a pending email).
		if ( isset( $_GET['newuseremail'] ) || isset( $_GET['dismiss'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$user   = wp_get_current_user();
		$errors = null;
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in save().
		if ( 'post' === $method && 'update' === $action ) {
			$errors = $this->save( $user );
		}

		foreach ( array( 'tokens', 'dashboard' ) as $name ) {
			$file = 'assets/dashboard/' . $name . '.css';
			wp_enqueue_style( 'favr-sites-' . $name, FAVR_SITES_URL . $file, 'dashboard' === $name ? array( 'favr-sites-tokens' ) : array(), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file ) );
		}
		$file = 'assets/profile/profile.css';
		wp_enqueue_style( 'favr-sites-profile', FAVR_SITES_URL . $file, array( 'favr-sites-tokens' ), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file ) );
		add_filter( 'admin_body_class', static fn( string $classes ): string => $classes . ' favr-dash-screen favr-profile-screen' );
		add_filter( 'admin_footer_text', '__return_empty_string', PHP_INT_MAX );
		add_filter( 'update_footer', '__return_empty_string', PHP_INT_MAX );
		add_action(
			'in_admin_header',
			static function (): void {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
			},
			PHP_INT_MAX
		);

		$GLOBALS['title']       = __( 'Your profile', 'favr-sites' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['parent_file'] = 'profile.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		global $_wp_admin_css_colors;
		$user = get_userdata( $user->ID ); // Fresh after a failed save.
		$view = array(
			'user'      => $user,
			'errors'    => $errors,
			'updated'   => isset( $_GET['updated'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'new_email' => get_user_meta( $user->ID, '_new_email', true ),
			'schemes'   => self::schemes( is_array( $_wp_admin_css_colors ) ? $_wp_admin_css_colors : array(), (string) get_user_option( 'admin_color', $user->ID ) ),
		);

		require_once ABSPATH . 'wp-admin/admin-header.php';
		include FAVR_SITES_PATH . 'templates/profile.php';
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}

	/**
	 * Save through WordPress's own profile update; redirect on success.
	 *
	 * @param \WP_User $user Current user.
	 * @return \WP_Error Errors to show (only returned when saving failed).
	 */
	private function save( \WP_User $user ): \WP_Error {
		check_admin_referer( 'update-user_' . $user->ID );
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.', 'favr-sites' ) );
		}

		// Fields this page doesn't show: names are derived, the toolbar stays on.
		$names                    = self::names(
			(string) wp_unslash( $_POST['first_name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by edit_user().
			(string) wp_unslash( $_POST['last_name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by edit_user().
			(string) $user->display_name,
			(string) $user->nickname
		);
		$_POST['display_name']    = $names['display_name'];
		$_POST['nickname']        = $names['nickname'];
		$_POST['admin_bar_front'] = '1';

		/** This action is documented in wp-admin/user-edit.php */
		do_action( 'personal_options_update', $user->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.

		$result = edit_user( $user->ID );
		if ( ! is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'updated', 'true', admin_url( 'profile.php' ) ) );
			exit;
		}
		return $result;
	}
}
