<?php
/**
 * The admin bar for Editors: a Favr help menu instead of WordPress's, a friendlier account menu,
 * a "New" menu in Favr's order and no Elementor dropdown.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Help\Links;

/**
 * Admin bar clean-up, in wp-admin and on the public site.
 */
final class AdminBar {

	private const LOGO_PARENTS = array( 'wp-logo', 'wp-logo-default', 'wp-logo-external' );
	private const NEW_PARENTS  = array( 'new-content', 'new-content-default' );

	/**
	 * What "New" offers, by post type: first what the dashboard's quick actions add, in their order
	 * (Dashboard\Items::ACTION_ORDER: event, news post, member, directory listing, document), then
	 * the rest in the menu's order, Media last. Other post types are not offered.
	 */
	public const NEW_ORDER = array( 'favr_event', 'post', 'favr_member', 'favr_business', 'favr_document', 'page', 'favr_person', 'favr_sponsor', 'attachment' );

	/**
	 * Help menu items from the Favr help links; empty links are skipped.
	 *
	 * @param array<string, string> $links Links::all().
	 * @return list<array{id: string, title: string, href: string}>
	 */
	public static function helpItems( array $links ): array {
		$items = array();
		if ( '' !== ( $links['help_url'] ?? '' ) ) {
			$items[] = array(
				'id'    => 'favr-help',
				'title' => esc_html__( 'Help center', 'favr-sites' ),
				'href'  => $links['help_url'],
			);
		}
		if ( '' !== ( $links['support_email'] ?? '' ) ) {
			$items[] = array(
				'id'    => 'favr-email',
				/* translators: %s: support email address. */
				'title' => sprintf( esc_html__( 'Email %s', 'favr-sites' ), esc_html( $links['support_email'] ) ),
				'href'  => 'mailto:' . $links['support_email'],
			);
		}
		if ( '' !== ( $links['support_phone'] ?? '' ) ) {
			$items[] = array(
				'id'    => 'favr-phone',
				/* translators: %s: support phone number. */
				'title' => sprintf( esc_html__( 'Call %s', 'favr-sites' ), esc_html( $links['support_phone'] ) ),
				'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $links['support_phone'] ),
			);
		}
		if ( '' !== ( $links['booking_url'] ?? '' ) ) {
			$items[] = array(
				'id'    => 'favr-book',
				'title' => esc_html__( 'Book a call', 'favr-sites' ),
				'href'  => $links['booking_url'],
			);
		}
		return $items;
	}

	/**
	 * Everything WordPress puts under its logo.
	 *
	 * @param array<string, string> $parents Node id => parent id.
	 * @return list<string>
	 */
	public static function logoNodesToRemove( array $parents ): array {
		$out = array();
		foreach ( $parents as $id => $parent ) {
			if ( 'wp-logo-default' !== $id && in_array( $parent, self::LOGO_PARENTS, true ) ) {
				$out[] = (string) $id;
			}
		}
		return $out;
	}

	/**
	 * Everything WordPress put under "New"; Favr's entries are added in their place.
	 *
	 * @param array<string, string> $parents Node id => parent id.
	 * @return list<string>
	 */
	public static function newNodesToRemove( array $parents ): array {
		$out = array();
		foreach ( $parents as $id => $parent ) {
			if ( in_array( $parent, self::NEW_PARENTS, true ) && ! in_array( $id, self::NEW_PARENTS, true ) ) {
				$out[] = (string) $id;
			}
		}
		return $out;
	}

	/**
	 * The entries of "New", in Favr's order, each named by its post type in sentence case.
	 *
	 * @param array<string, string> $labels Post type => its singular name, for the post types that
	 *                                      exist and the user may add to.
	 * @return list<array{id: string, title: string, path: string}> `path` is inside wp-admin.
	 */
	public static function newItems( array $labels ): array {
		$items = array();
		foreach ( self::NEW_ORDER as $type ) {
			$title = Words::sentence( (string) ( $labels[ $type ] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$items[] = array(
				'id'    => 'attachment' === $type ? 'new-media' : 'new-' . $type,
				'title' => $title,
				'path'  => self::newPath( $type ),
			);
		}
		return $items;
	}

	/**
	 * Where a post type is added.
	 *
	 * @param string $type Post type.
	 */
	private static function newPath( string $type ): string {
		$paths = array(
			'post'       => 'post-new.php',
			'attachment' => 'media-new.php',
		);
		return $paths[ $type ] ?? 'post-new.php?post_type=' . rawurlencode( $type );
	}

	/**
	 * The magnifier's label in Favr's words: "Search" in front of the keyboard shortcut WordPress
	 * shows, and what it finds for screen readers. The icon and the shortcut stay as they are.
	 *
	 * @param string $title The toolbar entry's HTML, as WordPress wrote it.
	 * @param string $word  The visible word.
	 * @param string $label What the search finds.
	 */
	public static function searchTitle( string $title, string $word, string $label ): string {
		$safe  = static fn( string $text ): string => addcslashes( esc_html( $text ), '\\$' );
		$named = preg_replace(
			array( '#<kbd>#', '#(<span class="screen-reader-text">)[^<]*(</span>)#' ),
			array( $safe( $word ) . ' <kbd>', '$1 ' . $safe( $label ) . '$2' ),
			$title,
			1
		);
		return is_string( $named ) ? $named : $title;
	}

	/**
	 * The link to the public site under the site's name: the dashboard's words for it ("View
	 * site"), in a new tab so the Editor keeps their place.
	 *
	 * @return array{id: string, title: string, meta: array{target: string}}
	 */
	public static function viewSite(): array {
		return array(
			'id'    => 'view-site',
			'title' => esc_html__( 'View site', 'favr-sites' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'favr-sites' ) . '</span>',
			'meta'  => array( 'target' => '_blank' ),
		);
	}

	/**
	 * What to call the user.
	 *
	 * @param string $first   First name.
	 * @param string $display Display name.
	 */
	public static function name( string $first, string $display ): string {
		return '' !== trim( $first ) ? trim( $first ) : $display;
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_bar_menu', array( $this, 'bar' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/admin_bar/settings', array( $this, 'elementor' ), PHP_INT_MAX );
		add_action( 'wp_head', array( $this, 'css' ) );
		add_action( 'admin_head', array( $this, 'css' ) );
	}

	/**
	 * Rework the bar for Editors.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function bar( $bar ): void {
		if ( ! Audience::current() ) {
			return;
		}
		$parents = array();
		foreach ( (array) $bar->get_nodes() as $node ) {
			$parents[ $node->id ] = (string) $node->parent;
		}

		// WordPress menu → Favr help menu, in the same place.
		if ( isset( $parents['wp-logo'] ) ) {
			foreach ( self::logoNodesToRemove( $parents ) as $id ) {
				$bar->remove_node( $id );
			}
			$bar->add_node(
				array(
					'id'    => 'wp-logo',
					'title' => '<span class="favr-ab-logo" aria-hidden="true"><span class="favr-ab-f">f</span>favr</span><span class="screen-reader-text">' . esc_html__( 'Favr help', 'favr-sites' ) . '</span>',
					'href'  => admin_url(),
				)
			);
			foreach ( self::helpItems( Links::all() ) as $item ) {
				$item['parent'] = 'wp-logo';
				$bar->add_node( $item );
			}
		}

		// "New": everything this Editor can add, in the dashboard's order. Its own link adds the first.
		foreach ( self::newNodesToRemove( $parents ) as $id ) {
			$bar->remove_node( $id );
		}
		if ( isset( $parents['new-content'] ) ) {
			$items = self::newItems( self::addableLabels() );
			if ( ! $items ) {
				$bar->remove_node( 'new-content' );
			} else {
				$bar->add_node(
					array(
						'id'   => 'new-content',
						'href' => admin_url( $items[0]['path'] ),
					)
				);
			}
			foreach ( $items as $item ) {
				$bar->add_node(
					array(
						'parent' => 'new-content',
						'id'     => $item['id'],
						'title'  => esc_html( $item['title'] ),
						'href'   => admin_url( $item['path'] ),
					)
				);
			}
		}

		// The magnifier opens WordPress's palette, which for Editors is the Favr search (Editors\Search).
		$search = $bar->get_node( 'command-palette' );
		if ( $search ) {
			$bar->add_node(
				array(
					'id'    => 'command-palette',
					'title' => self::searchTitle( (string) $search->title, __( 'Search', 'favr-sites' ), __( 'Find a member, event or page', 'favr-sites' ) ),
				)
			);
		}

		if ( isset( $parents['view-site'] ) ) {
			$bar->add_node( self::viewSite() );
		}

		// Account menu: their name, "Site editor", "Your profile", "Log out".
		$user = wp_get_current_user();
		$name = self::name( (string) $user->first_name, (string) $user->display_name );
		if ( isset( $parents['my-account'] ) ) {
			$bar->add_node(
				array(
					'id'    => 'my-account',
					'title' => '<span class="display-name">' . esc_html( $name ) . '</span>' . get_avatar( $user->ID, 26 ),
				)
			);
		}
		if ( isset( $parents['user-info'] ) ) {
			$bar->add_node(
				array(
					'id'    => 'user-info',
					'title' => get_avatar( $user->ID, 64 )
						. '<span class="display-name">' . esc_html( (string) $user->display_name ) . '</span>'
						. '<span class="username">' . esc_html__( 'Site editor', 'favr-sites' ) . '</span>'
						. '<span class="display-name edit-profile">' . esc_html__( 'Your profile', 'favr-sites' ) . '</span>',
				)
			);
		}
		if ( isset( $parents['logout'] ) ) {
			$bar->add_node(
				array(
					'id'    => 'logout',
					'title' => esc_html__( 'Log out', 'favr-sites' ),
				)
			);
		}
	}

	/**
	 * The post types on "New" that exist and that the user may add to, each with the name
	 * WordPress uses for it on the toolbar.
	 *
	 * @return array<string, string> Post type => name.
	 */
	private static function addableLabels(): array {
		$labels = array();
		foreach ( self::NEW_ORDER as $name ) {
			$type = get_post_type_object( $name );
			if ( $type && current_user_can( $type->cap->create_posts ) ) {
				$labels[ $name ] = (string) ( $type->labels->name_admin_bar ?? $type->labels->singular_name ?? '' );
			}
		}
		return $labels;
	}

	/**
	 * No Elementor "Edit with Elementor" dropdown for Editors: WordPress's own Edit link already
	 * opens pages in Elementor, and the dropdown also lists header/footer templates.
	 *
	 * @param array<string, mixed> $settings Elementor admin-bar config.
	 * @return array<string, mixed>
	 */
	public function elementor( $settings ) {
		if ( is_array( $settings ) && Audience::current() ) {
			unset( $settings['elementor_edit_page'] );
		}
		return $settings;
	}

	/** The Favr logo (rust "f" disc + wordmark in Fraunces) in place of the WordPress logo, and a quieter shortcut beside "Search". */
	public function css(): void {
		if ( ! is_admin_bar_showing() || ! Audience::current() ) {
			return;
		}
		$font = esc_url( FAVR_SITES_URL . 'assets/fonts/fraunces.woff2' );
		$css  = '@font-face{font-family:"Favr Display";src:url(' . $font . ') format("woff2");font-weight:400 700;font-display:swap}'
			. '#wpadminbar #wp-admin-bar-wp-logo>.ab-item{padding:0 10px}#wpadminbar #wp-admin-bar-wp-logo>.ab-item .ab-icon{display:none}'
			. '#wpadminbar .favr-ab-logo{display:inline-flex;align-items:center;gap:6px;font:600 16px/32px "Favr Display",Georgia,serif;color:inherit}'
			. '#wpadminbar #wp-admin-bar-command-palette kbd{margin-left:4px;font:inherit;opacity:.7}'
			. '#wpadminbar .favr-ab-f{display:inline-grid;place-items:center;width:20px;height:20px;border-radius:50%;background:#a9472a;color:#fbf6ef;font-size:13px;line-height:1}';
		echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS; the font URL is escaped above.
	}
}
