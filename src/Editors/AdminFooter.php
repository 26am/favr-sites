<?php
/**
 * No WordPress credit under an Editor's screens.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * "Thank you for creating with WordPress" and the version number mean nothing to the people who
 * keep a Favr site up to date, so Editors do not see them. Administrators do.
 */
final class AdminFooter {

	/**
	 * Pure: the footer text a user sees.
	 *
	 * @param mixed $text      WordPress's text.
	 * @param bool  $is_editor In the Favr audience.
	 */
	public static function text( $text, bool $is_editor ): string {
		return $is_editor ? '' : (string) $text;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'admin_footer_text', array( $this, 'filter' ), PHP_INT_MAX );
		add_filter( 'update_footer', array( $this, 'filter' ), PHP_INT_MAX );
	}

	/**
	 * Either half of the footer.
	 *
	 * @param mixed $text WordPress's text.
	 */
	public function filter( $text ): string {
		return self::text( $text, Audience::current() );
	}
}
