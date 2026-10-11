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
	 * Publish-menu items Editors don't get: display conditions stay Favr's (Editors can't save
	 * them anyway). "Save Draft" stays: on a published document Elementor only autosaves.
	 */
	public const HIDE = array( 'document-display-conditions' );

	/** Top-bar tools Editors never need: the design system (classes and variables) and page settings. */
	public const HIDE_TOOLS = array( 'open-design-system-toolbar', 'document-settings-button' );

	/** Top-bar utilities Editors never need: the Finder (it searches Elementor's own screens). */
	public const HIDE_UTILITIES = array( 'toggle-finder' );

	/**
	 * Widget groups Editors never need: Elementor's new "atomic" elements and AI widget builder,
	 * kits for other themes and uses, single-post parts and shop widgets, other tools' own.
	 */
	public const HIDE_CATEGORIES = array( 'v4-elements', 'atomic-form', 'custom-widgets', 'helloplus', 'link-in-bio', 'theme-elements-single', 'woocommerce-elements', 'emcp-custom' );

	/** The widget group shown first. */
	public const FIRST_CATEGORY = 'favr';

	/**
	 * Pure: is a widget offered in the panel and its search? Not when every group it belongs to
	 * is hidden. (assets/elementor/editor.js applies the same rule in the browser.)
	 *
	 * @param list<string> $categories The widget's groups.
	 * @param list<string> $hidden     Hidden groups.
	 */
	public static function showsWidget( array $categories, array $hidden ): bool {
		return ! $categories || array() !== array_diff( $categories, $hidden );
	}

	/** Elementor editor packages Editors do not get: the AI widget builder and its "install Angie" prompt. */
	public const DROP_PACKAGES = array( 'editor-widget-creation' );

	/**
	 * Pure: the editor packages a user gets.
	 *
	 * @param array<int|string, string> $packages  Elementor's packages.
	 * @param bool                      $is_editor In the Favr audience.
	 * @return list<string>|array<int|string, string>
	 */
	public static function packagesFor( array $packages, bool $is_editor ): array {
		return $is_editor ? array_values( array_diff( $packages, self::DROP_PACKAGES ) ) : $packages;
	}

	/**
	 * Pure: the editor settings a user gets. Editors are never shown the "install Angie" guide,
	 * which Elementor opens by itself once per site.
	 *
	 * @param array<string, mixed> $settings  Elementor's settings.
	 * @param bool                 $is_editor In the Favr audience.
	 * @return array<string, mixed>
	 */
	public static function settingsFor( array $settings, bool $is_editor ): array {
		if ( $is_editor && isset( $settings['angie'] ) && is_array( $settings['angie'] ) ) {
			unset( $settings['angie']['autoShow'] );
		}
		return $settings;
	}

	/** Pure: the CSS that hides the hidden groups and puts Favr's first. */
	public static function panelCss(): string {
		$hide = implode( ',', array_map( static fn( string $id ): string => '#elementor-panel-category-' . $id, self::HIDE_CATEGORIES ) );
		return $hide . '{display:none!important}#elementor-panel-categories{display:flex;flex-direction:column}#elementor-panel-category-' . self::FIRST_CATEGORY . '{order:-1}';
	}

	/**
	 * Where Exit goes: the Header & footer screen for those templates, else the Pages list.
	 *
	 * @param string|null $role Protect role of the document being edited.
	 * @return array{label: string, href: string}
	 */
	public static function back( ?string $role ): array {
		if ( 'header' === $role || 'footer' === $role ) {
			return array(
				'label' => __( 'Back to Header & footer', 'favr-sites' ),
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
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'styles' ), 20 );
		add_filter( 'elementor/editor/v2/packages', array( $this, 'packages' ), PHP_INT_MAX );
		add_filter( 'elementor/editor/localize_settings', array( $this, 'settings' ), PHP_INT_MAX );
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
				'help'           => self::helpLink( Links::all() ),
				'back'           => self::back( Protect::role( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0, Protect::ids() ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'hide'           => self::HIDE,
				'hideTools'      => self::HIDE_TOOLS,
				'hideUtilities'  => self::HIDE_UTILITIES,
				'hideCategories' => self::HIDE_CATEGORIES,
			)
		);
	}

	/** The editor in Favr's colours, with a shorter widget panel. */
	public function styles(): void {
		if ( ! Audience::current() ) {
			return;
		}
		$file = 'assets/elementor/editor.css';
		wp_enqueue_style( 'favr-sites-elementor-editor', FAVR_SITES_URL . $file, array(), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file ) );
		wp_add_inline_style( 'favr-sites-elementor-editor', self::panelCss() );
	}

	/**
	 * Elementor's editor packages, without the ones Editors do not get.
	 *
	 * @param mixed $packages Packages.
	 * @return mixed
	 */
	public function packages( $packages ) {
		return is_array( $packages ) ? self::packagesFor( $packages, Audience::current() ) : $packages;
	}

	/**
	 * Elementor's editor settings.
	 *
	 * @param mixed $settings Settings.
	 * @return mixed
	 */
	public function settings( $settings ) {
		return is_array( $settings ) ? self::settingsFor( $settings, Audience::current() ) : $settings;
	}
}
