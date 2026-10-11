<?php
/**
 * The News post editor in Favr colours, without WordPress's welcome tour, for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * For Editors, the block editor (News) gets Favr's accent instead of WordPress blue, starts
 * without the "Welcome to the editor" tour, publishes in one press, says "All news" on its way
 * back, and loses the Slug, Author and Template rows like every other edit screen. Its notes
 * feature is off: comments are off on Favr sites, so its request could only fail.
 * Administrators keep WordPress's editor as it is.
 */
final class BlockEditor {

	public const HANDLE = 'favr-sites-block-editor';

	/** Preference defaults Editors start with (assets/editor/editor.js applies them; a choice the Editor makes still wins). */
	public const DEFAULTS = array(
		'core/edit-post' => array( 'welcomeGuide' => false ),
		'core'           => array( 'isPublishSidebarEnabled' => false ),
	);

	/** Favr rust and its darker shade (--favr-accent and --favr-accent-strong in assets/dashboard/tokens.css). */
	public const ACCENT        = '#a9472a';
	public const ACCENT_STRONG = '#8e3a21';

	/**
	 * Pure: the accent inside the writing area. It is a frame of its own, which the page's sheets
	 * and tokens do not reach, so the two colours are written out.
	 */
	public static function canvasCss(): string {
		return ':root{--wp-admin-theme-color:' . self::ACCENT . ';--wp-admin-theme-color--rgb:169,71,42;'
			. '--wp-admin-theme-color-darker-10:' . self::ACCENT_STRONG . ';--wp-admin-theme-color-darker-10--rgb:142,58,33;'
			. '--wp-admin-theme-color-darker-20:' . self::ACCENT_STRONG . ';--wp-admin-theme-color-darker-20--rgb:142,58,33}';
	}

	/**
	 * Pure: the editor settings a user gets. Editors are not offered a template for the post, and
	 * the writing area takes Favr's accent.
	 *
	 * @param array<string, mixed> $settings  WordPress's settings.
	 * @param bool                 $is_editor In the Favr audience.
	 * @return array<string, mixed>
	 */
	public static function settingsFor( array $settings, bool $is_editor ): array {
		if ( ! $is_editor ) {
			return $settings;
		}
		$settings['availableTemplates'] = array();
		$styles                         = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$styles[]                       = array(
			'css'            => self::canvasCss(),
			'__unstableType' => 'user',
			'isGlobalStyles' => false,
		);
		$settings['styles']             = $styles;
		return $settings;
	}

	/**
	 * What the editor says after each save of a news post ("Post published." otherwise).
	 *
	 * @return array<string, string>
	 */
	public static function newsNotices(): array {
		return array(
			'item_published'           => __( 'News post published.', 'favr-sites' ),
			'item_published_privately' => __( 'News post published privately.', 'favr-sites' ),
			'item_reverted_to_draft'   => __( 'News post moved back to draft.', 'favr-sites' ),
			'item_scheduled'           => __( 'News post scheduled.', 'favr-sites' ),
			'item_updated'             => __( 'News post updated.', 'favr-sites' ),
			'item_trashed'             => __( 'News post moved to the trash.', 'favr-sites' ),
		);
	}

	/**
	 * Pure: a post type's labels with the words of the editor's back arrow ("All news") and of
	 * its notices, on a copy.
	 *
	 * @param object                $labels  The post type's labels.
	 * @param array<string, string> $notices Notice labels to set.
	 */
	public static function labelsFor( object $labels, array $notices = array() ): object {
		$all = trim( (string) ( $labels->all_items ?? '' ) );
		if ( '' === $all && ! $notices ) {
			return $labels;
		}
		$copy = clone $labels;
		if ( '' !== $all ) {
			$copy->view_items = $all;
		}
		foreach ( $notices as $key => $words ) {
			$copy->$key = $words;
		}
		return $copy;
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'init', array( $this, 'withoutNotes' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'settings' ), PHP_INT_MAX );
		add_filter( 'rest_prepare_post_type', array( $this, 'labels' ), PHP_INT_MAX );
		add_filter( 'rest_prepare_post', array( $this, 'withoutAuthorChoice' ), PHP_INT_MAX );
	}

	/** News without the editor's notes (they are comments, which are off), for Editors. */
	public function withoutNotes(): void {
		if ( Audience::current() && post_type_supports( 'post', 'editor' ) ) {
			add_post_type_support( 'post', 'editor' ); // Replaces `editor => notes`.
		}
	}

	/** The sheet and the script, for Editors. */
	public function enqueue(): void {
		if ( ! Audience::current() ) {
			return;
		}
		$tokens = 'assets/dashboard/tokens.css';
		$sheet  = 'assets/editor/editor.css';
		$script = 'assets/editor/editor.js';
		wp_enqueue_style( 'favr-sites-tokens', FAVR_SITES_URL . $tokens, array(), self::version( $tokens ) );
		wp_enqueue_style( self::HANDLE, FAVR_SITES_URL . $sheet, array( 'favr-sites-tokens' ), self::version( $sheet ) );
		wp_enqueue_script( self::HANDLE, FAVR_SITES_URL . $script, array( 'wp-data', 'wp-preferences', 'wp-dom-ready', 'wp-edit-post' ), self::version( $script ), true );
		wp_localize_script( self::HANDLE, 'favrSitesBlockEditor', array( 'defaults' => self::DEFAULTS ) );
	}

	/**
	 * The editor's settings.
	 *
	 * @param mixed $settings Settings.
	 * @return mixed
	 */
	public function settings( $settings ) {
		return is_array( $settings ) ? self::settingsFor( $settings, Audience::current() ) : $settings;
	}

	/**
	 * What the editor is told about a post type: the words of its back arrow and notices, for Editors.
	 *
	 * @param mixed $response REST response.
	 * @return mixed
	 */
	public function labels( $response ) {
		if ( $response instanceof \WP_REST_Response && Audience::current() ) {
			$data = $response->get_data();
			if ( is_array( $data ) && isset( $data['labels'] ) && is_object( $data['labels'] ) ) {
				$is_news        = isset( $data['slug'] ) && 'post' === $data['slug'];
				$data['labels'] = self::labelsFor( $data['labels'], $is_news ? self::newsNotices() : array() );
				$response->set_data( $data );
			}
		}
		return $response;
	}

	/**
	 * What the editor is told about a news post: Editors are not offered another author.
	 *
	 * @param mixed $response REST response.
	 * @return mixed
	 */
	public function withoutAuthorChoice( $response ) {
		if ( $response instanceof \WP_REST_Response && Audience::current() ) {
			$response->remove_link( 'https://api.w.org/action-assign-author' );
		}
		return $response;
	}

	/**
	 * Cache-busting version: plugin version + file time.
	 *
	 * @param string $file Path inside the plugin.
	 */
	private static function version( string $file ): string {
		return FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file );
	}
}
