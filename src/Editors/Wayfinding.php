<?php
/**
 * One click back to the list from every edit screen.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * For Editors: "← All events" above the title of a classic edit screen (post.php, post-new.php)
 * and "← All categories" above a term (term.php), always in the same place. The block editor and
 * Elementor have their own way back. No link when the user may not open the list.
 */
final class Wayfinding {

	/**
	 * Where an edit screen goes back to.
	 *
	 * @param string $post_type Post type.
	 * @param string $all_items The post type's "All events" label.
	 * @param string $name      The post type's plural name, used when it has no such label.
	 * @param bool   $can       The user may open the list.
	 * @return array{label: string, path: string}|null Label and path inside wp-admin, or null for no link.
	 */
	public static function forPostType( string $post_type, string $all_items, string $name, bool $can ): ?array {
		$label = self::label( $all_items, $name );
		if ( ! $can || '' === $post_type || '' === $label ) {
			return null;
		}
		$paths = array(
			'post'       => 'edit.php',
			'attachment' => 'upload.php',
		);
		return array(
			'label' => $label,
			'path'  => $paths[ $post_type ] ?? 'edit.php?post_type=' . rawurlencode( $post_type ),
		);
	}

	/**
	 * Where a term screen goes back to.
	 *
	 * @param string $taxonomy  Taxonomy.
	 * @param string $post_type Post type the screen belongs to ('' or 'post' for News).
	 * @param string $all_items The taxonomy's "All categories" label.
	 * @param string $name      The taxonomy's plural name, used when it has no such label.
	 * @param bool   $can       The user may open the list of terms.
	 * @return array{label: string, path: string}|null Label and path inside wp-admin, or null for no link.
	 */
	public static function forTaxonomy( string $taxonomy, string $post_type, string $all_items, string $name, bool $can ): ?array {
		$label = self::label( $all_items, $name );
		if ( ! $can || '' === $taxonomy || '' === $label ) {
			return null;
		}
		$path = 'edit-tags.php?taxonomy=' . rawurlencode( $taxonomy );
		if ( '' !== $post_type && 'post' !== $post_type ) {
			$path .= '&post_type=' . rawurlencode( $post_type );
		}
		return array(
			'label' => $label,
			'path'  => $path,
		);
	}

	/**
	 * The link.
	 *
	 * @param string $label Link text.
	 * @param string $url   Full address of the list.
	 */
	public static function html( string $label, string $url ): string {
		return '<p class="favr-back"><a href="' . esc_url( $url ) . '"><span aria-hidden="true">←</span> ' . esc_html( $label ) . '</a></p>';
	}

	/**
	 * "All events", or the plain name when there is no such label.
	 *
	 * @param string $all_items Preferred.
	 * @param string $name      Fallback.
	 */
	private static function label( string $all_items, string $name ): string {
		return '' !== trim( $all_items ) ? trim( $all_items ) : trim( $name );
	}

	/** Hooks. */
	public function hook(): void {
		// Last of the notices: the link lands right above the screen's own content (`.wrap`).
		add_action( 'all_admin_notices', array( $this, 'render' ), PHP_INT_MAX );
	}

	/** Print the link on edit and term screens. */
	public function render(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof \WP_Screen || ! Audience::current() ) {
			return;
		}
		$back = null;
		if ( 'post' === $screen->base && ! $screen->is_block_editor() ) {
			$type = get_post_type_object( (string) $screen->post_type );
			if ( $type ) {
				$back = self::forPostType(
					(string) $type->name,
					(string) ( $type->labels->all_items ?? '' ),
					(string) ( $type->labels->name ?? '' ),
					current_user_can( 'attachment' === $type->name ? 'upload_files' : $type->cap->edit_posts )
				);
			}
		} elseif ( 'term' === $screen->base ) {
			$taxonomy = get_taxonomy( (string) $screen->taxonomy );
			if ( $taxonomy ) {
				$back = self::forTaxonomy(
					(string) $taxonomy->name,
					(string) $screen->post_type,
					(string) ( $taxonomy->labels->all_items ?? '' ),
					(string) ( $taxonomy->labels->name ?? '' ),
					current_user_can( $taxonomy->cap->manage_terms )
				);
			}
		}
		if ( $back ) {
			echo self::html( $back['label'], admin_url( $back['path'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
		}
	}
}
