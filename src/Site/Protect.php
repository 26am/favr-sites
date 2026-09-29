<?php
/**
 * Editors can't break the site's foundations.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

use FavrSites\Dashboard\Audience;

/**
 * For Editors: Home, News, Header and Footer can't be deleted or taken off "published", and no
 * Elementor template's display conditions can change. Administrators are unaffected.
 */
final class Protect {

	private const DELETE_CAPS = array( 'delete_post', 'delete_page' );
	private const CONDITIONS  = '_elementor_conditions';

	/**
	 * The protected ids by role.
	 *
	 * @return array<string, int>
	 */
	public static function ids(): array {
		$recorded = (array) get_option( Foundations::OPTION, array() );
		return array_filter(
			array(
				'home'   => 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0,
				'news'   => (int) get_option( 'page_for_posts' ),
				'header' => (int) ( $recorded['header'] ?? 0 ),
				'footer' => (int) ( $recorded['footer'] ?? 0 ),
			)
		);
	}

	/**
	 * Which role an id plays.
	 *
	 * @param int                $post_id Post.
	 * @param array<string, int> $ids     Protected ids.
	 */
	public static function role( int $post_id, array $ids ): ?string {
		$role = $post_id ? array_search( $post_id, $ids, true ) : false;
		return is_string( $role ) ? $role : null;
	}

	/**
	 * Is this capability refused on this post?
	 *
	 * @param string             $cap     Meta cap.
	 * @param int                $post_id Post.
	 * @param array<string, int> $ids     Protected ids.
	 */
	public static function refusesCap( string $cap, int $post_id, array $ids ): bool {
		return in_array( $cap, self::DELETE_CAPS, true ) && null !== self::role( $post_id, $ids );
	}

	/**
	 * The status a save may set.
	 *
	 * @param string             $status  Requested status.
	 * @param int                $post_id Post.
	 * @param array<string, int> $ids     Protected ids.
	 */
	public static function status( string $status, int $post_id, array $ids ): string {
		return null !== self::role( $post_id, $ids ) ? 'publish' : $status;
	}

	/**
	 * Is this meta off-limits to Editors?
	 *
	 * @param string $key Meta key.
	 */
	public static function refusesMeta( string $key ): bool {
		return self::CONDITIONS === $key;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'map_meta_cap', array( $this, 'caps' ), 10, 4 );
		add_filter( 'wp_insert_post_data', array( $this, 'keepPublished' ), PHP_INT_MAX, 2 );
		foreach ( array( 'add', 'update', 'delete' ) as $op ) {
			add_filter( $op . '_post_metadata', array( $this, 'meta' ), 10, 3 );
		}
	}

	/**
	 * Refuse deleting the four.
	 *
	 * @param array<string> $caps    Required caps.
	 * @param string        $cap     Checked cap.
	 * @param int           $user_id User.
	 * @param array<mixed>  $args    [ post ].
	 * @return array<string>
	 */
	public function caps( $caps, $cap, $user_id, $args ) {
		if ( empty( $args[0] ) || ! in_array( $cap, self::DELETE_CAPS, true ) ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post || ! self::refusesCap( (string) $cap, (int) $post->ID, self::ids() ) ) {
			return $caps;
		}
		$user = get_userdata( (int) $user_id );
		return $user && Audience::includes( $user ) ? array( 'do_not_allow' ) : $caps;
	}

	/**
	 * An Editor's save keeps the four published.
	 *
	 * @param array<string, mixed> $data    Post data.
	 * @param array<string, mixed> $postarr Raw input.
	 * @return array<string, mixed>
	 */
	public function keepPublished( $data, $postarr ) {
		if ( ! empty( $postarr['ID'] ) && isset( $data['post_status'] ) && Audience::current() ) {
			$data['post_status'] = self::status( (string) $data['post_status'], (int) $postarr['ID'], self::ids() );
		}
		return $data;
	}

	/**
	 * Editors can't write display conditions (returning false short-circuits the write).
	 *
	 * @param mixed  $check     Null to continue.
	 * @param int    $object_id Post.
	 * @param string $meta_key  Key.
	 * @return mixed
	 */
	public function meta( $check, $object_id, $meta_key ) {
		return self::refusesMeta( (string) $meta_key ) && Audience::current() ? false : $check;
	}
}
