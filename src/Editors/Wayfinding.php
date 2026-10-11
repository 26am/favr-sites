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
 * For Editors: "← All events" above the title of a classic edit screen (post.php, post-new.php),
 * "← Media library" above Add media and "← All categories" above a term (term.php), always in the
 * same place. The block editor and Elementor have their own way back. No link when the user may
 * not open the list.
 *
 * The link goes back to the list as the Editor left it (search, view, page, order), and stays so
 * through saves: the place travels in a hidden field and in the address after a save. A record
 * opened from Approvals, or still waiting for approval, goes back to Approvals instead and says
 * who submitted it.
 */
final class Wayfinding {

	/** The hidden field, and the address argument after a save, that carry the way back. */
	public const FIELD = 'favr_back';

	/** The Approvals screen (favr/core's inbox), as a path inside wp-admin. */
	public const APPROVALS = 'admin.php?page=favr-approvals';

	/** Address arguments that say what just happened on a list, not which list it is. */
	private const PASSING = array( 'action', 'action2', '_wpnonce', '_wp_http_referer', 'filter_action', 'bulk_edit', 'post', 'ids', 'trashed', 'untrashed', 'deleted', 'locked', 'updated', 'skipped', 'message' );

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
	 * An address as a path inside this site's wp-admin, split into script and arguments. Nothing
	 * from another site, another folder or with a scheme of its own gets through.
	 *
	 * @param string $from      Full address, or a path inside wp-admin ("edit.php?s=x").
	 * @param string $admin_url This site's wp-admin address, with the closing slash.
	 * @return array{0: string, 1: array<string, mixed>}|null Script and arguments.
	 */
	private static function inside( string $from, string $admin_url ): ?array {
		if ( '' !== $admin_url && 0 === strpos( $from, $admin_url ) ) {
			$from = (string) substr( $from, strlen( $admin_url ) );
		}
		if ( ! preg_match( '/^([a-z-]+\.php)(?:\?([^#]*))?(?:#.*)?$/', $from, $parts ) ) {
			return null;
		}
		$args = array();
		parse_str( $parts[2] ?? '', $args );
		return array( $parts[1], $args );
	}

	/**
	 * The list an Editor came from, when it is this post type's own list: with its search, view,
	 * page and order, without what had just happened there.
	 *
	 * @param string $from      Where the Editor came from (full address or a path inside wp-admin).
	 * @param string $post_type The post type being edited.
	 * @param string $admin_url This site's wp-admin address, with the closing slash.
	 * @return string|null Path inside wp-admin, or null when it is not that list.
	 */
	public static function listReturn( string $from, string $post_type, string $admin_url ): ?string {
		$inside = self::inside( $from, $admin_url );
		if ( ! $inside || '' === $post_type ) {
			return null;
		}
		list( $script, $args ) = $inside;
		$media                 = 'attachment' === $post_type;
		if ( ( $media ? 'upload.php' : 'edit.php' ) !== $script || isset( $args['page'] ) ) {
			return null;
		}
		if ( ! $media && (string) ( $args['post_type'] ?? 'post' ) !== $post_type ) {
			return null;
		}
		$keep = array();
		foreach ( $args as $key => $value ) {
			if ( is_scalar( $value ) && '' !== (string) $value && ! in_array( (string) $key, self::PASSING, true ) ) {
				$keep[ (string) $key ] = (string) $value;
			}
		}
		return $keep ? $script . '?' . http_build_query( $keep, '', '&', PHP_QUERY_RFC3986 ) : $script;
	}

	/**
	 * Did the Editor come from the Approvals screen?
	 *
	 * @param string $from      Where the Editor came from.
	 * @param string $admin_url This site's wp-admin address, with the closing slash.
	 */
	public static function isApprovals( string $from, string $admin_url ): bool {
		$inside = self::inside( $from, $admin_url );
		return $inside && 'admin.php' === $inside[0] && 'favr-approvals' === ( $inside[1]['page'] ?? '' );
	}

	/**
	 * Where the Editor came from: the first of the candidates that is this type's list or the
	 * Approvals screen.
	 *
	 * @param array<int, mixed> $candidates Addresses, most trusted first (carried through a save, then the referer).
	 * @param string            $post_type  The post type being edited.
	 * @param string            $admin_url  This site's wp-admin address, with the closing slash.
	 * @return string|null Path inside wp-admin.
	 */
	public static function origin( array $candidates, string $post_type, string $admin_url ): ?string {
		foreach ( $candidates as $from ) {
			if ( ! is_string( $from ) || '' === $from ) {
				continue;
			}
			if ( self::isApprovals( $from, $admin_url ) ) {
				return self::APPROVALS;
			}
			$list = self::listReturn( $from, $post_type, $admin_url );
			if ( null !== $list ) {
				return $list;
			}
		}
		return null;
	}

	/**
	 * The back link of an edit screen, given where the Editor came from and the record's state.
	 *
	 * @param array{label: string, path: string} $back            The plain link to the list.
	 * @param string|null                        $origin          Where the Editor came from (see origin()).
	 * @param string                             $post_status     The record's status.
	 * @param bool                               $can_approve     The user may open Approvals.
	 * @param string                             $approvals_label "Approvals".
	 * @return array{label: string, path: string}
	 */
	public static function resolve( array $back, ?string $origin, string $post_status, bool $can_approve, string $approvals_label ): array {
		if ( $can_approve && ( self::APPROVALS === $origin || 'pending' === $post_status ) ) {
			return array(
				'label' => $approvals_label,
				'path'  => self::APPROVALS,
			);
		}
		if ( null !== $origin && self::APPROVALS !== $origin ) {
			$back['path'] = $origin;
		}
		return $back;
	}

	/**
	 * "Waiting for approval · submitted by Ben Carver. Back to Approvals"
	 *
	 * @param string $who   Who submitted the record ('' when unknown).
	 * @param string $url   Full address of Approvals ('' for no link).
	 * @param string $label Link text.
	 */
	public static function waitingHtml( string $who, string $url, string $label ): string {
		/* translators: %s: a person's name. */
		$text = '' !== $who ? sprintf( __( 'Waiting for approval · submitted by %s.', 'favr-sites' ), $who ) : __( 'Waiting for approval.', 'favr-sites' );
		$link = '' !== $url ? ' <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : '';
		return '<div class="notice notice-warning favr-waiting"><p>' . esc_html( $text ) . $link . '</p></div>';
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
		add_action( 'edit_form_top', array( $this, 'field' ) );
		add_filter( 'redirect_post_location', array( $this, 'carry' ), 10, 2 );
	}

	/** Print the link on edit, upload and term screens. */
	public function render(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof \WP_Screen || ! Audience::current() ) {
			return;
		}
		$back  = null;
		$after = '';
		if ( 'post' === $screen->base && ! $screen->is_block_editor() ) {
			$type = get_post_type_object( (string) $screen->post_type );
			$back = $type ? self::toList( $type ) : null;
			if ( $back ) {
				$post    = get_post();
				$status  = $post instanceof \WP_Post ? (string) $post->post_status : '';
				$approve = self::canApprove();
				$back    = self::resolve( $back, self::cameFrom( (string) $type->name ), $status, $approve, __( 'Approvals', 'favr-sites' ) );
				if ( 'pending' === $status && $post instanceof \WP_Post ) {
					$author = get_userdata( (int) $post->post_author );
					$after  = self::waitingHtml( $author ? (string) $author->display_name : '', $approve ? admin_url( self::APPROVALS ) : '', __( 'Back to Approvals', 'favr-sites' ) );
				}
			}
		} elseif ( 'media' === $screen->base ) {
			// Add media (media-new.php).
			$type = get_post_type_object( 'attachment' );
			$back = $type ? self::toList( $type ) : null;
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
			echo self::html( $back['label'], admin_url( $back['path'] ) ) . $after; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html() and waitingHtml().
		}
	}

	/**
	 * Keep the way back through a save: a hidden field in the edit form.
	 *
	 * @param mixed $post The post being edited.
	 */
	public function field( $post ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof \WP_Screen || ! Audience::current() || 'post' !== $screen->base || $screen->is_block_editor() || ! $post instanceof \WP_Post ) {
			return;
		}
		$origin = self::cameFrom( (string) $post->post_type );
		if ( null !== $origin ) {
			echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="' . esc_attr( $origin ) . '">';
		}
	}

	/**
	 * After a save WordPress reopens the edit screen: hand the way back on in the address.
	 *
	 * @param mixed $location Where WordPress is about to go.
	 * @param mixed $post_id  The saved post.
	 * @return mixed
	 */
	public function carry( $location, $post_id = 0 ) {
		if ( ! is_string( $location ) || ! Audience::current() ) {
			return $location;
		}
		$origin = self::cameFrom( (string) get_post_type( (int) $post_id ), false );
		return null !== $origin ? add_query_arg( self::FIELD, rawurlencode( $origin ), $location ) : $location;
	}

	/**
	 * The plain link to a post type's list.
	 *
	 * @param \WP_Post_Type $type Post type.
	 * @return array{label: string, path: string}|null
	 */
	private static function toList( $type ): ?array {
		return self::forPostType(
			(string) $type->name,
			(string) ( $type->labels->all_items ?? '' ),
			(string) ( $type->labels->name ?? '' ),
			current_user_can( 'attachment' === $type->name ? 'upload_files' : $type->cap->edit_posts )
		);
	}

	/**
	 * Where the Editor came from: what the form or the address carries, then the page before.
	 * Each is only ever read as "this type's list" or "Approvals" and rebuilt from its parts.
	 *
	 * @param string $post_type   The post type being edited.
	 * @param bool   $with_before Also look at the page the Editor was on before (not after a save: that is the edit screen itself).
	 */
	private static function cameFrom( string $post_type, bool $with_before = true ): ?string {
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a way back, not a change; origin() skips what is not text, then parses and rebuilds it.
		$candidates = array(
			isset( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : '',
			isset( $_GET[ self::FIELD ] ) ? wp_unslash( $_GET[ self::FIELD ] ) : '',
		);
		// phpcs:enable
		if ( $with_before ) {
			$candidates[] = wp_get_referer();
		}
		return self::origin( $candidates, $post_type, admin_url() );
	}

	/** May the current user open Approvals? favr/core only adds the screen for those who may review something. */
	private static function canApprove(): bool {
		$hook = function_exists( 'get_plugin_page_hookname' ) ? get_plugin_page_hookname( 'favr-approvals', '' ) : '';
		return '' !== $hook && ! empty( $GLOBALS['_registered_pages'][ $hook ] );
	}
}
