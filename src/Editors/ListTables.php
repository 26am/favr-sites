<?php
/**
 * Calmer post lists for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Dashboard\Icons;

/**
 * Row links: the title opens the right editor; "View" stays; Trash becomes a quiet bin icon.
 * Columns: no Comments, no Yoast scores.
 */
final class ListTables {

	private const KEEP = array( 'view', 'trash', 'untrash', 'delete' );

	/**
	 * Keep only the row actions an Editor needs, in a fixed order.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public static function rowActions( $actions ) {
		$out = array();
		foreach ( self::KEEP as $key ) {
			if ( isset( $actions[ $key ] ) ) {
				$out[ $key ] = 'trash' === $key ? self::trashIcon( (string) $actions[ $key ] ) : $actions[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Core's Trash link as a small icon (the href is already escaped by core).
	 *
	 * @param string $html Core link.
	 */
	public static function trashIcon( string $html ): string {
		if ( ! preg_match( '/href="([^"]+)"/', $html, $match ) ) {
			return $html;
		}
		$label = esc_attr__( 'Move to Trash', 'favr-sites' );
		return '<a href="' . $match[1] . '" class="favr-trash submitdelete" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . Icons::svg( 'trash', 16 ) . '</a>';
	}

	/**
	 * Drop Comments and Yoast columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( $columns ) {
		foreach ( array_keys( (array) $columns ) as $key ) {
			if ( 'comments' === $key || str_starts_with( (string) $key, 'wpseo-' ) ) {
				unset( $columns[ $key ] );
			}
		}
		return $columns;
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'current_screen', array( $this, 'screen' ) );
	}

	/**
	 * On list screens, for Editors only.
	 *
	 * @param \WP_Screen $screen Screen.
	 */
	public function screen( $screen ): void {
		if ( ! $screen || 'edit' !== $screen->base || ! Audience::current() ) {
			return;
		}
		add_filter( 'post_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		add_filter( 'page_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		// The screen-id filter runs after the post-type ones, so it also catches Yoast's columns.
		add_filter( 'manage_' . $screen->id . '_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_action( 'admin_head', array( $this, 'css' ) );
	}

	/** Bin icon styling. */
	public function css(): void {
		echo '<style>.favr-trash{color:#8c8f94!important;display:inline-flex;vertical-align:middle}.favr-trash:hover,.favr-trash:focus{color:#b32d2e!important}.favr-trash svg{display:block}</style>';
	}
}
