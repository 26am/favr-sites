<?php
/**
 * The two Favr menus: Header and Footer.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Menus;

/**
 * Which WordPress menu is the Header and which is the Footer. Connected in Settings → Favr; a
 * fresh site (no menus at all) gets "Header" and "Footer" created the first time an Administrator
 * loads wp-admin.
 */
final class Slots {

	public const SLOTS  = array( 'header', 'footer' );
	public const OPTION = 'favr_sites_menus';

	/**
	 * Resolve slots: the saved menu if it still exists, else a menu whose slug is the slot name.
	 *
	 * @param mixed              $option Saved option.
	 * @param array<int, string> $menus  Menu term id => slug.
	 * @return array{header: ?int, footer: ?int}
	 */
	public static function resolve( $option, array $menus ): array {
		$option = is_array( $option ) ? $option : array();
		$out    = array();
		foreach ( self::SLOTS as $slot ) {
			$saved = (int) ( $option[ $slot ] ?? 0 );
			if ( $saved && isset( $menus[ $saved ] ) ) {
				$out[ $slot ] = $saved;
				continue;
			}
			$by_slug      = array_search( $slot, $menus, true );
			$out[ $slot ] = false !== $by_slug ? (int) $by_slug : null;
		}
		return $out;
	}

	/**
	 * Clean a submitted slots option: only real menu ids (0 = not connected).
	 *
	 * @param mixed              $input Submitted.
	 * @param array<int, string> $menus Menu term id => slug.
	 * @return array{header: int, footer: int}
	 */
	public static function sanitize( $input, array $menus ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( self::SLOTS as $slot ) {
			$id           = (int) ( $input[ $slot ] ?? 0 );
			$out[ $slot ] = isset( $menus[ $id ] ) ? $id : 0;
		}
		return $out;
	}

	/**
	 * Slot label.
	 *
	 * @param string $slot Slot.
	 */
	public static function label( string $slot ): string {
		return 'footer' === $slot ? __( 'Footer menu', 'favr-sites' ) : __( 'Header menu', 'favr-sites' );
	}

	/**
	 * Menus on this site: term id => slug.
	 *
	 * @return array<int, string>
	 */
	public static function menus(): array {
		$out = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$out[ (int) $menu->term_id ] = (string) $menu->slug;
		}
		return $out;
	}

	/**
	 * The connected menus right now.
	 *
	 * @return array{header: ?int, footer: ?int}
	 */
	public static function current(): array {
		return self::resolve( get_option( self::OPTION, array() ), self::menus() );
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_init', array( $this, 'ensureDefaults' ) );
	}

	/** Fresh sites (no menus at all): create "Header" and "Footer" and connect them. */
	public function ensureDefaults(): void {
		if ( ! current_user_can( 'manage_options' ) || wp_get_nav_menus() ) {
			return;
		}
		$option = array();
		foreach ( self::SLOTS as $slot ) {
			$id = wp_create_nav_menu( 'footer' === $slot ? __( 'Footer', 'favr-sites' ) : __( 'Header', 'favr-sites' ) );
			if ( ! is_wp_error( $id ) ) {
				$option[ $slot ] = (int) $id;
			}
		}
		update_option( self::OPTION, $option );
	}
}
