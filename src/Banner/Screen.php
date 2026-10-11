<?php
/**
 * Alert banner: the Editors' screen.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Banner;

use FavrSites\Dashboard\Audience;
use FavrSites\Menus\Tree;

/**
 * A Favr screen with one form: the message, an optional link, the style, optional show and hide
 * times, and whether visitors can close the banner.
 */
final class Screen {

	public const PAGE  = 'favr-banner';
	private const SAVE = 'favr_sites_save_banner';

	/**
	 * The status line shown above the form.
	 *
	 * @param Banner                $banner Banner.
	 * @param \DateTimeImmutable    $now    Now.
	 * @param \DateTimeZone         $zone   Site time zone.
	 * @param callable(int): string $format Formats a timestamp for people.
	 */
	public static function status( Banner $banner, \DateTimeImmutable $now, \DateTimeZone $zone, callable $format ): string {
		if ( ! $banner->enabled() ) {
			return __( 'The banner is off.', 'favr-sites' );
		}
		if ( '' === $banner->message() ) {
			return __( 'Add a message to show the banner.', 'favr-sites' );
		}
		$window = $banner->window( $zone );
		$state  = $banner->state( $now, $zone );
		if ( 'live' === $state ) {
			$status = __( 'The banner is showing now.', 'favr-sites' );
			if ( null !== $window['until'] ) {
				/* translators: %s: date and time. */
				$status .= ' ' . sprintf( __( 'It will hide on %s.', 'favr-sites' ), $format( $window['until'] ) );
			}
			return $status;
		}
		if ( 'pending' === $state ) {
			/* translators: %s: date and time. */
			return sprintf( __( 'The banner will show from %s.', 'favr-sites' ), $format( (int) $window['from'] ) );
		}
		/* translators: %s: date and time. */
		return sprintf( __( 'The banner stopped showing on %s.', 'favr-sites' ), $format( (int) $window['until'] ) );
	}

	/**
	 * The confirmation after a save: what visitors see now, not only that the form was stored.
	 *
	 * @param Banner             $banner Banner.
	 * @param \DateTimeImmutable $now    Now.
	 * @param \DateTimeZone      $zone   Site time zone.
	 */
	public static function savedNotice( Banner $banner, \DateTimeImmutable $now, \DateTimeZone $zone ): string {
		$state = $banner->state( $now, $zone );
		if ( 'live' === $state ) {
			return __( 'Banner saved. It is showing on the site now.', 'favr-sites' );
		}
		if ( 'pending' === $state ) {
			return __( 'Banner saved. It is not on the site yet.', 'favr-sites' );
		}
		if ( ! $banner->enabled() ) {
			return __( 'Banner saved. It is off the site now.', 'favr-sites' );
		}
		return '' === $banner->message()
			? __( 'Banner saved. It needs a message before it can show.', 'favr-sites' )
			: __( 'Banner saved. Its hide time has passed, so it is off the site.', 'favr-sites' );
	}

	/**
	 * The label over the preview when the banner is not on the site; none while it is live.
	 *
	 * @param string $state live|pending|off.
	 */
	public static function previewLabel( string $state ): string {
		if ( 'live' === $state ) {
			return '';
		}
		return 'pending' === $state ? __( 'Preview (not on the site yet)', 'favr-sites' ) : __( 'Preview (not on the site)', 'favr-sites' );
	}

	/**
	 * The site's time zone as people say it: "Eastern Time", not "America/New_York".
	 *
	 * @param \DateTimeZone      $zone Site time zone.
	 * @param \DateTimeImmutable $now  Now.
	 */
	public static function zoneName( \DateTimeZone $zone, \DateTimeImmutable $now ): string {
		$id = $zone->getName();
		if ( 'UTC' === $id || preg_match( '/^[+-]\d/', $id ) ) {
			return 'UTC' === $id ? 'UTC' : 'UTC' . $id;
		}
		// North American zones by their abbreviation (EST and EDT are both Eastern Time). Only there:
		// "CST" is also China's.
		$names = array(
			'E'  => __( 'Eastern Time', 'favr-sites' ),
			'C'  => __( 'Central Time', 'favr-sites' ),
			'M'  => __( 'Mountain Time', 'favr-sites' ),
			'P'  => __( 'Pacific Time', 'favr-sites' ),
			'AK' => __( 'Alaska Time', 'favr-sites' ),
			'H'  => __( 'Hawaii Time', 'favr-sites' ),
		);
		$short = $now->setTimezone( $zone )->format( 'T' );
		if ( preg_match( '#^(America/|US/|Pacific/Honolulu$)#', $id ) && preg_match( '/^(E|C|M|P|AK|H)[SD]T$/', $short, $match ) ) {
			return $names[ $match[1] ];
		}
		// Anywhere else: the zone's city.
		$parts = explode( '/', $id );
		/* translators: %s: a city, e.g. "London". */
		return sprintf( __( '%s time', 'favr-sites' ), str_replace( '_', ' ', (string) end( $parts ) ) );
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_' . self::SAVE, array( $this, 'save' ) );
	}

	/** Menu entry. */
	public function menu(): void {
		$hook = add_menu_page( __( 'Alert banner', 'favr-sites' ), __( 'Alert banner', 'favr-sites' ), 'edit_pages', self::PAGE, array( $this, 'render' ), 'dashicons-megaphone', 22 );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'load' ) );
		}
	}

	/** Assets and screen chrome. */
	public function load(): void {
		foreach ( array( 'tokens', 'dashboard' ) as $name ) {
			$file = 'assets/dashboard/' . $name . '.css';
			wp_enqueue_style( 'favr-sites-' . $name, FAVR_SITES_URL . $file, 'dashboard' === $name ? array( 'favr-sites-tokens' ) : array(), self::version( $file ) );
		}
		wp_enqueue_style( 'favr-sites-banner', FAVR_SITES_URL . 'assets/banner/banner.css', array(), self::version( 'assets/banner/banner.css' ) ); // The preview.
		wp_enqueue_style( 'favr-sites-banner-screen', FAVR_SITES_URL . 'assets/banner/screen.css', array( 'favr-sites-tokens' ), self::version( 'assets/banner/screen.css' ) );
		add_filter( 'admin_body_class', static fn( string $classes ): string => $classes . ' favr-dash-screen favr-banner-screen' );
		add_filter( 'admin_footer_text', '__return_empty_string', PHP_INT_MAX );
		add_filter( 'update_footer', '__return_empty_string', PHP_INT_MAX );

		// For Editors, plugin notices don't belong on a Favr screen (the banner has its own).
		// Administrators keep theirs, as on the Favr dashboard and profile.
		if ( Audience::current() ) {
			add_action(
				'in_admin_header',
				static function (): void {
					remove_all_actions( 'admin_notices' );
					remove_all_actions( 'all_admin_notices' );
				},
				PHP_INT_MAX
			);
		}
	}

	/** Page. */
	public function render(): void {
		$banner   = Store::get();
		$zone     = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $zone );
		$warnings = get_transient( self::warningsKey() );
		delete_transient( self::warningsKey() );

		$view = array(
			'banner'   => $banner,
			'state'    => $banner->state( $now, $zone ),
			'status'   => self::status( $banner, $now, $zone, static fn( int $timestamp ): string => (string) wp_date( 'j M Y, g:i a', $timestamp ) ),
			'zone'     => self::zoneName( $zone, $now ),
			'colors'   => Display::colors( $banner->style(), Display::kitColor() ),
			'saved'    => isset( $_GET['saved'] ) ? self::savedNotice( $banner, $now, $zone ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
			'warnings' => is_array( $warnings ) ? $warnings : array(),
			'action'   => self::SAVE,
		);
		include FAVR_SITES_PATH . 'templates/banner-screen.php';
	}

	/** Save the form. */
	public function save(): void {
		check_admin_referer( self::SAVE );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit the banner.', 'favr-sites' ), 403 );
		}
		$input = isset( $_POST['banner'] ) && is_array( $_POST['banner'] ) ? wp_unslash( $_POST['banner'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is cleaned by Banner::clean().
		$out   = Banner::clean( $input, array( Tree::class, 'cleanUrl' ), time() );
		Store::save( $out['banner'] );
		if ( $out['warnings'] ) {
			set_transient( self::warningsKey(), $out['warnings'], 60 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&saved=1' ) );
		exit;
	}

	/** Per-user key for warnings shown after the redirect. */
	private static function warningsKey(): string {
		return 'favr_sites_banner_warnings_' . get_current_user_id();
	}

	/**
	 * Cache-busting version for an asset.
	 *
	 * @param string $file Path from the plugin root.
	 */
	private static function version( string $file ): string {
		return FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file );
	}
}
