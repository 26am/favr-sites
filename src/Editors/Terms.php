<?php
/**
 * Plainer category and level screens for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Skin\Skin;

/**
 * For Editors, on every taxonomy's screens (edit-tags.php, term.php): no Slug. The column leaves
 * the table; the field is hidden in the add form, in Quick Edit and on the edit screen (no hook
 * removes those, so a few rules are printed on the skin's sheet). WordPress still gets the field:
 * a new term's slug is made from its name, and an existing one keeps the slug it has.
 *
 * WordPress's helper paragraphs under the fields ("The name is how it appears on your site.",
 * the Jazz and Bebop example) go, or become one plain line. They are taxonomy labels, so every
 * taxonomy's labels are filtered as it is registered.
 */
final class Terms {

	/** Screens that list or edit terms. */
	private const SCREENS = array( 'edit-tags', 'term' );

	/**
	 * The table's columns without Slug, for Editors.
	 *
	 * @param mixed $columns   Column key => heading.
	 * @param bool  $is_editor In the Favr audience.
	 * @return mixed
	 */
	public static function columns( $columns, bool $is_editor ) {
		if ( ! $is_editor ) {
			return $columns;
		}
		$columns = is_array( $columns ) ? $columns : array();
		unset( $columns['slug'] );
		return $columns;
	}

	/**
	 * What stands under each field of a term instead of WordPress's paragraph: nothing, or one
	 * plain line. Label key => text.
	 *
	 * @return array<string, string>
	 */
	public static function help(): array {
		return array(
			'name_field_description'   => '',
			'slug_field_description'   => '',
			'parent_field_description' => __( 'Optional. Choose one to file this under it.', 'favr-sites' ),
			'desc_field_description'   => __( 'Optional. It may not show on your site.', 'favr-sites' ),
		);
	}

	/**
	 * A taxonomy's labels with Favr's help, for Editors.
	 *
	 * @param mixed $labels    The taxonomy's labels (an object).
	 * @param bool  $is_editor In the Favr audience.
	 * @return mixed
	 */
	public static function labels( $labels, bool $is_editor ) {
		if ( ! $is_editor || ! is_object( $labels ) ) {
			return $labels;
		}
		foreach ( self::help() as $key => $line ) {
			$labels->$key = $line;
		}
		return $labels;
	}

	/**
	 * The rules that take the Slug field off the screen: `.term-slug-wrap` is the field in the
	 * add form and its row on the edit screen; Quick Edit has no class for it. An emptied helper
	 * paragraph is taken out too, so it leaves no gap.
	 */
	public static function css(): string {
		return '.term-slug-wrap,.inline-edit-row label:has(input[name="slug"]){display:none}'
			. '.form-field p[id$="-description"]:empty{display:none}';
	}

	/**
	 * Is this a screen that lists or edits terms?
	 *
	 * @param string $base The screen's base.
	 */
	public static function isTermScreen( string $base ): bool {
		return in_array( $base, self::SCREENS, true );
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'register_taxonomy_args', array( $this, 'taxonomy' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ), 21 ); // After Skin\Skin (20) registers the sheet.
	}

	/**
	 * Every taxonomy, as it is registered: its label and column filters carry its name, so they
	 * can only be added here. Nothing about the taxonomy itself changes.
	 *
	 * @param mixed $args     The taxonomy's arguments.
	 * @param mixed $taxonomy Its name.
	 * @return mixed
	 */
	public function taxonomy( $args, $taxonomy ) {
		if ( is_string( $taxonomy ) && '' !== $taxonomy ) {
			add_filter( 'taxonomy_labels_' . $taxonomy, array( $this, 'termLabels' ) );
			// Also when a row comes back from Quick Edit or the add form (admin-ajax.php).
			add_filter( 'manage_edit-' . $taxonomy . '_columns', array( $this, 'termColumns' ), PHP_INT_MAX );
		}
		return $args;
	}

	/**
	 * Labels filter.
	 *
	 * @param mixed $labels Labels.
	 * @return mixed
	 */
	public function termLabels( $labels ) {
		return self::labels( $labels, self::forEditor() );
	}

	/**
	 * Columns filter.
	 *
	 * @param mixed $columns Columns.
	 * @return mixed
	 */
	public function termColumns( $columns ) {
		return self::columns( $columns, self::forEditor() );
	}

	/**
	 * Is the current user an Editor? A plugin may register a taxonomy while it loads, before
	 * WordPress can tell who is asking; nobody is an Editor yet then.
	 */
	private static function forEditor(): bool {
		return function_exists( 'wp_get_current_user' ) && Audience::current();
	}

	/** Print the hiding rules on the skin's sheet, which only Editors get. */
	public function styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen instanceof \WP_Screen && self::isTermScreen( (string) $screen->base ) && Audience::current() ) {
			wp_add_inline_style( Skin::HANDLE, self::css() );
		}
	}
}
