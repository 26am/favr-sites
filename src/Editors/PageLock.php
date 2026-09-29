<?php
/**
 * Pages not built with Elementor are locked for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * Editors get one editor per type: pages in Elementor, News in the block editor. Anything that doesn't
 * fit is locked ("Managed by Favr"): pages not built with Elementor (plugin pages holding a shortcode
 * that makes them work) and News posts built with Elementor (block-editor edits wouldn't reach
 * visitors). Editors can't edit, trash or publish them.
 */
final class PageLock {

	// Core checks edit_post/delete_post; Elementor checks the post type's own meta caps (edit_page…).
	private const CAPS = array( 'edit_post', 'edit_page', 'delete_post', 'delete_page', 'publish_post' );

	/**
	 * Locked for Editors?
	 *
	 * @param string $post_type            Post type.
	 * @param string $status               Post status.
	 * @param bool   $built_with_elementor _elementor_edit_mode = builder.
	 * @param bool   $elementor_active     Elementor loaded.
	 * @param bool   $posts_page           The News (posts) page, whose own content WordPress doesn't show.
	 */
	public static function isLocked( string $post_type, string $status, bool $built_with_elementor, bool $elementor_active, bool $posts_page = false ): bool {
		if ( ! $elementor_active || 'auto-draft' === $status ) {
			return false;
		}
		if ( $posts_page && 'page' === $post_type ) {
			return true;
		}
		if ( 'page' === $post_type ) {
			return ! $built_with_elementor;
		}
		return 'post' === $post_type && $built_with_elementor;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'map_meta_cap', array( $this, 'caps' ), 10, 4 );
		add_filter( 'display_post_states', array( $this, 'states' ), 10, 2 );
	}

	/**
	 * Deny edit/delete/publish of a locked page to Editors.
	 *
	 * @param array<string> $caps    Required caps.
	 * @param string        $cap     Checked cap.
	 * @param int           $user_id User.
	 * @param array<mixed>  $args    [ post_id ].
	 * @return array<string>
	 */
	public function caps( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, self::CAPS, true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( $args[0] ); // An id or a WP_Post.
		if ( ! $post || ! self::lockedPost( $post ) ) {
			return $caps;
		}
		$user = get_userdata( (int) $user_id );
		return $user && Audience::includes( $user ) ? array( 'do_not_allow' ) : $caps;
	}

	/**
	 * "Managed by Favr" in the Pages list, for Editors.
	 *
	 * @param array<string, string> $states States.
	 * @param \WP_Post              $post   Post.
	 * @return array<string, string>
	 */
	public function states( $states, $post ) {
		if ( $post instanceof \WP_Post && self::lockedPost( $post ) && Audience::current() ) {
			$states['favr_managed'] = __( 'Managed by Favr', 'favr-sites' );
		}
		return $states;
	}

	/**
	 * Is this post locked for Editors?
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function lockedPost( \WP_Post $post ): bool {
		if ( 'page' !== $post->post_type && 'post' !== $post->post_type ) {
			return false; // Skip the meta read for attachments, listings, events and so on.
		}
		$mode = (string) get_post_meta( $post->ID, '_elementor_edit_mode', true );
		return self::isLocked( $post->post_type, $post->post_status, 'builder' === $mode, (bool) did_action( 'elementor/loaded' ), 'page' === $post->post_type && (int) get_option( 'page_for_posts' ) === (int) $post->ID );
	}
}
