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

	private const TYPES = array( 'page', 'post', 'favr_business', 'favr_event', 'favr_member' );

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
		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future' ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'posts_per_page'         => $limit,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
			)
		);
		$out   = array();
		foreach ( $query->posts as $post ) {
			$user_id = (int) get_post_meta( $post->ID, '_edit_last', true ) ?: (int) $post->post_author;
			$user    = $user_id ? get_userdata( $user_id ) : false;
			$out[]   = array(
				'who'   => $user ? $user->display_name : '',
				'verb'  => self::verb( $post->post_date_gmt, $post->post_modified_gmt ),
				'title' => self::title( $post->post_title ),
				'url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
				/* translators: %s: human time difference, e.g. "2 hours". */
				'ago'   => sprintf( __( '%s ago', 'favr-sites' ), human_time_diff( (int) strtotime( $post->post_modified_gmt . ' UTC' ) ) ),
			);
		}
		return $out;
	}
}
