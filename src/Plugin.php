<?php
/**
 * Composition root.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites;

/**
 * Wires every unit.
 */
final class Plugin {

	/**
	 * Booted.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/** Boot once. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		( new Comments\Off() )->hook();
		( new Editors\PageLock() )->hook();
		( new Editors\BlockList() )->hook();
		( new Editors\Routing() )->hook();

		if ( is_admin() ) {
			( new Admin\SettingsPage() )->hook();
			( new Dashboard\Takeover() )->hook();
			( new Editors\ListTables() )->hook();
			( new Editors\Menu() )->hook();
		}
	}
}
