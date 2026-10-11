<?php
/**
 * Less WordPress around an Editor's screens.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * For Editors: no Screen Options, no Help tabs, and no Author, Slug, Attributes, Custom Fields,
 * Discussion, Comments, Trackbacks or Revisions box on a classic edit screen.
 *
 * Without Screen Options an Editor has no way to bring back a box or a column they once hid, or to
 * return to two columns, so those saved choices stop applying as well: every Editor sees the same
 * screen. The permalink under the title is still edited there: WordPress keeps the field it saves
 * inside the Slug box, so the field is printed on its own once the box is gone.
 */
final class Chrome {

	/** Boxes Editors lose. Not `postexcerpt`: Favr Events uses it for an event's short summary. */
	public const BOXES = array( 'authordiv', 'slugdiv', 'pageparentdiv', 'postcustom', 'commentstatusdiv', 'commentsdiv', 'trackbacksdiv', 'revisionsdiv' );

	/** Where a box can sit (WordPress only removes it from the place it is told). */
	private const CONTEXTS = array( 'normal', 'advanced', 'side' );

	/**
	 * Every box in every place.
	 *
	 * @return list<array{0: string, 1: string}> Pairs of box id and context.
	 */
	public static function removals(): array {
		$out = array();
		foreach ( self::BOXES as $box ) {
			foreach ( self::CONTEXTS as $context ) {
				$out[] = array( $box, $context );
			}
		}
		return $out;
	}

	/**
	 * Screen Options show for everybody but Editors.
	 *
	 * @param mixed $show      What WordPress decided.
	 * @param bool  $is_editor In the Favr audience.
	 */
	public static function showsScreenOptions( $show, bool $is_editor ): bool {
		return $is_editor ? false : (bool) $show;
	}

	/**
	 * The boxes or columns a user has hidden: none for Editors.
	 *
	 * @param mixed $hidden    Hidden ids.
	 * @param bool  $is_editor In the Favr audience.
	 * @return list<string>
	 */
	public static function hidden( $hidden, bool $is_editor ): array {
		return $is_editor || ! is_array( $hidden ) ? array() : array_values( $hidden );
	}

	/**
	 * May this item's slug be changed from its edit screen? WordPress's own rule for the Slug box:
	 * not while the item waits for review and its author cannot publish.
	 *
	 * @param string $post_status The item's status.
	 * @param bool   $can_publish The user may publish this post type.
	 */
	public static function keepsSlugField( string $post_status, bool $can_publish ): bool {
		return 'pending' !== $post_status || $can_publish;
	}

	/**
	 * Is a box registered on a screen (and not removed)?
	 *
	 * @param mixed  $boxes The screen's part of $GLOBALS['wp_meta_boxes']: context => priority => id => box.
	 * @param string $id    Box id.
	 */
	public static function hasBox( $boxes, string $id ): bool {
		foreach ( is_array( $boxes ) ? $boxes : array() as $priorities ) {
			foreach ( is_array( $priorities ) ? $priorities : array() as $registered ) {
				if ( is_array( $registered ) && ! empty( $registered[ $id ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'screen_options_show_screen', array( $this, 'screenOptions' ), PHP_INT_MAX );
		add_action( 'current_screen', array( $this, 'screen' ) );
		add_action( 'in_admin_header', array( $this, 'help' ), PHP_INT_MAX );
		add_action( 'add_meta_boxes', array( $this, 'boxes' ), PHP_INT_MAX );
		add_action( 'edit_form_top', array( $this, 'slugField' ) );
		add_filter( 'hidden_meta_boxes', array( $this, 'hiddenBoxes' ), PHP_INT_MAX, 2 );
		add_filter( 'hidden_columns', array( $this, 'hiddenColumns' ), PHP_INT_MAX );
	}

	/**
	 * No Screen Options tab.
	 *
	 * @param mixed $show Show.
	 */
	public function screenOptions( $show ): bool {
		return self::showsScreenOptions( $show, Audience::current() );
	}

	/**
	 * Screen Options also holds "1 column / 2 columns"; an Editor always gets the screen's default.
	 *
	 * @param \WP_Screen $screen Screen.
	 */
	public function screen( $screen ): void {
		if ( $screen instanceof \WP_Screen && Audience::current() ) {
			add_filter( 'get_user_option_screen_layout_' . $screen->id, '__return_false', PHP_INT_MAX );
		}
	}

	/** No Help tab. Runs just before WordPress draws it, after every screen has added its tabs. */
	public function help(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen instanceof \WP_Screen && Audience::current() ) {
			$screen->remove_help_tabs();
		}
	}

	/** Remove the boxes from a classic edit screen (the block editor draws its own panels). */
	public function boxes(): void {
		$screen = self::classicEditScreen();
		if ( ! $screen ) {
			return;
		}
		foreach ( self::removals() as list( $box, $context ) ) {
			remove_meta_box( $box, $screen, $context );
		}
	}

	/**
	 * The slug field the permalink editor under the title saves through, when no Slug box holds it.
	 *
	 * @param \WP_Post $post The post being edited.
	 */
	public function slugField( $post ): void {
		global $wp_meta_boxes;
		$screen = self::classicEditScreen();
		$type   = $post instanceof \WP_Post ? get_post_type_object( $post->post_type ) : null;
		if ( ! $screen || ! $type || self::hasBox( $wp_meta_boxes[ $screen->id ] ?? null, 'slugdiv' ) ) {
			return;
		}
		if ( ! self::keepsSlugField( (string) get_post_status( $post ), current_user_can( $type->cap->publish_posts ) ) ) {
			return;
		}
		/** This filter is documented in wp-admin/edit-tag-form.php */
		$slug = apply_filters( 'editable_slug', $post->post_name, $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		echo '<input type="hidden" name="post_name" id="post_name" value="' . esc_attr( (string) $slug ) . '">';
	}

	/**
	 * Nothing stays hidden on a classic edit screen.
	 *
	 * @param mixed $hidden Hidden box ids.
	 * @param mixed $screen Screen.
	 * @return mixed
	 */
	public function hiddenBoxes( $hidden, $screen = null ) {
		if ( $screen instanceof \WP_Screen && $screen->is_block_editor() ) {
			return $hidden;
		}
		return Audience::current() ? self::hidden( $hidden, true ) : $hidden;
	}

	/**
	 * No list column stays hidden.
	 *
	 * @param mixed $hidden Hidden column ids.
	 * @return mixed
	 */
	public function hiddenColumns( $hidden ) {
		return Audience::current() ? self::hidden( $hidden, true ) : $hidden;
	}

	/** The current screen when it is a classic edit screen and the user is an Editor. */
	private static function classicEditScreen(): ?\WP_Screen {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen instanceof \WP_Screen && 'post' === $screen->base && ! $screen->is_block_editor() && Audience::current() ? $screen : null;
	}
}
