<?php
/**
 * Less WordPress around an Editor's screens.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Skin\Skin;

/**
 * For Editors: no Screen Options, no Help tabs, and no Author, Slug, Attributes, Custom Fields,
 * Discussion, Comments, Trackbacks or Revisions box on a classic edit screen.
 *
 * Without Screen Options an Editor has no way to bring back a box or a column they once hid, or to
 * return to two columns, so those saved choices stop applying as well: every Editor sees the same
 * screen. The permalink under the title is still edited there: WordPress keeps the field it saves
 * inside the Slug box, so the field is printed on its own once the box is gone.
 *
 * On a classic edit screen an Editor also gets assets/skin/edit-screen.js (a question before
 * leaving with unsaved changes, box titles that neither fold nor drag, a dot on each filled tab),
 * boxes that are never folded or reordered, a description editor in the Favr typeface without the
 * Code tab, and no Visibility row where it says nothing true.
 */
final class Chrome {

	/** The edit-screen script. */
	public const SCRIPT      = 'favr-sites-edit-screen';
	public const SCRIPT_FILE = 'assets/skin/edit-screen.js';

	/** Record types whose own switch decides who may see them, so "Visibility: Public" would mislead. */
	public const NO_VISIBILITY = array( 'favr_document', 'favr_member' );

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

	/**
	 * Does a screen get the edit-screen script? Classic post.php and post-new.php, for Editors.
	 *
	 * @param string $base         The screen's base.
	 * @param bool   $block_editor The screen is the block editor.
	 * @param string $action       The `action` in the address ('elementor' is Elementor's editor).
	 * @param bool   $is_editor    In the Favr audience.
	 */
	public static function loadsEditScript( string $base, bool $block_editor, string $action, bool $is_editor ): bool {
		return $is_editor && 'post' === $base && ! $block_editor && 'elementor' !== $action;
	}

	/**
	 * Which boxes a user folded, or the order they dragged them into: neither for Editors, who have
	 * no handle to undo it with.
	 *
	 * @param mixed $saved     What the user's account holds.
	 * @param bool  $is_editor In the Favr audience.
	 * @return mixed False (WordPress's "nothing saved") for Editors.
	 */
	public static function savedBoxState( $saved, bool $is_editor ) {
		return $is_editor ? false : $saved;
	}

	/**
	 * Is this one of the Favr record types (listing, event, member, document, person, sponsor)?
	 *
	 * @param string $post_type Post type.
	 */
	public static function isRecordType( string $post_type ): bool {
		return 0 === strpos( $post_type, 'favr_' );
	}

	/**
	 * A text editor without the Code tab and the HTML tag buttons, on records and on media (where
	 * that leaves a plain text box). News and pages keep theirs.
	 *
	 * @param array<string, mixed> $settings  wp_editor() settings.
	 * @param string               $post_type The screen's post type.
	 * @return array<string, mixed>
	 */
	public static function plainEditor( array $settings, string $post_type ): array {
		if ( self::isRecordType( $post_type ) || 'attachment' === $post_type ) {
			$settings['quicktags'] = false;
		}
		return $settings;
	}

	/**
	 * The editor's toolbar without "Insert Read More tag": a record has no "read more".
	 *
	 * @param array<int, mixed> $buttons   Toolbar buttons.
	 * @param string            $post_type The screen's post type.
	 * @return list<mixed>
	 */
	public static function withoutReadMore( array $buttons, string $post_type ): array {
		if ( ! self::isRecordType( $post_type ) ) {
			return array_values( $buttons );
		}
		return array_values( array_diff( $buttons, array( 'wp_more' ) ) );
	}

	/**
	 * Favr type for the text inside the editor (its own frame, which the skin's sheets do not
	 * reach, so the token values are repeated here). WordPress prints the value between double
	 * quotes without escaping it: no double quote, backslash or line break may be in it.
	 *
	 * @param string $font_url Address of the Favr Sans font file.
	 */
	public static function contentStyle( string $font_url ): string {
		$font_url = str_replace( array( '"', "'", '\\', "\r", "\n", ')' ), '', $font_url );
		return "@font-face{font-family:'Favr Sans';src:url('" . $font_url . "') format('woff2');font-weight:400 700;font-display:swap}"
			. "body#tinymce{font-family:'Favr Sans',-apple-system,blinkmacsystemfont,'Segoe UI',roboto,sans-serif;font-size:14.5px;line-height:1.55;color:#2e2230}"
			. 'body#tinymce a{color:#a9472a}';
	}

	/**
	 * Add a style to the editor's settings, after any that is there.
	 *
	 * @param array<string, mixed> $init TinyMCE settings.
	 * @param string               $css  Style.
	 * @return array<string, mixed>
	 */
	public static function withContentStyle( array $init, string $css ): array {
		$before                = isset( $init['content_style'] ) && is_string( $init['content_style'] ) ? trim( $init['content_style'] ) : '';
		$init['content_style'] = '' === $before ? $css : $before . ' ' . $css;
		return $init;
	}

	/**
	 * Is the Publish box's Visibility row hidden for this post type?
	 *
	 * @param string $post_type Post type.
	 * @param mixed  $types     Post types without the row (the filtered list).
	 */
	public static function hidesVisibility( string $post_type, $types ): bool {
		return is_array( $types ) && in_array( $post_type, $types, true );
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
		// After the skin (20), whose sheet the Visibility rule is attached to.
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 21 );
		add_filter( 'wp_editor_settings', array( $this, 'editorSettings' ), PHP_INT_MAX, 2 );
		add_filter( 'mce_buttons', array( $this, 'editorButtons' ), PHP_INT_MAX, 2 );
		add_filter( 'tiny_mce_before_init', array( $this, 'editorLook' ), PHP_INT_MAX, 2 );
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
	 * The same for boxes once folded shut or dragged elsewhere: their titles no longer do either.
	 *
	 * @param \WP_Screen $screen Screen.
	 */
	public function screen( $screen ): void {
		if ( $screen instanceof \WP_Screen && Audience::current() ) {
			add_filter( 'get_user_option_screen_layout_' . $screen->id, '__return_false', PHP_INT_MAX );
			add_filter( 'get_user_option_closedpostboxes_' . $screen->id, array( $this, 'boxState' ), PHP_INT_MAX );
			add_filter( 'get_user_option_meta-box-order_' . $screen->id, array( $this, 'boxState' ), PHP_INT_MAX );
		}
	}

	/**
	 * No box stays folded or out of place.
	 *
	 * @param mixed $saved Saved state.
	 * @return mixed
	 */
	public function boxState( $saved ) {
		return self::savedBoxState( $saved, Audience::current() );
	}

	/** The edit-screen script, and no Visibility row where it would mislead. */
	public function assets(): void {
		$screen = self::classicEditScreen();
		if ( ! $screen ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only read to tell Elementor's editor apart.
		if ( ! self::loadsEditScript( (string) $screen->base, false, $action, true ) ) {
			return;
		}
		wp_enqueue_script( self::SCRIPT, FAVR_SITES_URL . self::SCRIPT_FILE, array( 'jquery', 'postbox' ), FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . self::SCRIPT_FILE ), true );
		wp_localize_script( self::SCRIPT, 'favrEditScreenL10n', array( 'leave' => __( 'You have changes that are not saved yet.', 'favr-sites' ) ) );

		/**
		 * Post types whose Publish box shows no Visibility row to Editors.
		 *
		 * @param list<string> $types Post types.
		 */
		$types = apply_filters( 'favr_sites_no_visibility_types', self::NO_VISIBILITY );
		if ( self::hidesVisibility( (string) $screen->post_type, $types ) ) {
			wp_add_inline_style( Skin::HANDLE, 'body.favr-skin #visibility.misc-pub-visibility{display:none}' );
		}
	}

	/**
	 * No Code tab or tag buttons on a record's description; a plain text box on media.
	 *
	 * @param mixed $settings  wp_editor() settings.
	 * @param mixed $editor_id Editor id.
	 * @return mixed
	 */
	public function editorSettings( $settings, $editor_id = '' ) {
		$screen = self::classicEditScreen();
		return $screen && is_array( $settings ) ? self::plainEditor( $settings, (string) $screen->post_type ) : $settings;
	}

	/**
	 * No "Read more" button on a record's description.
	 *
	 * @param mixed $buttons   Toolbar buttons.
	 * @param mixed $editor_id Editor id.
	 * @return mixed
	 */
	public function editorButtons( $buttons, $editor_id = '' ) {
		$screen = self::classicEditScreen();
		return $screen && is_array( $buttons ) ? self::withoutReadMore( $buttons, (string) $screen->post_type ) : $buttons;
	}

	/**
	 * Favr type inside the editor.
	 *
	 * @param mixed $init      TinyMCE settings.
	 * @param mixed $editor_id Editor id.
	 * @return mixed
	 */
	public function editorLook( $init, $editor_id = '' ) {
		if ( ! self::classicEditScreen() || ! is_array( $init ) ) {
			return $init;
		}
		return self::withContentStyle( $init, self::contentStyle( FAVR_SITES_URL . 'assets/fonts/source-sans-3.woff2' ) );
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
		if ( ! $screen ) {
			return;
		}
		$type = $post instanceof \WP_Post ? get_post_type_object( $post->post_type ) : null;
		if ( ! $type || self::hasBox( $wp_meta_boxes[ $screen->id ] ?? null, 'slugdiv' ) ) {
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
