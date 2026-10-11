<?php
/**
 * Recent activity across the site's content.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * The most recently changed items the user may edit. Uses WordPress's own "last edited by"
 * record; nothing new is stored.
 */
final class Activity {

	private const TYPES = array( 'page', 'post', 'favr_business', 'favr_event', 'favr_member', 'favr_document', 'favr_person', 'favr_sponsor' );

	/**
	 * Keep types the user may edit.
	 *
	 * @param array<mixed> $candidates Post types.
	 * @param callable     $editable   callable( string $type ): bool.
	 * @return list<string>
	 */
	public static function types( array $candidates, callable $editable ): array {
		return array_values( array_filter( $candidates, static fn( $type ): bool => is_string( $type ) && $editable( $type ) ) );
	}

	/**
	 * "added" when the item hasn't been edited since it was created (within a minute), else "updated".
	 *
	 * @param string $created_gmt  post_date_gmt.
	 * @param string $modified_gmt post_modified_gmt.
	 */
	public static function verb( string $created_gmt, string $modified_gmt ): string {
		if ( str_starts_with( $created_gmt, '0000' ) ) {
			return 'added'; // A draft that has never been published has no creation date yet.
		}
		return abs( strtotime( $modified_gmt . ' UTC' ) - strtotime( $created_gmt . ' UTC' ) ) <= MINUTE_IN_SECONDS ? 'added' : 'updated';
	}

	/**
	 * When an item last changed, as a Unix time: the first real date among the candidates, else
	 * now. WordPress leaves the GMT dates of a draft at 0000-00-00 until it is saved a second time,
	 * and "2028 years ago" is not when anything happened. Nor is a date later than now: that is
	 * when a scheduled item goes out, not when somebody touched it.
	 *
	 * @param array<mixed> $gmt_dates Dates in GMT ("Y-m-d H:i:s"), best first.
	 * @param int          $now       The current Unix time.
	 */
	public static function changed( array $gmt_dates, int $now ): int {
		foreach ( $gmt_dates as $date ) {
			if ( ! is_string( $date ) || '' === $date || str_starts_with( $date, '0000' ) ) {
				continue;
			}
			$stamp = strtotime( $date . ' UTC' );
			if ( false !== $stamp && $stamp > 0 && $stamp <= $now ) {
				return $stamp;
			}
		}
		return $now;
	}

	/**
	 * The time beside a row: "2 hours ago", or "scheduled for Jan 5" for an item waiting to go out.
	 *
	 * @param string                        $status     The item's status.
	 * @param int                           $publish_at When it is published, as a Unix time.
	 * @param int                           $changed    When it last changed, as a Unix time.
	 * @param int                           $now        The current Unix time.
	 * @param callable(int, int): string    $diff       Words for a time difference ("2 hours").
	 * @param callable(string, int): string $date       Formats a Unix time in the site's time zone.
	 */
	public static function when( string $status, int $publish_at, int $changed, int $now, callable $diff, callable $date ): string {
		if ( 'future' === $status && $publish_at > $now ) {
			$far = $publish_at - $now >= 365 * 24 * 60 * MINUTE_IN_SECONDS;
			/* translators: %s: a date, e.g. "Jan 5". */
			return sprintf( __( 'scheduled for %s', 'favr-sites' ), $date( $far ? 'M j, Y' : 'M j', $publish_at ) );
		}
		/* translators: %s: human time difference, e.g. "2 hours". */
		return sprintf( __( '%s ago', 'favr-sites' ), $diff( $changed, $now ) );
	}

	/**
	 * The query for the list: the latest changes that have happened. An item inserted already
	 * scheduled (an import, WP-CLI) carries its publish date as its modified date; left in, it sat
	 * on top of the list until that day. One scheduled in the editor has a real modified time and
	 * stays, in its place.
	 *
	 * @param list<string> $types Post types.
	 * @param int          $limit Max rows.
	 * @param int          $now   The current Unix time.
	 * @return array<string, mixed>
	 */
	public static function query( array $types, int $limit, int $now ): array {
		return array(
			'post_type'              => $types,
			'post_status'            => array( 'publish', 'draft', 'pending', 'future' ),
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'date_query'             => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => gmdate( 'Y-m-d H:i:s', $now + MINUTE_IN_SECONDS ),
				),
			),
		);
	}

	/**
	 * Plain-text title with a fallback.
	 *
	 * @param string $raw Title.
	 */
	public static function title( string $raw ): string {
		$title = trim( wp_strip_all_tags( $raw ) );
		return '' !== $title ? $title : __( '(no title)', 'favr-sites' );
	}

	/**
	 * Recent changes. `who` is '' when the change has no known author (e.g. imported content).
	 *
	 * @param int $limit Max rows.
	 * @return list<array{who: string, verb: string, title: string, url: string, ago: string}>
	 */
	public static function recent( int $limit = 10 ): array {
		$types = self::types(
			(array) apply_filters( 'favr_sites_activity_post_types', self::TYPES ),
			static function ( string $type ): bool {
				$object = get_post_type_object( $type );
				return $object && current_user_can( $object->cap->edit_posts );
			}
		);
		if ( ! $types ) {
			return array();
		}
		$now   = time();
		$query = new \WP_Query( self::query( $types, $limit, $now ) );
		$out   = array();
		foreach ( $query->posts as $post ) {
			$user_id = (int) get_post_meta( $post->ID, '_edit_last', true ) ?: (int) $post->post_author;
			$user    = $user_id ? get_userdata( $user_id ) : false;
			$out[]   = array(
				'who'   => $user ? $user->display_name : '',
				'verb'  => self::verb( $post->post_date_gmt, $post->post_modified_gmt ),
				'title' => self::title( $post->post_title ),
				'url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
				'ago'   => self::when(
					(string) $post->post_status,
					(int) strtotime( $post->post_date_gmt . ' UTC' ),
					self::changed( self::gmtDates( $post ), $now ),
					$now,
					'human_time_diff',
					static fn( string $format, int $stamp ): string => (string) wp_date( $format, $stamp )
				),
			);
		}
		return $out;
	}

	/**
	 * The dates an item can be said to have changed at, in GMT, best first: last modified, then
	 * created, each as stored in GMT and as worked out from the site's own time.
	 *
	 * @param \WP_Post $post The item.
	 * @return list<string>
	 */
	private static function gmtDates( \WP_Post $post ): array {
		$dates = array();
		foreach ( array( 'post_modified', 'post_date' ) as $field ) {
			$dates[] = (string) $post->{$field . '_gmt'};
			$local   = (string) $post->$field;
			$dates[] = '' === $local || str_starts_with( $local, '0000' ) ? '' : (string) get_gmt_from_date( $local );
		}
		return $dates;
	}
}
