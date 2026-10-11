<?php
/**
 * Calmer lists for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Dashboard\Icons;

/**
 * One rule for the links under a row, on every list (posts of any type, categories and levels,
 * Media as a list): the title opens the item; under it "View" (when there is a public page),
 * whatever a Favr plugin added (a key starting `favr-`), and a quiet bin. In the Trash the two
 * links stay words: deleting for good should not hide behind an icon. A row left with no link
 * keeps an empty placeholder, because WordPress prints the "Show more details" toggle a phone
 * needs together with the row's links, and only when there is at least one.
 * Columns: no Author, no Comments, no Yoast or Rank Math scores.
 * Dates: "Oct 8, 2026", with a status word only when the item is not published; a scheduled item
 * keeps its time.
 * Tabs: no "Mine"; no "Published" when it says the same as "All".
 * Rows: fifty a page (Editors have no Screen Options to choose). Members and the Directory open
 * A to Z until the Editor picks another order.
 * Filters: no month filter on Favr's own lists (events have their own dates; members, listings,
 * documents, people and sponsors are not looked up by the month they were added).
 */
final class ListTables {

	/** Row-action key (WordPress prints it as a class) of the placeholder for a row with no links. */
	public const NO_ACTIONS = 'favr-no-actions';

	/** Row-action keys a Favr plugin may add ("Rename" on pages, "Download" on documents). */
	private const OURS = 'favr-';

	/** Rows on a page of any list. */
	public const PER_PAGE = 50;

	/** A small lock, as a CSS mask, before the title of a page an Editor cannot open. */
	private const LOCK = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='5' y='11' width='14' height='10' rx='2'/%3E%3Cpath d='M8 11V7a4 4 0 0 1 8 0v4'/%3E%3C/svg%3E\")";

	/** The box WordPress asks a list thumbnail to fit (WP_Media_List_Table). */
	private const THUMB = 60;

	/** Columns Editors lose: who wrote it (they have no Author box either) and comments (off). */
	private const DROP_COLUMNS = array( 'author', 'comments' );

	/** Column key prefixes of the SEO plugins. */
	private const SEO_COLUMNS = array( 'wpseo-', 'rank_math_' );

	/** Post types whose list has no month filter. */
	public const NO_MONTHS = array( 'favr_business', 'favr_event', 'favr_member', 'favr_document', 'favr_person', 'favr_sponsor' );

	/** Post types that have no public page of their own, so "Published" is never the right word. */
	private const NEVER_PUBLIC = array( 'favr_member' );

	/** Lists that open A to Z by name: post type => the meta key holding the name to sort by ('' for the title). */
	private const A_TO_Z = array(
		'favr_member'   => '',
		'favr_business' => '_favr_sort', // Favr Directory's "Sort as" value (Model\Ranking::SORT_KEY).
	);

	/**
	 * The links under a post or page: View, a Favr plugin's own, the bin; in the Trash, Restore and
	 * Delete permanently. Never none: a draft the Editor may not edit has no View (WordPress only
	 * offers a preview to those who can edit it) and no Trash, and a row without links gets no
	 * "Show more details" toggle either, so on a phone its folded columns could not be opened.
	 *
	 * @param mixed $actions Actions.
	 * @return array<string, string>
	 */
	public static function rowActions( $actions ) {
		return self::tidy( $actions, array( 'view' ), array( 'trash' => __( 'Move to Trash', 'favr-sites' ) ), array( 'untrash', 'delete' ) );
	}

	/**
	 * The links under a category, group or level: View and the bin. WordPress's script still asks
	 * before deleting; it finds the link by the class the bin keeps.
	 *
	 * @param mixed $actions Actions.
	 * @return array<string, string>
	 */
	public static function termActions( $actions ) {
		return self::tidy( $actions, array( 'view' ), array( 'delete' => __( 'Delete', 'favr-sites' ) ), array() );
	}

	/**
	 * The links under a file: View, Copy URL and the bin (WordPress asks before deleting a file
	 * for good). "Download file" stays in the file's details. In the Trash, if a site keeps one for
	 * files, Restore and Delete permanently stay words.
	 *
	 * @param mixed $actions Actions.
	 * @return array<string, string>
	 */
	public static function mediaActions( $actions ) {
		$actions = is_array( $actions ) ? $actions : array();
		if ( isset( $actions['untrash'] ) ) {
			return self::tidy( $actions, array(), array(), array( 'untrash', 'delete' ) );
		}
		$bins = array(
			'trash'  => __( 'Move to Trash', 'favr-sites' ),
			'delete' => __( 'Delete permanently', 'favr-sites' ),
		);
		return self::tidy( $actions, array( 'view', 'copy' ), $bins, array() );
	}

	/**
	 * A row's links in one order: the plain ones, a Favr plugin's own, the bins, the rest.
	 *
	 * @param mixed                 $actions Actions as WordPress and the plugins built them.
	 * @param list<string>          $first   Keys kept as they are, first.
	 * @param array<string, string> $bins    Keys drawn as a bin => what the bin says when pointed at.
	 * @param list<string>          $last    Keys kept as they are, last.
	 * @return array<string, string>
	 */
	private static function tidy( $actions, array $first, array $bins, array $last ): array {
		$actions = is_array( $actions ) ? $actions : array();
		$out     = array();
		foreach ( $first as $key ) {
			if ( isset( $actions[ $key ] ) ) {
				$out[ $key ] = (string) $actions[ $key ];
			}
		}
		foreach ( $actions as $key => $html ) {
			if ( is_string( $key ) && str_starts_with( $key, self::OURS ) && self::NO_ACTIONS !== $key ) {
				$out[ $key ] = (string) $html;
			}
		}
		foreach ( $bins as $key => $label ) {
			if ( isset( $actions[ $key ] ) ) {
				$out[ $key ] = self::binIcon( (string) $actions[ $key ], $label );
			}
		}
		foreach ( $last as $key ) {
			if ( isset( $actions[ $key ] ) ) {
				$out[ $key ] = (string) $actions[ $key ];
			}
		}
		return $out ? $out : array( self::NO_ACTIONS => '' );
	}

	/**
	 * Core's Trash link as a small icon (kept for callers of the older name).
	 *
	 * @param string $html Core link.
	 */
	public static function trashIcon( string $html ): string {
		return self::binIcon( $html, __( 'Move to Trash', 'favr-sites' ) );
	}

	/**
	 * A link of WordPress's as a small bin: the same address, classes and question before deleting
	 * (all already escaped by WordPress), the word replaced by the icon. The label WordPress wrote
	 * for a screen reader ("Move “About” to the Trash") stays; pointing at the bin shows $label.
	 *
	 * @param string $html  WordPress's link.
	 * @param string $label What the bin does, in two or three words.
	 */
	public static function binIcon( string $html, string $label ): string {
		if ( ! preg_match( '/^\s*<a\s[^>]*href="([^"]*)"[^>]*>/', $html, $link ) ) {
			return $html;
		}
		$class = preg_match( '/\sclass="([^"]*)"/', $link[0], $match ) ? ' ' . $match[1] : '';
		$click = preg_match( '/\sonclick="([^"]*)"/', $link[0], $match ) ? ' onclick="' . $match[1] . '"' : '';
		$aria  = preg_match( '/\saria-label="([^"]*)"/', $link[0], $match ) ? $match[1] : esc_attr( $label );
		return '<a href="' . $link[1] . '" class="favr-trash' . $class . '"' . $click . ' aria-label="' . $aria . '" title="' . esc_attr( $label ) . '">' . Icons::svg( 'trash', 16 ) . '</a>';
	}

	/**
	 * Drop the Author, Comments, Yoast and Rank Math columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( $columns ) {
		foreach ( array_keys( (array) $columns ) as $key ) {
			if ( in_array( $key, self::DROP_COLUMNS, true ) || self::isSeoColumn( (string) $key ) ) {
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
	 * The date as "Oct 8, 2026" instead of "2026/10/08 at 11:02 pm". A scheduled item keeps its
	 * time ("Oct 20, 2026, 9:00 am"): when it goes out is the point. An item without a date keeps
	 * WordPress's text ("Unpublished").
	 *
	 * @param string $text        WordPress's text.
	 * @param string $post_date   The item's date in the site's time (post_date).
	 * @param string $post_status The item's status.
	 */
	public static function dateText( string $text, string $post_date, string $post_status = '' ): string {
		$stamp = '' === $post_date || str_starts_with( $post_date, '0000-00-00' ) ? false : strtotime( $post_date );
		if ( false === $stamp ) {
			return $text;
		}
		if ( 'future' === $post_status ) {
			/* translators: Date and time of a scheduled item in a list, e.g. "Oct 20, 2026, 9:00 am". See https://www.php.net/manual/datetime.format.php */
			return (string) date_i18n( __( 'M j, Y, g:i a', 'favr-sites' ), $stamp );
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

	/**
	 * Rows on a page. Editors have no Screen Options, so one number for everybody.
	 *
	 * @param mixed $chosen What WordPress would show.
	 */
	public static function perPage( $chosen = 0 ): int {
		return self::PER_PAGE;
	}

	/**
	 * The tabs above a list without "Mine" (nobody owns an item here), and without "Published"
	 * when it only repeats "All" or when the list is of things that are never public. The tab the
	 * Editor is on always stays.
	 *
	 * @param mixed  $views     Tab key => link.
	 * @param string $post_type Post type of the list.
	 * @return mixed
	 */
	public static function views( $views, string $post_type ) {
		if ( ! is_array( $views ) ) {
			return $views;
		}
		unset( $views['mine'] );
		if ( ! isset( $views['publish'] ) || str_contains( (string) $views['publish'], 'class="current"' ) ) {
			return $views;
		}
		$same = isset( $views['all'] ) && self::tabCount( (string) $views['all'] ) === self::tabCount( (string) $views['publish'] );
		if ( $same || in_array( $post_type, self::NEVER_PUBLIC, true ) ) {
			unset( $views['publish'] );
		}
		return $views;
	}

	/**
	 * The number in a tab: "All (1,315)" is 1315. No number is -1.
	 *
	 * @param string $link The tab's link.
	 */
	private static function tabCount( string $link ): int {
		if ( ! preg_match( '/\(([^()]*\d[^()]*)\)/', wp_strip_all_tags( $link ), $match ) ) {
			return -1;
		}
		return (int) preg_replace( '/\D/', '', $match[1] );
	}

	/**
	 * A picture's size fitted whole into the list thumbnail's box, never enlarged.
	 *
	 * @param int $width  Width of the picture.
	 * @param int $height Height of the picture.
	 * @return array{0: int, 1: int}
	 */
	public static function thumbSize( int $width, int $height ): array {
		if ( $width < 1 || $height < 1 ) {
			return array( self::THUMB, self::THUMB );
		}
		$ratio = min( 1, self::THUMB / $width, self::THUMB / $height );
		return array( max( 1, (int) round( $width * $ratio ) ), max( 1, (int) round( $height * $ratio ) ) );
	}

	/**
	 * How a list is ordered when the Editor chose no order: Members by name, the Directory by its
	 * "Sort as" value and then the name. Null for every other list and for a chosen order.
	 *
	 * @param string $post_type Post type of the list.
	 * @param string $orderby   The order asked for ('' for none).
	 * @return array<string, mixed>|null `orderby` and, with it, either `order` or a `meta` clause.
	 */
	public static function defaultOrder( string $post_type, string $orderby ): ?array {
		if ( '' !== $orderby || ! isset( self::A_TO_Z[ $post_type ] ) ) {
			return null;
		}
		$key = self::A_TO_Z[ $post_type ];
		if ( '' === $key ) {
			return array(
				'orderby' => 'title',
				'order'   => 'ASC',
			);
		}
		return array(
			'orderby' => array(
				'favr_sort' => 'ASC',
				'title'     => 'ASC',
			),
			// EXISTS or NOT EXISTS: a row without the value is still listed, by its title.
			'meta'    => array(
				'relation'  => 'OR',
				'favr_sort' => array(
					'key'     => $key,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				),
			),
		);
	}

	/**
	 * A query's meta conditions with one more that must also hold. What was there stays a group of
	 * its own, so an either-or somebody else set is not widened by the new clause.
	 *
	 * @param mixed        $existing The query's meta_query so far.
	 * @param array<mixed> $clause   The clause to add.
	 * @return array<int|string, mixed>
	 */
	public static function withClause( $existing, array $clause ): array {
		$existing = array_filter( (array) $existing );
		if ( ! $existing ) {
			return array( $clause );
		}
		return array(
			'relation' => 'AND',
			$existing,
			$clause,
		);
	}

	/**
	 * Where to go when the Trash view has just been emptied: the whole list, with the same
	 * "permanently deleted" message. Null when the Editor should stay.
	 *
	 * @param string $status    The view asked for (`post_status`).
	 * @param int    $deleted   How many items were just deleted for good.
	 * @param int    $left      How many are left in the Trash.
	 * @param string $post_type Post type of the list.
	 * @return array<string, int|string>|null Query arguments for edit.php.
	 */
	public static function leaveEmptyTrash( string $status, int $deleted, int $left, string $post_type ): ?array {
		if ( 'trash' !== $status || $deleted < 1 || $left > 0 ) {
			return null;
		}
		$args = 'post' === $post_type || '' === $post_type ? array() : array( 'post_type' => $post_type );
		return $args + array( 'deleted' => $deleted );
	}

	/**
	 * The sortable columns with the "sorted" arrow on the name instead of the date, for a list that
	 * opens A to Z.
	 *
	 * @param mixed $columns Column key => what WordPress sorts by.
	 * @return mixed
	 */
	public static function sortable( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		foreach ( $columns as $key => $how ) {
			if ( is_array( $how ) && isset( $how[4] ) ) {
				unset( $columns[ $key ][4] );
			}
		}
		$title            = isset( $columns['title'] ) ? array_values( (array) $columns['title'] ) : array( 'title' );
		$columns['title'] = array( $title[0], (bool) ( $title[1] ?? false ), $title[2] ?? '', $title[3] ?? '', 'asc' );
		return $columns;
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'current_screen', array( $this, 'screen' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'tile' ) );
		// Not on `current_screen`: a category added on the page comes back from admin-ajax.php.
		add_filter( 'tag_row_actions', array( $this, 'termRow' ), PHP_INT_MAX );
	}

	/**
	 * On list screens (posts of any type, categories and levels, the Media Library as a list), for
	 * Editors only.
	 *
	 * @param mixed $screen Screen.
	 */
	public function screen( $screen ): void {
		if ( ! is_object( $screen ) || ! isset( $screen->base ) || ! in_array( $screen->base, array( 'edit', 'upload', 'edit-tags' ), true ) || ! Audience::current() ) {
			return;
		}
		add_action( 'admin_head', array( $this, 'css' ) );
		if ( 'edit-tags' === $screen->base ) {
			add_filter( 'edit_' . $screen->taxonomy . '_per_page', array( self::class, 'perPage' ), PHP_INT_MAX );
			return;
		}
		if ( 'upload' === $screen->base ) {
			// The Media Library as a list: the same links, columns, dates and page length as every other list.
			add_filter( 'media_row_actions', array( self::class, 'mediaActions' ), PHP_INT_MAX );
			add_filter( 'manage_media_columns', array( self::class, 'columns' ), PHP_INT_MAX );
			add_filter( 'media_date_column_time', array( $this, 'time' ), 10, 2 );
			add_filter( 'upload_per_page', array( self::class, 'perPage' ), PHP_INT_MAX );
			add_filter( 'wp_get_attachment_image_src', array( $this, 'thumb' ), 10, 3 );
			return;
		}
		$this->leaveTrash( (string) $screen->post_type );
		add_filter( 'post_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		add_filter( 'page_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		// The screen-id filter runs after the post-type ones, so it also catches Yoast's columns.
		add_filter( 'manage_' . $screen->id . '_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_filter( 'views_' . $screen->id, array( $this, 'tabs' ), PHP_INT_MAX );
		add_filter( 'post_date_column_status', array( $this, 'status' ), 10, 2 );
		add_filter( 'post_date_column_time', array( $this, 'time' ), 10, 2 );
		add_filter( 'disable_months_dropdown', array( self::class, 'hidesMonths' ), 10, 2 );
		add_filter( 'edit_posts_per_page', array( self::class, 'perPage' ), PHP_INT_MAX );
		add_action( 'pre_get_posts', array( $this, 'order' ) );
	}

	/**
	 * After the last item in the Trash is deleted for good, WordPress leaves the Editor on an empty
	 * Trash view with no tab selected: go to the whole list instead, with the same message.
	 *
	 * @param string $post_type Post type of the list.
	 */
	private function leaveTrash( string $post_type ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reads which view is shown; changes nothing.
		$status  = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
		$deleted = isset( $_GET['deleted'] ) ? absint( wp_unslash( $_GET['deleted'] ) ) : 0;
		// phpcs:enable
		if ( 'trash' !== $status || $deleted < 1 || '' === $post_type || headers_sent() ) {
			return;
		}
		$args = self::leaveEmptyTrash( $status, $deleted, (int) ( wp_count_posts( $post_type )->trash ?? 0 ), $post_type );
		if ( null !== $args && wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) ) ) {
			exit;
		}
	}

	/**
	 * The links under a category, group or level, for Editors.
	 *
	 * @param mixed $actions Actions.
	 * @return mixed
	 */
	public function termRow( $actions ) {
		return Audience::current() ? self::termActions( $actions ) : $actions;
	}

	/**
	 * The tabs above the list.
	 *
	 * @param mixed $views Tabs.
	 * @return mixed
	 */
	public function tabs( $views ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return self::views( $views, $screen instanceof \WP_Screen ? (string) $screen->post_type : '' );
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
		return $post instanceof \WP_Post ? self::dateText( (string) $text, (string) $post->post_date, (string) $post->post_status ) : $text;
	}

	/**
	 * The list's own query: A to Z where the Editor chose no order.
	 *
	 * @param mixed $query The query.
	 */
	public function order( $query ): void {
		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		$change  = self::defaultOrder( (string) $query->get( 'post_type' ), is_string( $orderby ) ? $orderby : 'chosen' );
		if ( null === $change ) {
			return;
		}
		$query->set( 'orderby', $change['orderby'] );
		if ( isset( $change['order'] ) ) {
			$query->set( 'order', $change['order'] );
		}
		if ( isset( $change['meta'] ) ) {
			$query->set( 'meta_query', self::withClause( $query->get( 'meta_query' ), $change['meta'] ) );
		}
		add_filter( 'manage_edit-' . $query->get( 'post_type' ) . '_sortable_columns', array( self::class, 'sortable' ), PHP_INT_MAX );
	}

	/**
	 * Pure: a picture with no "medium" file of its own (a small logo) gets the file itself as its
	 * medium size, so the media grid and the media window show it whole instead of falling back
	 * to WordPress's cropped thumbnail.
	 *
	 * @param mixed $file What WordPress prepared for the media grid.
	 * @return mixed
	 */
	public static function wholeTile( $file ) {
		if ( ! is_array( $file ) || 'image' !== ( $file['type'] ?? '' ) || ! is_array( $file['sizes'] ?? null ) ) {
			return $file;
		}
		if ( empty( $file['sizes']['medium'] ) && ! empty( $file['sizes']['full'] ) ) {
			$file['sizes']['medium'] = $file['sizes']['full'];
		}
		return $file;
	}

	/**
	 * Whole pictures in the media grid and the media window, for Editors.
	 *
	 * @param mixed $file What WordPress prepared.
	 * @return mixed
	 */
	public function tile( $file ) {
		return Audience::current() ? self::wholeTile( $file ) : $file;
	}

	/**
	 * The list thumbnail of a picture: the whole picture, not WordPress's cropped square, so a
	 * wide logo is not cut off. The skin draws it inside its tile.
	 *
	 * @param mixed $image         What WordPress chose: address, width, height, resized.
	 * @param mixed $attachment_id The file.
	 * @param mixed $size          The size asked for.
	 * @return mixed
	 */
	public function thumb( $image, $attachment_id, $size ) {
		if ( ! is_array( $image ) || array( self::THUMB, self::THUMB ) !== $size || ! wp_attachment_is_image( (int) $attachment_id ) ) {
			return $image;
		}
		$whole = wp_get_attachment_image_src( (int) $attachment_id, 'medium' ); // Scaled, never cropped; the file itself when it is small.
		if ( ! is_array( $whole ) || empty( $whole[0] ) ) {
			return $image;
		}
		list( $width, $height ) = self::thumbSize( (int) ( $whole[1] ?? 0 ), (int) ( $whole[2] ?? 0 ) );
		return array( $whole[0], $width, $height, (bool) ( $whole[3] ?? false ) );
	}

	/**
	 * Small rules that belong to the markup printed here (the colours are the skin's tokens; the
	 * greys are for a screen without them): the bin, a locked page's title (Editors\PageLock adds
	 * the row class), and no "Detach" under a file's "Uploaded to".
	 */
	public function css(): void {
		$lock = '.wp-list-table tr.' . PageLock::ROW_CLASS . ' .column-primary strong>span:first-child';
		$css  = '.favr-trash{color:var(--favr-muted,#8c8f94)!important;display:inline-flex;vertical-align:middle}'
			. '.favr-trash:hover,.favr-trash:focus{color:var(--favr-danger,#b32d2e)!important}'
			. '.favr-trash svg{display:block}'
			. '@media (max-width:782px){.favr-trash{min-width:32px;min-height:32px;align-items:center;justify-content:center}}'
			. '.wp-list-table .detach-from-parent{display:none}'
			. $lock . '{color:var(--favr-muted,#646970);font-weight:400}'
			. $lock . '::before{content:"";display:inline-block;width:14px;height:14px;margin-right:6px;vertical-align:-2px;background:currentColor;-webkit-mask:' . self::LOCK . ' center/contain no-repeat;mask:' . self::LOCK . ' center/contain no-repeat}'
			. '.wp-list-table .favr-managed{cursor:help}';
		echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constants of this plugin, no input.
	}
}
