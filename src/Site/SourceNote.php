<?php
/**
 * The note at the top of every page's source.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

/**
 * Anyone who views a Favr site's source reads who made it: a comment and a generator tag, the
 * first thing printed in `wp_head` (as close to the top as a plugin gets without rewriting the
 * page). Always on, whatever the footer credit's switch says: David, 2026-10-20. The generator
 * tag has no version, so it tells nobody which release to attack.
 */
final class SourceNote {

	/**
	 * Printed already (once per page).
	 *
	 * @var bool
	 */
	private bool $printed = false;

	/** Hook. */
	public function hook(): void {
		add_action( 'wp_head', array( $this, 'print' ), PHP_INT_MIN );
	}

	/** The note: constant text, nothing from the site or a visitor. */
	public static function markup(): string {
		return "<!--\n\tThis site is powered by Favr.\n\tWebsites for Associations, Clubs and Chambers: https://favr.site/\n-->\n<meta name=\"generator\" content=\"Favr\">\n";
	}

	/** Print it once, on public pages (not in Elementor's editor). */
	public function print(): void {
		if ( $this->printed || is_admin() || is_feed() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}
		$this->printed = true;
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constant markup.
	}
}
