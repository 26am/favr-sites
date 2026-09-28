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

		if ( is_admin() ) {
			( new Admin\SettingsPage() )->hook();
			( new Dashboard\Takeover() )->hook();
		}
	}
}
