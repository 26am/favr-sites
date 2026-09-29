<?php
/**
 * A calmer Elementor editor for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Site\Protect;
use FavrSites\Help\Links;

/**
 * In the Elementor editor, Editors don't get Site Settings, Theme Builder, "Connect my account",
 * Angie, "Send feedback" or the "What's new" megaphone; Help goes to Favr and Exit is
 * "Back to Pages". Done through Elementor's own menu registry (assets/elementor/editor.js), so an
 * item Elementor renames simply falls back to its default.
 */
final class ElementorEditor {

	/**
	 * Where "Help" goes: the Favr help centre, else an email to Favr, else nowhere.
	 *
	 * @param array<string, string> $links Links::all().
	 * @return array{label: string, href: string}|null
	 */
	public static function helpLink( array $links ): ?array {
		if ( '' !== ( $links['help_url'] ?? '' ) ) {
			return array(
				'label' => __( 'Favr help', 'favr-sites' ),
				'href'  => $links['help_url'],
			);
		}
		if ( '' !== ( $links['support_email'] ?? '' ) ) {
			return array(
				'label' => __( 'Email Favr', 'favr-sites' ),
				'href'  => 'mailto:' . $links['support_email'],
			);
		}
		return null;
	}

	/**
	 * Where Exit goes: the Header & Footer screen for those templates, else the Pages list.
	 *
	 * @param string|null $role Protect role of the document being edited.
	 * @return array{label: string, href: string}
	 */
	public static function back( ?string $role ): array {
		if ( 'header' === $role || 'footer' === $role ) {
			return array(
				'label' => __( 'Back to Header & Footer', 'favr-sites' ),
				'href'  => admin_url( 'admin.php?page=favr-menus' ),
			);
		}
		return array(
			'label' => __( 'Back to Pages', 'favr-sites' ),
			'href'  => admin_url( 'edit.php?post_type=page' ),
		);
	}

	/** Hooks. */
	public function hook(): void {
		// After Elementor's own enqueues (priority 10) on both of its editor hooks.
		add_action( 'elementor/editor/v2/scripts/enqueue', array( $this, 'enqueue' ), 20 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/** Load the Favr editor tweaks and drop the "What's new" megaphone, for Editors. */
	public function enqueue(): void {
		if ( ! Audience::current() ) {
			return;
		}
		wp_dequeue_script( 'e-editor-notifications' );
		if ( wp_script_is( 'favr-sites-elementor-editor', 'enqueued' ) ) {
			return;
		}
		$file = 'assets/elementor/editor.js';
		wp_enqueue_script( 'favr-sites-elementor-editor', FAVR_SITES_URL . $file, array( 'elementor-v2-editor-app-bar' ), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file ), true );
		wp_localize_script(
			'favr-sites-elementor-editor',
			'favrSitesElementor',
			array(
				'help' => self::helpLink( Links::all() ),
				'back' => self::back( Protect::role( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0, Protect::ids() ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Favr keeps the Header and Footer published and site-wide.
				'hide' => array( 'document-save-draft', 'document-display-conditions' ),
			)
		);
	}
}
