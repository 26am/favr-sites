<?php
/**
 * "Your profile" for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Profile;

use FavrSites\Dashboard\Audience;

/**
 * Replaces wp-admin/profile.php for Editors with a short Favr page: name, email, password and
 * photo. Saving goes through WordPress's own profile update (personal_options_update + edit_user),
 * so email confirmation and password checks are core's.
 *
 * Settings Editors no longer see are pinned: toolbar on, Media infinite scrolling on, application
 * passwords off, Elementor AI off (it stays off until Favr's AI controls), comment shortcuts off.
 * Elementor AI is pinned where Elementor reads it (the user option `elementor_enable_ai`, "on"
 * when never set), not stored: nothing is written, and an Editor who never saved this page has
 * no "Generate with Elementor AI" buttons in the Media Library either.
 * There is no colour scheme to pick: Editors always get Favr's (Brand\ColorScheme), and saving
 * the page leaves the scheme stored for the user as it was.
 */
final class ProfilePage {

	/** The user option Elementor keeps its per-user AI switch in (Elementor\Modules\Ai\Preferences::ENABLE_AI). */
	public const ELEMENTOR_AI = 'elementor_enable_ai';

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
	 * The colour scheme to hand back to WordPress when the profile is saved: the one already
	 * stored. The page posts no scheme, and WordPress writes its default ("fresh") over the stored
	 * one when the field is missing, so the stored value is put in its place.
	 *
	 * @param mixed $stored The user's raw `admin_color` meta.
	 */
	public static function keptScheme( $stored ): string {
		return is_string( $stored ) && '' !== $stored ? $stored : 'fresh';
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'load-profile.php', array( $this, 'maybeTakeOver' ) );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'appPasswords' ), 10, 2 );
		add_filter( 'show_admin_bar', array( $this, 'toolbar' ), PHP_INT_MAX );
		add_filter( 'media_library_infinite_scrolling', array( $this, 'infiniteScrolling' ), PHP_INT_MAX );
		add_filter( 'get_user_option_' . self::ELEMENTOR_AI, array( $this, 'elementorAiOption' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Pure: the value Elementor gets for its "Enable Elementor AI functionality" preference.
	 * Always off for Editors; everybody else keeps what is stored (false when never set).
	 *
	 * @param mixed $stored    The stored user option.
	 * @param bool  $is_editor The user is in the Favr audience.
	 * @return mixed
	 */
	public static function elementorAi( $stored, bool $is_editor ) {
		return $is_editor ? '0' : $stored;
	}

	/**
	 * Elementor AI is off for Editors. Elementor asks once, when it loads its AI module, and
	 * then adds no AI buttons to the Media Library, the media window or its editor.
	 *
	 * @param mixed $stored Stored value.
	 * @param mixed $option Option name.
	 * @param mixed $user   The user it is read for.
	 * @return mixed
	 */
	public function elementorAiOption( $stored, $option = '', $user = null ) {
		return self::elementorAi( $stored, $user instanceof \WP_User && Audience::includes( $user ) );
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
	 * Pure: does the toolbar show? Always for Editors on the public site, but never forced back
	 * inside Elementor's editor preview, where Elementor turns it off: there it sits on top of
	 * the page's header.
	 *
	 * @param bool $show            What WordPress (and anything else) decided.
	 * @param bool $is_editor       In the Favr audience.
	 * @param bool $builder_preview This request is the page inside Elementor's editor.
	 */
	public static function showsToolbar( bool $show, bool $is_editor, bool $builder_preview ): bool {
		return $is_editor && ! $builder_preview ? true : $show;
	}

	/**
	 * The toolbar always shows for Editors on the public site.
	 *
	 * @param bool $show Show.
	 */
	public function toolbar( $show ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which kind of request this is.
		return self::showsToolbar( (bool) $show, is_user_logged_in() && Audience::current(), isset( $_GET['elementor-preview'] ) );
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

		$user = get_userdata( $user->ID ); // Fresh after a failed save.
		$view = array(
			'user'      => $user,
			'errors'    => $errors,
			'updated'   => isset( $_GET['updated'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'new_email' => get_user_meta( $user->ID, '_new_email', true ),
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

		// Fields this page doesn't show: names are derived, the toolbar stays on, the colour scheme stays as stored.
		$names                    = self::names(
			(string) wp_unslash( $_POST['first_name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by edit_user().
			(string) wp_unslash( $_POST['last_name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by edit_user().
			(string) $user->display_name,
			(string) $user->nickname
		);
		$_POST['display_name']    = $names['display_name'];
		$_POST['nickname']        = $names['nickname'];
		$_POST['admin_bar_front'] = '1';
		$_POST['admin_color']     = self::keptScheme( get_user_meta( $user->ID, 'admin_color', true ) );

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
