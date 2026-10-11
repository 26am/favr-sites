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
 * Columns: no Comments, no Yoast or Rank Math scores.
 * Dates: "Oct 8, 2026", with a status word only when the item is not published.
 * Filters: no month filter on Favr's own lists (events have their own dates; members, listings,
 * documents, people and sponsors are not looked up by the month they were added).
 */
final class ListTables {

	private const KEEP = array( 'view', 'trash', 'untrash', 'delete' );

	/** Column key prefixes of the SEO plugins. */
	private const SEO_COLUMNS = array( 'wpseo-', 'rank_math_' );

	/** Post types whose list has no month filter. */
	public const NO_MONTHS = array( 'favr_business', 'favr_event', 'favr_member', 'favr_document', 'favr_person', 'favr_sponsor' );

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
	 * Drop the Comments, Yoast and Rank Math columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( $columns ) {
		foreach ( array_keys( (array) $columns ) as $key ) {
			if ( 'comments' === $key || self::isSeoColumn( (string) $key ) ) {
				unset( $columns[ $key ] );
			}
		}
		return $columns;
	}

	/**
	 * A column an SEO plugin added?
	 *
	 * @param string $key Column key.
	 */
	private static function isSeoColumn( string $key ): bool {
		foreach ( self::SEO_COLUMNS as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The word above a date. A published item needs none; anything else keeps WordPress's word
	 * ("Scheduled", "Missed schedule", "Last modified"), in sentence case.
	 *
	 * @param string $status      WordPress's word (may carry markup).
	 * @param string $post_status The item's status.
	 */
	public static function dateStatus( string $status, string $post_status ): string {
		if ( 'publish' === $post_status ) {
			return '';
		}
		return 'Last Modified' === $status ? __( 'Last modified', 'favr-sites' ) : $status;
	}

	/**
	 * The date as "Oct 8, 2026" instead of "2026/10/08 at 11:02 pm". An item without a date keeps
	 * WordPress's text ("Unpublished").
	 *
	 * @param string $text      WordPress's text.
	 * @param string $post_date The item's date in the site's time (post_date).
	 */
	public static function dateText( string $text, string $post_date ): string {
		$stamp = '' === $post_date || str_starts_with( $post_date, '0000-00-00' ) ? false : strtotime( $post_date );
		if ( false === $stamp ) {
			return $text;
		}
		/* translators: Date in a list, e.g. "Oct 8, 2026". See https://www.php.net/manual/datetime.format.php */
		return (string) date_i18n( __( 'M j, Y', 'favr-sites' ), $stamp );
	}

	/**
	 * No month filter on Favr's own lists; News, Pages and Media keep theirs.
	 *
	 * @param mixed  $disable   Already turned off by somebody else.
	 * @param string $post_type Post type of the list.
	 */
	public static function hidesMonths( $disable, $post_type ): bool {
		return (bool) $disable || in_array( (string) $post_type, self::NO_MONTHS, true );
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'current_screen', array( $this, 'screen' ) );
	}

	/**
	 * On list screens (posts of any type, and the Media Library as a list), for Editors only.
	 *
	 * @param \WP_Screen $screen Screen.
	 */
	public function screen( $screen ): void {
		if ( ! $screen || ! in_array( $screen->base, array( 'edit', 'upload' ), true ) || ! Audience::current() ) {
			return;
		}
		if ( 'upload' === $screen->base ) {
			// The Media Library as a list: the same dates as every other list.
			add_filter( 'media_date_column_time', array( $this, 'time' ), 10, 2 );
			return;
		}
		add_filter( 'post_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		add_filter( 'page_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		// The screen-id filter runs after the post-type ones, so it also catches Yoast's columns.
		add_filter( 'manage_' . $screen->id . '_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_filter( 'post_date_column_status', array( $this, 'status' ), 10, 2 );
		add_filter( 'post_date_column_time', array( $this, 'time' ), 10, 2 );
		add_filter( 'disable_months_dropdown', array( self::class, 'hidesMonths' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'css' ) );
	}

	/**
	 * The status word in the Date column.
	 *
	 * @param mixed $status WordPress's word.
	 * @param mixed $post   The row's post.
	 * @return mixed
	 */
	public function status( $status, $post ) {
		return $post instanceof \WP_Post ? self::dateStatus( (string) $status, (string) $post->post_status ) : $status;
	}

	/**
	 * The date in the Date column.
	 *
	 * @param mixed $text WordPress's text.
	 * @param mixed $post The row's post.
	 * @return mixed
	 */
	public function time( $text, $post ) {
		return $post instanceof \WP_Post ? self::dateText( (string) $text, (string) $post->post_date ) : $text;
	}

	/** Bin icon styling (the colours are the skin's tokens; the greys are for a screen without them). */
	public function css(): void {
		echo '<style>.favr-trash{color:var(--favr-muted,#8c8f94)!important;display:inline-flex;vertical-align:middle}.favr-trash:hover,.favr-trash:focus{color:var(--favr-danger,#b32d2e)!important}.favr-trash svg{display:block}</style>';
	}
}
