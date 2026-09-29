<?php
/**
 * Pages open in Elementor; posts never do. Editors only, and only when Elementor runs.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * Routes every way into a page editor to Elementor, and takes Elementor off posts.
 */
final class Routing {

	/** Hooks. */
	public function hook(): void {
		add_filter( 'get_edit_post_link', array( $this, 'editLink' ), 10, 3 );
		add_action( 'load-post.php', array( $this, 'redirectEdit' ) );
		add_action( 'load-post-new.php', array( $this, 'redirectNew' ) );
		add_filter( 'elementor/document/urls/exit_to_dashboard', array( $this, 'exitUrl' ), 10, 2 );
		add_action( 'init', array( $this, 'postsWithoutElementor' ), 20 );
	}

	/**
	 * Elementor editor URL for a post.
	 *
	 * @param int $post_id Post.
	 */
	public static function elementorUrl( int $post_id ): string {
		return add_query_arg(
			array(
				'post'   => $post_id,
				'action' => 'elementor',
			),
			admin_url( 'post.php' )
		);
	}

	/** Should page routing apply for this request? */
	private static function active(): bool {
		return did_action( 'elementor/loaded' ) && Audience::current();
	}

	/**
	 * Page edit links point at Elementor.
	 *
	 * @param string $link    Link.
	 * @param int    $post_id Post.
	 * @param string $context 'display' escapes ampersands.
	 * @return string
	 */
	public function editLink( $link, $post_id, $context ) {
		if ( ! $link || ! self::active() || 'page' !== get_post_type( (int) $post_id ) ) {
			return $link;
		}
		$url = self::elementorUrl( (int) $post_id );
		return 'display' === $context ? esc_url( $url ) : $url;
	}

	/** Post.php?action=edit on a page → Elementor (only when the Editor may edit it). */
	public function redirectEdit(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' !== $action || ! $post_id || ! self::active() || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		wp_safe_redirect( self::elementorUrl( $post_id ) );
		exit;
	}

	/** Post-new.php?post_type=page → a new Elementor page. */
	public function redirectNew(): void {
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'page' !== $type || ! self::active() || ! class_exists( '\Elementor\Core\Documents_Manager' ) ) {
			return;
		}
		wp_safe_redirect( \Elementor\Core\Documents_Manager::get_create_new_post_url( 'page' ) );
		exit;
	}

	/**
	 * Elementor's "Exit" returns to the Pages list.
	 *
	 * @param string $url      URL.
	 * @param mixed  $document Elementor document.
	 * @return string
	 */
	public function exitUrl( $url, $document ) {
		if ( ! Audience::current() || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || 'page' !== get_post_type( (int) $document->get_main_id() ) ) {
			return $url;
		}
		return admin_url( 'edit.php?post_type=page' );
	}

	/** Elementor offers nothing on posts for Editors (no row action, switch button or editor). */
	public function postsWithoutElementor(): void {
		if ( self::active() ) {
			remove_post_type_support( 'post', 'elementor' );
		}
	}
}
