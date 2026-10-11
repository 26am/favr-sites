<?php
/**
 * Safer keys on the Approvals screen, for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * Each card on Approvals is one form whose first button is Approve, so Enter in the note field
 * approved the item the note was meant to reject, and Reject acted on one click. The markup is the
 * shared library's; for Editors a small script makes Enter in a note move to Reject, and Reject
 * ask once before it sends. Approve stays one click.
 */
final class Approvals {

	private const PAGE   = 'favr-approvals';
	private const SCRIPT = 'assets/skin/approvals.js';

	/**
	 * Does this request get the script?
	 *
	 * @param bool   $editor Whether the user is an Editor.
	 * @param string $page   The admin page in the address.
	 */
	public static function wanted( bool $editor, string $page ): bool {
		return $editor && self::PAGE === $page;
	}

	/**
	 * The questions Reject asks. %s is the item's title.
	 *
	 * @return array{withNote: string, noNote: string}
	 */
	public static function words(): array {
		return array(
			/* translators: %s: the title of the item being rejected. */
			'withNote' => __( 'Reject “%s”? Your note is sent to the person.', 'favr-sites' ),
			/* translators: %s: the title of the item being rejected. */
			'noNote'   => __( 'Reject “%s” without a note to the person?', 'favr-sites' ),
		);
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/** Load the script on the Approvals screen, for Editors. */
	public function assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen this is; nothing is saved.
		if ( ! self::wanted( Audience::current(), $page ) ) {
			return;
		}
		wp_enqueue_script( 'favr-sites-approvals', FAVR_SITES_URL . self::SCRIPT, array(), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . self::SCRIPT ), true );
		wp_localize_script( 'favr-sites-approvals', 'favrApprovals', self::words() );
	}
}
