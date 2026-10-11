<?php
/**
 * The toolbar search finds members, listings, events and documents, for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * The magnifier in the toolbar (⌘K / Ctrl+K) is WordPress's command palette. For Editors it
 * becomes a search of their records: a REST route finds members, directory listings, events,
 * documents, people, sponsors, pages and news posts by name (members and listings also by company
 * or e-mail, invoices by number or member when Favr Payments runs), and assets/skin/search.js
 * lists them in the palette. Choosing one opens its edit screen.
 *
 * The commands an Editor has no screen for go: "Go to: Patterns" and the site editor's loaders,
 * and the two screens they led to send an Editor back to the dashboard.
 */
final class Search {

	public const REST_NAMESPACE = 'favr-sites/v1';
	public const ROUTE          = '/search';

	/** At most this many results of one type, and this many in all. */
	public const PER_TYPE = 8;
	public const TOTAL    = 20;

	/** Shorter searches are ignored; longer ones are cut. */
	public const MIN_LENGTH = 2;
	public const MAX_LENGTH = 80;

	/** What is searched, in the order the results are listed (the menu's order, records first). */
	public const TYPES = array( 'favr_member', 'favr_business', 'favr_event', 'favr_document', 'favr_person', 'favr_sponsor', 'page', 'post' );

	/** Everything but the trash and WordPress's unsaved drafts. */
	public const STATUSES = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/** Post type => the fields searched besides the name: company and e-mail. */
	public const FIELDS = array(
		'favr_member'   => array( 'favr_member_organization', 'favr_member_email' ),
		'favr_business' => array( 'favr_organization', 'favr_email' ),
	);

	/**
	 * The palette's loaders that go for Editors: "Go to: Patterns" (and Styles, Navigation,
	 * Templates), WordPress's own page and post search (the Favr search lists both, with their
	 * type) and the template and stylesheet lookups an Editor may not use.
	 */
	public const LOADERS_OFF = array(
		'core/edit-site/basic-navigation',
		'core/edit-site/navigate-pages',
		'core/edit-site/navigate-posts',
		'core/edit-site/navigate-templates',
		'core/edit-site/navigate-template-parts',
		'core/edit-site/global-styles-css',
	);

	/** Who keeps the invoices: Favr Payments' capability for its Invoices screen. */
	private const INVOICES_CAP = 'edit_others_favr_members';

	/** WordPress's words on the search field, in its default text domain. */
	private const CORE_PLACEHOLDER = 'Search commands and settings';

	/**
	 * The search term, cleaned: plain text on one line, no longer than a name, and empty when it
	 * is too short to search for.
	 *
	 * @param mixed $raw What the request sent.
	 */
	public static function term( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$term = mb_substr( sanitize_text_field( $raw ), 0, self::MAX_LENGTH );
		return mb_strlen( $term ) < self::MIN_LENGTH ? '' : $term;
	}

	/**
	 * What every query shares: one type, no trash, ids only, nothing counted or cached.
	 *
	 * @param string $type  Post type.
	 * @param int    $limit How many.
	 * @return array<string, mixed>
	 */
	private static function query( string $type, int $limit ): array {
		return array(
			'post_type'              => $type,
			'post_status'            => self::STATUSES,
			'posts_per_page'         => $limit,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
	}

	/**
	 * The records of one type whose name holds the term.
	 *
	 * @param string $type Post type.
	 * @param string $term Search::term().
	 * @return array<string, mixed> Query arguments.
	 */
	public static function titleQuery( string $type, string $term ): array {
		return self::query( $type, self::PER_TYPE ) + array(
			's'              => $term,
			'search_columns' => array( 'post_title' ),
		);
	}

	/**
	 * The members or listings whose company or e-mail holds the term, for the places the name
	 * search left free.
	 *
	 * @param string    $type  Post type.
	 * @param string    $term  Search::term().
	 * @param list<int> $found Ids the name search found.
	 * @param int       $limit How many are still missing.
	 * @return array<string, mixed> Query arguments; empty when there is nothing to ask.
	 */
	public static function fieldQuery( string $type, string $term, array $found, int $limit ): array {
		if ( $limit < 1 || ! isset( self::FIELDS[ $type ] ) ) {
			return array();
		}
		$fields = array( 'relation' => 'OR' );
		foreach ( self::FIELDS[ $type ] as $key ) {
			$fields[] = array(
				'key'     => $key,
				'value'   => $term,
				'compare' => 'LIKE',
			);
		}
		return self::query( $type, $limit ) + array(
			'post__not_in' => $found,
			'meta_query'   => $fields, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- two named keys, a handful of rows, at most twice per search.
		);
	}

	/**
	 * One result: its title as plain text, what it is, and where it is edited. Nothing else
	 * leaves the site.
	 *
	 * @param string $title Title as stored.
	 * @param string $type  Type label ("Member").
	 * @param string $url   Edit screen.
	 * @return array{title: string, type: string, url: string}
	 */
	public static function result( string $title, string $type, string $url ): array {
		$title = trim( html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return array(
			'title' => '' === $title ? __( '(no title)', 'favr-sites' ) : $title,
			'type'  => $type,
			'url'   => $url,
		);
	}

	/**
	 * The results for the records this user may edit; the others are left out.
	 *
	 * @param array<int|string> $ids   Post ids.
	 * @param string            $label Type label.
	 * @return list<array{title: string, type: string, url: string}>
	 */
	public static function rows( array $ids, string $label ): array {
		$rows = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id < 1 || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$url = (string) get_edit_post_link( $id, 'raw' );
			if ( '' !== $url ) {
				$rows[] = self::result( (string) get_post_field( 'post_title', $id, 'raw' ), $label, $url );
			}
		}
		return $rows;
	}

	/**
	 * At most PER_TYPE of a type and TOTAL in all. When there are more, every type gives up its
	 * last matches in turn, so one type with many matches never crowds out another.
	 *
	 * @template T
	 * @param array<string, array<int, T>> $groups Type => its matches, best first.
	 * @return array<string, list<T>> The same, cut; types without matches are gone.
	 */
	public static function cap( array $groups ): array {
		$groups = array_filter( array_map( static fn( array $rows ): array => array_slice( array_values( $rows ), 0, self::PER_TYPE ), $groups ) );
		$take   = array_fill_keys( array_keys( $groups ), 0 );
		$left   = self::TOTAL;
		for ( $round = 0; $round < self::PER_TYPE && $left > 0; $round++ ) {
			foreach ( $groups as $type => $rows ) {
				if ( $left > 0 && isset( $rows[ $round ] ) ) {
					++$take[ $type ];
					--$left;
				}
			}
		}
		$out = array();
		foreach ( $groups as $type => $rows ) {
			$out[ $type ] = array_slice( $rows, 0, $take[ $type ] );
		}
		return $out;
	}

	/** May this request search? Editors only: everybody else keeps WordPress as it is. */
	public static function allowed(): bool {
		return is_user_logged_in() && Audience::current();
	}

	/**
	 * A screen an Editor has no use for: the Patterns list, a new pattern, the site editor.
	 * Editing a synced pattern from inside a news post stays possible.
	 *
	 * @param string $pagenow   The admin file being loaded.
	 * @param string $post_type Its post type, '' when it names none.
	 */
	public static function closed( string $pagenow, string $post_type ): bool {
		if ( 'site-editor.php' === $pagenow ) {
			return true;
		}
		return 'wp_block' === $post_type && in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true );
	}

	/**
	 * What assets/skin/search.js is told.
	 *
	 * @return array{path: string, min: int, placeholder: string, off: list<string>}
	 */
	public static function settings(): array {
		return array(
			'path'        => '/' . self::REST_NAMESPACE . self::ROUTE,
			'min'         => self::MIN_LENGTH,
			'placeholder' => __( 'Find a member, event or page', 'favr-sites' ),
			'off'         => self::LOADERS_OFF,
		);
	}

	/** Hooks. Registered on every request: the route answers over REST, the rest runs in wp-admin. */
	public function hook(): void {
		add_action( 'rest_api_init', array( $this, 'route' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 20 ); // After WordPress enqueues its palette (10).
		add_action( 'admin_init', array( $this, 'redirect' ) );
		add_action( 'admin_page_access_denied', array( $this, 'redirect' ) ); // The site editor is refused before admin_init.
	}

	/** GET favr-sites/v1/search?q=… */
	public function route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'respond' ),
				'permission_callback' => array( self::class, 'allowed' ),
				'args'                => array(
					'q' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Answer a search.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function respond( $request ) {
		$term = self::term( $request->get_param( 'q' ) );
		return rest_ensure_response( array( 'results' => '' === $term ? array() : self::find( $term ) ) );
	}

	/**
	 * Everything this user may edit that matches, type by type.
	 *
	 * @param string $term Search::term(), not empty.
	 * @return list<array{title: string, type: string, url: string}>
	 */
	public static function find( string $term ): array {
		$groups = array();
		foreach ( self::TYPES as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object || ! current_user_can( $object->cap->edit_posts ) ) {
				continue;
			}
			$ids  = array_map( 'intval', get_posts( self::titleQuery( $type, $term ) ) );
			$more = self::fieldQuery( $type, $term, $ids, self::PER_TYPE - count( $ids ) );
			if ( $more ) {
				$ids = array_merge( $ids, array_map( 'intval', get_posts( $more ) ) );
			}
			$label           = Words::sentence( (string) ( $object->labels->name_admin_bar ?? $object->labels->singular_name ?? '' ) );
			$groups[ $type ] = self::rows( $ids, $label );
		}
		$groups['invoice'] = self::invoices( $term );

		$results = array();
		foreach ( self::cap( $groups ) as $rows ) {
			$results = array_merge( $results, $rows );
		}
		return $results;
	}

	/**
	 * Invoices by number, member or billing name, when Favr Payments runs and this user keeps
	 * its invoices. Favr Payments does the search; a failure there costs the invoices, not the search.
	 *
	 * @param string $term Search::term(), not empty.
	 * @return list<array{title: string, type: string, url: string}>
	 */
	private static function invoices( string $term ): array {
		$store  = '\FavrPayments\Billing\Invoices';
		$screen = '\FavrPayments\Admin\InvoicesScreen';
		if ( ! is_callable( array( $store, 'query' ) ) || ! is_callable( array( $screen, 'url' ) ) ) {
			return array();
		}
		if ( ! current_user_can( self::INVOICES_CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Favr Payments' capability, named above.
			return array();
		}
		$rows = array();
		try {
			$found = $store::query(
				array(
					'search'   => $term,
					'per_page' => self::PER_TYPE,
				)
			);
			foreach ( (array) ( $found['rows'] ?? array() ) as $invoice ) {
				$number = (string) ( $invoice->number ?? '' );
				$id     = (int) ( $invoice->id ?? 0 );
				if ( '' === $number || $id < 1 ) {
					continue;
				}
				$bill_to = $invoice->bill_to ?? array();
				$name    = is_array( $bill_to ) ? trim( (string) ( $bill_to['name'] ?? '' ) ) : ''; // The name the invoice list shows.
				$rows[]  = self::result(
					'' === $name ? $number : $number . ' · ' . $name,
					__( 'Invoice', 'favr-sites' ),
					(string) $screen::url( array( 'invoice' => $id ) )
				);
			}
		} catch ( \Throwable $e ) {
			return array();
		}
		return $rows;
	}

	/** The search script, its words and its look, wherever WordPress loads its palette for an Editor. */
	public function assets(): void {
		if ( ! Audience::current() || ! wp_script_is( 'wp-core-commands', 'enqueued' ) ) {
			return;
		}
		$settings = self::settings();
		$js       = 'assets/skin/search.js';
		$css      = 'assets/skin/search.css';

		// WordPress reads its words for the field once, as its script starts: answer before that.
		wp_add_inline_script(
			'wp-commands',
			sprintf(
				'if ( window.wp && wp.hooks ) { wp.hooks.addFilter( "i18n.gettext_default", "favr-sites/search", function ( translation, text ) { return %1$s === text ? %2$s : translation; } ); }',
				wp_json_encode( self::CORE_PLACEHOLDER, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
				wp_json_encode( $settings['placeholder'], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			),
			'before'
		);
		wp_enqueue_script( 'favr-sites-search', FAVR_SITES_URL . $js, array( 'wp-api-fetch', 'wp-commands', 'wp-core-commands', 'wp-data', 'wp-element' ), self::version( $js ), true );
		wp_add_inline_script( 'favr-sites-search', 'window.favrSitesSearch = ' . wp_json_encode( $settings, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
		wp_enqueue_style( 'favr-sites-search', FAVR_SITES_URL . $css, array( 'wp-commands' ), self::version( $css ) );
	}

	/** Patterns and the site editor are not for Editors: back to the dashboard. */
	public function redirect(): void {
		global $pagenow;
		if ( ! Audience::current() ) {
			return;
		}
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read to pick a screen, nothing is saved.
		if ( self::closed( (string) $pagenow, $type ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Cache-busting version: plugin version + file time.
	 *
	 * @param string $file Path inside the plugin.
	 */
	private static function version( string $file ): string {
		return FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $file );
	}
}
