<?php
/**
 * Where the alert banner is kept.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Banner;

/**
 * One autoloaded option: the banner is read on every public page.
 */
final class Store {

	public const OPTION = 'favr_sites_banner';

	/** The saved banner (off when nothing usable is stored). */
	public static function get(): Banner {
		return Banner::fromArray( get_option( self::OPTION, array() ) );
	}

	/**
	 * Save, then let page caches know.
	 *
	 * @param Banner $banner Banner.
	 */
	public static function save( Banner $banner ): void {
		update_option( self::OPTION, $banner->toArray(), true );

		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's purge hook; a no-op without it.

		/**
		 * Fires after the alert banner is saved. Purge other page caches here.
		 *
		 * @param Banner $banner The saved banner.
		 */
		do_action( 'favr_sites_banner_saved', $banner );
	}
}
