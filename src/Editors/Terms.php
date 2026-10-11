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
 *
 * The confirmations name the thing ("Category added.", "Membership level updated.") instead of
 * WordPress's "Item added."; a name used twice is refused in the same word. A level has no
 * "Parent level". The second way back WordPress prints under "… updated." is hidden: the link
 * above the page title (Editors\Wayfinding) is the one way back.
 */
final class Terms {

	/** Screens that list or edit terms. */
	private const SCREENS = array( 'edit-tags', 'term' );

	/** Taxonomies that are one flat list although WordPress would let a term sit under another: no Parent field. */
	public const FLAT = array( 'favr_member_level' );

	/** Taxonomies two record types share (members and directory listings): their Count adds both, so it is not shown. */
	public const SHARED = array( 'favr_member_level' );

	/** WordPress's sentences when a name is already used in the taxonomy. */
	private const TAKEN = array(
		'A term with the name provided already exists with this parent.',
		'A term with the name provided already exists in this taxonomy.',
	);

	/**
	 * The word for the thing being added, while WordPress adds a term ('' at any other time).
	 *
	 * @var string
	 */
	private string $adding = '';

	/**
	 * The table's columns without Slug, for Editors; without Count too where the count would
	 * add two kinds of record together.
	 *
	 * @param mixed $columns   Column key => heading.
	 * @param bool  $is_editor In the Favr audience.
	 * @param bool  $shared    The taxonomy is shared by two record types.
	 * @return mixed
	 */
	public static function columns( $columns, bool $is_editor, bool $shared = false ) {
		if ( ! $is_editor ) {
			return $columns;
		}
		$columns = is_array( $columns ) ? $columns : array();
		unset( $columns['slug'] );
		if ( $shared ) {
			unset( $columns['posts'] );
		}
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
	 * paragraph is taken out too, so it leaves no gap. So is the "Go to categories" link WordPress
	 * prints under a confirmation, and, for a flat taxonomy, the Parent field.
	 *
	 * @param bool $flat The screen's taxonomy is one flat list.
	 */
	public static function css( bool $flat = false ): string {
		return '.term-slug-wrap,.inline-edit-row label:has(input[name="slug"]){display:none}'
			. '.form-field p[id$="-description"]:empty{display:none}'
			. '#message p:has(>a[href*="edit-tags.php"]){display:none}'
			. ( $flat ? '.term-parent-wrap{display:none}' : '' );
	}

	/**
	 * The confirmations of a taxonomy, in WordPress's order (1 added, 2 deleted, 3 updated,
	 * 4 not added, 5 not updated, 6 several deleted), from its own words.
	 *
	 * @param string $singular The taxonomy's word for one ("Category").
	 * @param string $plural   Its word for several ("Categories").
	 * @return array<int, string>
	 */
	public static function messages( string $singular, string $plural ): array {
		$one  = Words::sentence( $singular );
		$many = Words::sentence( $plural );
		return array(
			0 => '',
			/* translators: %s: what was added, e.g. "Category". */
			1 => sprintf( __( '%s added.', 'favr-sites' ), $one ),
			/* translators: %s: what was deleted, e.g. "Category". */
			2 => sprintf( __( '%s deleted.', 'favr-sites' ), $one ),
			/* translators: %s: what was updated, e.g. "Category". */
			3 => sprintf( __( '%s updated.', 'favr-sites' ), $one ),
			/* translators: %s: what could not be added, e.g. "Category". */
			4 => sprintf( __( '%s not added.', 'favr-sites' ), $one ),
			/* translators: %s: what could not be updated, e.g. "Category". */
			5 => sprintf( __( '%s not updated.', 'favr-sites' ), $one ),
			/* translators: %s: what was deleted, plural, e.g. "Categories". */
			6 => sprintf( __( '%s deleted.', 'favr-sites' ), $many ),
		);
	}

	/**
	 * What an Editor reads when the name is already used: "Another category already has that name."
	 *
	 * @param string $singular The taxonomy's word for one.
	 */
	public static function duplicate( string $singular ): string {
		$one = Words::sentence( $singular );
		if ( preg_match( '/^[A-Z][a-z]/', $one ) ) {
			$one = lcfirst( $one ); // "CPA firm type" keeps its capitals.
		}
		/* translators: %s: the kind of thing, e.g. "category" or "membership level". */
		return sprintf( __( 'Another %s already has that name.', 'favr-sites' ), $one );
	}

	/**
	 * WordPress's two "A term with the name provided already exists…" sentences in the thing's
	 * own word; every other phrase as it was.
	 *
	 * @param mixed  $translation What would be shown.
	 * @param mixed  $text        The phrase in the code.
	 * @param mixed  $domain      Whose phrase it is ('default' is WordPress).
	 * @param string $singular    The taxonomy's word for one.
	 * @return mixed
	 */
	public static function taken( $translation, $text, $domain, string $singular ) {
		return 'default' === $domain && in_array( $text, self::TAKEN, true ) ? self::duplicate( $singular ) : $translation;
	}

	/**
	 * Is this taxonomy one flat list for Editors (no Parent field)?
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public static function isFlat( string $taxonomy ): bool {
		/**
		 * Taxonomies whose add and edit forms show Editors no Parent field.
		 *
		 * @param list<string> $taxonomies Taxonomy names.
		 */
		return in_array( $taxonomy, (array) apply_filters( 'favr_sites_flat_taxonomies', self::FLAT ), true );
	}

	/**
	 * Is this taxonomy shared by two record types, so that its Count column adds them together?
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public static function isShared( string $taxonomy ): bool {
		/**
		 * Taxonomies whose list shows Editors no Count column.
		 *
		 * @param list<string> $taxonomies Taxonomy names.
		 */
		return in_array( $taxonomy, (array) apply_filters( 'favr_sites_shared_taxonomies', self::SHARED ), true );
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
		add_filter( 'term_updated_messages', array( $this, 'termMessages' ), PHP_INT_MAX );
		add_filter( 'pre_insert_term', array( $this, 'naming' ), 10, 2 );
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
		// The filter's name carries the taxonomy: manage_edit-{taxonomy}_columns.
		$taxonomy = preg_match( '/^manage_edit-(.+)_columns$/', (string) current_filter(), $found ) ? $found[1] : '';
		return self::columns( $columns, self::forEditor(), self::isShared( $taxonomy ) );
	}

	/**
	 * The confirmations of every taxonomy with screens, for Editors.
	 *
	 * @param mixed $messages Taxonomy name => messages.
	 * @return mixed
	 */
	public function termMessages( $messages ) {
		if ( ! is_array( $messages ) || ! self::forEditor() ) {
			return $messages;
		}
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $name => $taxonomy ) {
			if ( isset( $taxonomy->labels->singular_name, $taxonomy->labels->name ) ) {
				$messages[ $name ] = self::messages( (string) $taxonomy->labels->singular_name, (string) $taxonomy->labels->name );
			}
		}
		return $messages;
	}

	/**
	 * WordPress is about to add a term: from here on its "A term with the name provided already
	 * exists" is said in the taxonomy's own word, for Editors. The name itself is not touched.
	 *
	 * @param mixed $term     The name being added.
	 * @param mixed $taxonomy The taxonomy.
	 * @return mixed
	 */
	public function naming( $term, $taxonomy ) {
		if ( ! is_string( $taxonomy ) || ! self::forEditor() ) {
			return $term;
		}
		$object = get_taxonomy( $taxonomy );
		$word   = $object && isset( $object->labels->singular_name ) ? (string) $object->labels->singular_name : '';
		if ( '' !== $word ) {
			if ( '' === $this->adding ) {
				add_filter( 'gettext', array( $this, 'takenName' ), 10, 3 );
			}
			$this->adding = $word;
		}
		return $term;
	}

	/**
	 * Translation filter while a term is added.
	 *
	 * @param mixed $translation What would be shown.
	 * @param mixed $text        The phrase in the code.
	 * @param mixed $domain      Whose phrase it is.
	 * @return mixed
	 */
	public function takenName( $translation, $text, $domain ) {
		return self::taken( $translation, $text, $domain, $this->adding );
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
			wp_add_inline_style( Skin::HANDLE, self::css( self::isFlat( (string) $screen->taxonomy ) ) );
		}
	}
}
