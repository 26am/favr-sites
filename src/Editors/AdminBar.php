<?php
/**
 * The admin bar for Editors: a Favr help menu instead of WordPress's, a friendlier account menu,
 * a shorter "New" menu and no Elementor dropdown.
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
	private const NEW_KEEP     = array( 'new-content-default', 'new-post', 'new-page', 'new-media' );

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
				'title' => esc_html__( 'Help centre', 'favr-sites' ),
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
	 * "New" entries other than News post, Page and Media.
	 *
	 * @param array<string, string> $parents Node id => parent id.
	 * @return list<string>
	 */
	public static function newNodesToRemove( array $parents ): array {
		$out = array();
		foreach ( $parents as $id => $parent ) {
			if ( in_array( $parent, self::NEW_PARENTS, true ) && ! in_array( $id, self::NEW_KEEP, true ) ) {
				$out[] = (string) $id;
			}
		}
		return $out;
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
					'title' => '<span class="favr-ab-mark" aria-hidden="true">favr</span><span class="screen-reader-text">' . esc_html__( 'Favr help', 'favr-sites' ) . '</span>',
					'href'  => admin_url(),
				)
			);
			foreach ( self::helpItems( Links::all() ) as $item ) {
				$item['parent'] = 'wp-logo';
				$bar->add_node( $item );
			}
		}

		// "New": News post, Page, Media.
		foreach ( self::newNodesToRemove( $parents ) as $id ) {
			$bar->remove_node( $id );
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

	/** The Favr mark in place of the WordPress logo; it takes the bar's own colours, so every admin colour scheme works. */
	public function css(): void {
		if ( ! is_admin_bar_showing() || ! Audience::current() ) {
			return;
		}
		echo '<style>#wpadminbar #wp-admin-bar-wp-logo>.ab-item{padding:0 10px}#wpadminbar #wp-admin-bar-wp-logo>.ab-item .ab-icon{display:none}#wpadminbar .favr-ab-mark{font:italic 600 17px/32px Georgia,"Times New Roman",serif;letter-spacing:-.02em;color:inherit}</style>';
	}
}
