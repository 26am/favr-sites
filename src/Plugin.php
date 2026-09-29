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
		( new Site\Protect() )->hook();
		( new Editors\BlockList() )->hook();
		( new Editors\Routing() )->hook();
		( new Editors\AdminBar() )->hook();
		( new Brand\ColorScheme() )->hook();
		( new Editors\ElementorEditor() )->hook();
		( new Profile\ProfilePage() )->hook();
		( new Editors\Menu() )->hook(); // Also on the front end: the admin bar uses the "News" labels.

		if ( is_admin() ) {
			( new Admin\SettingsPage() )->hook();
			( new Menus\Slots() )->hook();
			( new Menus\Screen() )->hook();
			( new Site\Setup() )->hook();
			( new Dashboard\Takeover() )->hook();
			( new Editors\ListTables() )->hook();
		}
	}
}
