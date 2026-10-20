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
		( new Editors\Menu() )->hook(); // Also on the front end: the admin bar uses the News, Pages and Media labels.
		( new Banner\Display() )->hook();
		( new Site\Credit() )->hook(); // The footer credit; its switch is saved through admin-post.
		( new Site\SourceNote() )->hook(); // "This site is powered by Favr." at the top of the page source.
		( new Editors\Search() )->hook();
		( new Editors\BlockEditor() )->hook();
		( new Editors\Pages() )->hook();
		( new Editors\ErrorPage() )->hook();
		( new Editors\Approvals() )->hook();

		if ( is_admin() ) {
			( new Admin\SettingsPage() )->hook();
			( new Menus\Slots() )->hook();
			( new Menus\Screen() )->hook();
			( new Banner\Screen() )->hook();
			( new Site\Setup() )->hook();
			( new Dashboard\Takeover() )->hook();
			( new Editors\Words() )->hook();
			( new Editors\ListTables() )->hook();
			( new Editors\Terms() )->hook();
			( new Editors\Chrome() )->hook();
			( new Editors\Wayfinding() )->hook();
			( new Editors\AdminFooter() )->hook();
			( new Skin\Skin() )->hook();
		}
	}
}
