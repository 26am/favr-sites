# Favr Editor Experience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** For Editors: pages only in Elementor (non-Elementor pages locked), News posts only in a trimmed block editor, tidy list tables and menu. For everyone: comments off.

**Architecture:** Small units in Favr Sites, each with a pure core (unit-tested) and thin WordPress glue. Every Editor-only behaviour checks `Audience::current()`. Enforcement is permission-level (`map_meta_cap`, routing), not CSS.

**Tech Stack:** PHP 8.1+, WordPress 6.7+, Elementor (optional at runtime), PHPUnit 9.6 + Brain Monkey, WPCS.

**Spec:** `docs/superpowers/specs/2026-09-29-editor-experience-design.md`

## Global Constraints

- Editor-only behaviour uses `Dashboard\Audience` (Editor role, not `manage_options`); Administrators keep stock WordPress. Comments are off for everyone.
- Elementor inactive → page lock and routing do nothing.
- No data is modified (comments kept; pages not converted).
- No JS; CSS inline and scoped; `composer test` green, `composer lint` 0 errors.
- Menu order: Dashboard, Pages, News, Media, Approvals, Directory, Events, Members, Profile, Tools.
- Block list (posts): paragraph, heading, list, list-item, quote, image, gallery, embed, buttons, button, separator, table, file.

## Review Focus

- A locked page reached by a direct URL (`post.php?action=edit`, `action=elementor`, REST) → refused, never redirected into an editor (glue check in Task 6 real check; `isLocked` table in Task 1).
- The Trash view of a list (`untrash`, `delete` actions) → still usable (test in Task 3).
- A plugin top-level menu the allow-list doesn't know → hidden for Editors, untouched for Administrators (test in Task 4).
- Front-end comment form or comment REST POST → refused (test of `endpoints()` in Task 5; real check in Task 6).
- Elementor deactivated → pages editable in the block editor, nothing locked (test in Task 1).

## File structure

```
src/Dashboard/Audience.php     + current() memo (+ flush() for tests)
src/Dashboard/Icons.php        + 'trash'
src/Editors/PageLock.php       isLocked() + map_meta_cap + "Managed by Favr"
src/Editors/Routing.php        pages → Elementor, posts lose Elementor support
src/Editors/BlockList.php      allowed blocks + editor settings for posts
src/Editors/ListTables.php     row actions + columns + trash icon CSS
src/Editors/Menu.php           allow-list order + "News" labels
src/Comments/Off.php           comments off site-wide
src/Plugin.php                 wiring
tests/Unit/{PageLock,BlockList,ListTables,Menu,CommentsOff}Test.php
```

---

### Task 1: Audience memo + PageLock

**Files:** Modify `src/Dashboard/Audience.php`; Create `src/Editors/PageLock.php`; Test `tests/Unit/PageLockTest.php`.

**Interfaces:**
- Produces: `Audience::current(): bool`, `Audience::flush(): void`; `PageLock::isLocked( string $post_type, string $status, bool $built_with_elementor, bool $elementor_active ): bool`; `PageLock::hook(): void`.

- [ ] **Step 1: Failing test** — `tests/Unit/PageLockTest.php`
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\PageLock;

final class PageLockTest extends TestCase {

	public function test_only_existing_non_elementor_pages_are_locked_when_elementor_runs(): void {
		$this->assertTrue( PageLock::isLocked( 'page', 'publish', false, true ) );
		$this->assertTrue( PageLock::isLocked( 'page', 'draft', false, true ) );
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', true, true ) );   // built with Elementor
		$this->assertFalse( PageLock::isLocked( 'page', 'auto-draft', false, true ) ); // being created
		$this->assertFalse( PageLock::isLocked( 'post', 'publish', false, true ) );   // posts never
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', false, false ) );  // Elementor off
	}
}
```
- [ ] **Step 2:** `composer test -- --filter PageLockTest` → FAIL (class not found).
- [ ] **Step 3: Implement** `src/Editors/PageLock.php`:
```php
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
 * Plugin pages (login, account, register…) hold a shortcode that makes them work; opening them in
 * Elementor could lose it. Editors can't edit, trash or publish them; they're tagged "Managed by Favr".
 */
final class PageLock {

	private const CAPS = array( 'edit_post', 'delete_post', 'publish_post' );

	/**
	 * Locked for Editors?
	 *
	 * @param string $post_type            Post type.
	 * @param string $status               Post status.
	 * @param bool   $built_with_elementor _elementor_edit_mode = builder.
	 * @param bool   $elementor_active     Elementor loaded.
	 */
	public static function isLocked( string $post_type, string $status, bool $built_with_elementor, bool $elementor_active ): bool {
		return $elementor_active && 'page' === $post_type && 'auto-draft' !== $status && ! $built_with_elementor;
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
	public function caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, self::CAPS, true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || ! self::lockedPost( $post ) ) {
			return $caps;
		}
		$user = get_userdata( $user_id );
		return $user && Audience::includes( $user ) ? array( 'do_not_allow' ) : $caps;
	}

	/**
	 * "Managed by Favr" in the Pages list, for Editors.
	 *
	 * @param array<string, string> $states States.
	 * @param \WP_Post              $post   Post.
	 * @return array<string, string>
	 */
	public function states( array $states, \WP_Post $post ): array {
		if ( self::lockedPost( $post ) && Audience::current() ) {
			$states['favr_managed'] = __( 'Managed by Favr', 'favr-sites' );
		}
		return $states;
	}

	/**
	 * Is this post a locked page?
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function lockedPost( \WP_Post $post ): bool {
		return self::isLocked( $post->post_type, $post->post_status, 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ), (bool) did_action( 'elementor/loaded' ) );
	}
}
```
In `src/Dashboard/Audience.php` add:
```php
	/**
	 * Memoized answer for the current user (checked many times per request).
	 *
	 * @var array<int, bool>
	 */
	private static array $memo = array();

	/** Does the current user get the Favr experience? */
	public static function current(): bool {
		$user = wp_get_current_user();
		return self::$memo[ (int) $user->ID ] ??= self::includes( $user );
	}

	/** Reset the memo (tests, user switches). */
	public static function flush(): void {
		self::$memo = array();
	}
```
- [ ] **Step 4:** `composer test && composer lint` → green.
- [ ] **Step 5:** Commit `feat: lock pages not built with Elementor for Editors`.

---

### Task 2: Routing (pages → Elementor, no Elementor on posts) + BlockList

**Files:** Create `src/Editors/Routing.php`, `src/Editors/BlockList.php`; Test `tests/Unit/BlockListTest.php`.

**Interfaces:**
- Consumes: `Audience::current()`.
- Produces: `Routing::elementorUrl( int $post_id ): string`; `BlockList::BLOCKS`, `BlockList::blocksFor( string $post_type ): ?array`, `BlockList::trimSettings( array $settings ): array`.

- [ ] **Step 1: Failing test** — `tests/Unit/BlockListTest.php`
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\BlockList;

final class BlockListTest extends TestCase {

	public function test_posts_get_the_short_list_and_other_types_are_untouched(): void {
		$this->assertContains( 'core/paragraph', BlockList::blocksFor( 'post' ) );
		$this->assertContains( 'core/embed', BlockList::blocksFor( 'post' ) );
		$this->assertNotContains( 'core/html', BlockList::blocksFor( 'post' ) );
		$this->assertNull( BlockList::blocksFor( 'page' ) );
		$this->assertNull( BlockList::blocksFor( 'favr_event' ) );
	}

	public function test_editor_settings_drop_patterns_openverse_and_code_editing(): void {
		$out = BlockList::trimSettings( array( 'codeEditingEnabled' => true, 'enableOpenverseMediaCategory' => true, '__experimentalBlockPatterns' => array( 1 ), 'keep' => 'me' ) );
		$this->assertFalse( $out['codeEditingEnabled'] );
		$this->assertFalse( $out['enableOpenverseMediaCategory'] );
		$this->assertSame( array(), $out['__experimentalBlockPatterns'] );
		$this->assertSame( array(), $out['__experimentalBlockPatternCategories'] );
		$this->assertSame( 'me', $out['keep'] );
	}
}
```
- [ ] **Step 2:** run → FAIL (class not found).
- [ ] **Step 3: Implement** `src/Editors/BlockList.php`:
```php
<?php
/**
 * A short, hard-to-break block list for News posts.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * Editors writing News get writing blocks only: no patterns, Openverse or code editor.
 */
final class BlockList {

	public const BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/image',
		'core/gallery',
		'core/embed',
		'core/buttons',
		'core/button',
		'core/separator',
		'core/table',
		'core/file',
	);

	/**
	 * Allowed blocks for a post type, or null for "leave alone".
	 *
	 * @param string $post_type Post type.
	 * @return list<string>|null
	 */
	public static function blocksFor( string $post_type ): ?array {
		return 'post' === $post_type ? self::BLOCKS : null;
	}

	/**
	 * Editor settings without patterns, Openverse or the code editor.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function trimSettings( array $settings ): array {
		$settings['codeEditingEnabled']                   = false;
		$settings['enableOpenverseMediaCategory']         = false;
		$settings['__experimentalBlockPatterns']          = array();
		$settings['__experimentalBlockPatternCategories'] = array();
		return $settings;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'allowed_block_types_all', array( $this, 'allowed' ), PHP_INT_MAX, 2 );
		add_filter( 'block_editor_settings_all', array( $this, 'settings' ), PHP_INT_MAX, 2 );
		add_filter( 'should_load_remote_block_patterns', array( $this, 'remotePatterns' ) );
	}

	/**
	 * Allowed blocks.
	 *
	 * @param bool|array<string> $allowed Allowed.
	 * @param mixed              $context \WP_Block_Editor_Context.
	 * @return bool|array<string>
	 */
	public function allowed( $allowed, $context ) {
		$type = self::postType( $context );
		return ( $type && Audience::current() ) ? ( self::blocksFor( $type ) ?? $allowed ) : $allowed;
	}

	/**
	 * Editor settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param mixed                $context  \WP_Block_Editor_Context.
	 * @return array<string, mixed>
	 */
	public function settings( array $settings, $context ): array {
		$type = self::postType( $context );
		return ( $type && null !== self::blocksFor( $type ) && Audience::current() ) ? self::trimSettings( $settings ) : $settings;
	}

	/**
	 * No remote (wordpress.org) patterns for Editors.
	 *
	 * @param bool $load Load.
	 */
	public function remotePatterns( $load ): bool {
		return Audience::current() ? false : (bool) $load;
	}

	/**
	 * Post type from a block editor context.
	 *
	 * @param mixed $context Context.
	 */
	private static function postType( $context ): string {
		return is_object( $context ) && isset( $context->post ) && $context->post instanceof \WP_Post ? $context->post->post_type : '';
	}
}
```
`src/Editors/Routing.php`:
```php
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
	 */
	public function editLink( $link, $post_id, $context ) {
		if ( ! $link || ! self::active() || 'page' !== get_post_type( (int) $post_id ) ) {
			return $link;
		}
		$url = self::elementorUrl( (int) $post_id );
		return 'display' === $context ? esc_url( $url ) : $url;
	}

	/** post.php?action=edit on a page → Elementor (only if the Editor may edit it). */
	public function redirectEdit(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' !== $action || ! $post_id || ! self::active() || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		wp_safe_redirect( self::elementorUrl( $post_id ) );
		exit;
	}

	/** post-new.php?post_type=page → a new Elementor page. */
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
```
- [ ] **Step 4:** `composer test && composer lint` → green.
- [ ] **Step 5:** Commit `feat: pages open in Elementor and News in a short block editor for Editors`.

---

### Task 3: List tables (row actions, columns, trash icon)

**Files:** Create `src/Editors/ListTables.php`; Modify `src/Dashboard/Icons.php` (add `trash`); Test `tests/Unit/ListTablesTest.php`.

**Interfaces:**
- Produces: `ListTables::rowActions( array $actions ): array`, `ListTables::trashIcon( string $html ): string`, `ListTables::columns( array $columns ): array`.

- [ ] **Step 1: Failing test**
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\ListTables;

final class ListTablesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
	}

	public function test_only_view_and_trash_survive_and_trash_becomes_an_icon(): void {
		$actions = array(
			'edit'                => '<a href="post.php?post=1&amp;action=edit">Edit</a>',
			'inline hide-if-no-js' => '<button>Quick&nbsp;Edit</button>',
			'trash'               => '<a href="post.php?post=1&amp;action=trash&amp;_wpnonce=abc" class="submitdelete" aria-label="Move “About” to the Trash">Trash</a>',
			'view'                => '<a href="/about/">View</a>',
			'edit_with_elementor' => '<a href="post.php?post=1&amp;action=elementor">Edit with Elementor</a>',
			'duplicate'           => '<a href="#">Duplicate</a>',
		);
		$out = ListTables::rowActions( $actions );
		$this->assertSame( array( 'view', 'trash' ), array_keys( $out ) );
		$this->assertStringContainsString( 'href="post.php?post=1&amp;action=trash&amp;_wpnonce=abc"', $out['trash'] );
		$this->assertStringContainsString( 'class="favr-trash submitdelete"', $out['trash'] );
		$this->assertStringContainsString( '<svg', $out['trash'] );
		$this->assertStringNotContainsString( '>Trash<', $out['trash'] );
	}

	public function test_trash_view_keeps_restore_and_delete(): void {
		$actions = array(
			'untrash' => '<a href="#r">Restore</a>',
			'delete'  => '<a href="#d" class="submitdelete">Delete Permanently</a>',
		);
		$this->assertSame( $actions, ListTables::rowActions( $actions ) );
	}

	public function test_columns_drop_comments_and_yoast(): void {
		$cols = array( 'cb' => '', 'title' => 'Title', 'author' => 'Author', 'comments' => 'C', 'wpseo-score' => 'SEO', 'wpseo-links' => 'L', 'date' => 'Date' );
		$this->assertSame( array( 'cb', 'title', 'author', 'date' ), array_keys( ListTables::columns( $cols ) ) );
	}
}
```
- [ ] **Step 2:** run → FAIL.
- [ ] **Step 3: Implement** `src/Editors/ListTables.php`:
```php
<?php
/**
 * Calmer post lists for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;
use FavrSites\Dashboard\Icons;

/**
 * Row links: the title opens the right editor; "View" stays; Trash becomes a quiet bin icon.
 * Columns: no Comments, no Yoast scores.
 */
final class ListTables {

	private const KEEP = array( 'view', 'trash', 'untrash', 'delete' );

	/**
	 * Keep only the row actions an Editor needs, in a fixed order.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public static function rowActions( array $actions ): array {
		$out = array();
		foreach ( self::KEEP as $key ) {
			if ( isset( $actions[ $key ] ) ) {
				$out[ $key ] = 'trash' === $key ? self::trashIcon( (string) $actions[ $key ] ) : $actions[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Core's Trash link as a small icon (the href is already escaped by core).
	 *
	 * @param string $html Core link.
	 */
	public static function trashIcon( string $html ): string {
		if ( ! preg_match( '/href="([^"]+)"/', $html, $match ) ) {
			return $html;
		}
		$label = esc_attr__( 'Move to Trash', 'favr-sites' );
		return '<a href="' . $match[1] . '" class="favr-trash submitdelete" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . Icons::svg( 'trash', 16 ) . '</a>';
	}

	/**
	 * Drop Comments and Yoast columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		foreach ( array_keys( $columns ) as $key ) {
			if ( 'comments' === $key || str_starts_with( (string) $key, 'wpseo-' ) ) {
				unset( $columns[ $key ] );
			}
		}
		return $columns;
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'current_screen', array( $this, 'screen' ) );
	}

	/**
	 * On list screens, for Editors only.
	 *
	 * @param \WP_Screen $screen Screen.
	 */
	public function screen( $screen ): void {
		if ( ! $screen || 'edit' !== $screen->base || ! Audience::current() ) {
			return;
		}
		add_filter( 'post_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		add_filter( 'page_row_actions', array( self::class, 'rowActions' ), PHP_INT_MAX );
		// Screen-id filter runs after the post-type ones, so it also catches Yoast's columns.
		add_filter( 'manage_' . $screen->id . '_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_action( 'admin_head', array( $this, 'css' ) );
	}

	/** Bin icon styling. */
	public function css(): void {
		echo '<style>.favr-trash{color:#8c8f94!important;display:inline-flex;vertical-align:middle}.favr-trash:hover,.favr-trash:focus{color:#b32d2e!important}.favr-trash svg{display:block}</style>';
	}
}
```
In `src/Dashboard/Icons.php` add to PATHS: `'trash' => '<path d="M4 7h16M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',`
- [ ] **Step 4:** `composer test && composer lint` → green.
- [ ] **Step 5:** Commit `feat: calmer post lists for Editors (view + bin icon, no comment or SEO columns)`.

---

### Task 4: Menu (allow-list order + "News")

**Files:** Create `src/Editors/Menu.php`; Test `tests/Unit/MenuTest.php`.

**Interfaces:**
- Produces: `Menu::ORDER`, `Menu::arrange( array $slugs ): array{keep: list<string>, remove: list<string>}`, `Menu::relabel( array $menu, array $submenu ): array{0: array, 1: array}`, `Menu::NEWS_LABELS`.

- [ ] **Step 1: Failing test**
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\Menu;

final class MenuTest extends TestCase {

	public function test_keeps_known_entries_in_favr_order_and_removes_the_rest(): void {
		$slugs = array( 'index.php', 'separator1', 'edit.php', 'upload.php', 'edit.php?post_type=page', 'edit-comments.php', 'elementor', 'favr-approvals', 'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event', 'edit.php?post_type=favr_member', 'separator2', 'profile.php', 'tools.php', 'wpseo_workouts' );
		$out   = Menu::arrange( $slugs );
		$this->assertSame( array( 'index.php', 'edit.php?post_type=page', 'edit.php', 'upload.php', 'favr-approvals', 'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event', 'edit.php?post_type=favr_member', 'profile.php', 'tools.php' ), $out['keep'] );
		$this->assertSame( array( 'separator1', 'edit-comments.php', 'elementor', 'separator2', 'wpseo_workouts' ), $out['remove'] );
	}

	public function test_missing_plugins_are_skipped(): void {
		$this->assertSame( array( 'index.php', 'edit.php', 'profile.php' ), Menu::arrange( array( 'profile.php', 'edit.php', 'index.php' ) )['keep'] );
	}

	public function test_posts_become_news_in_the_menu(): void {
		$menu    = array( 5 => array( 'Posts', 'edit_posts', 'edit.php' ), 10 => array( 'Media', 'upload_files', 'upload.php' ) );
		$submenu = array( 'edit.php' => array( 5 => array( 'All Posts', 'edit_posts', 'edit.php' ), 10 => array( 'Add New Post', 'edit_posts', 'post-new.php' ), 15 => array( 'Categories', 'manage_categories', 'edit-tags.php?taxonomy=category' ) ) );
		list( $menu, $submenu ) = Menu::relabel( $menu, $submenu );
		$this->assertSame( 'News', $menu[5][0] );
		$this->assertSame( 'Media', $menu[10][0] );
		$this->assertSame( 'All news', $submenu['edit.php'][5][0] );
		$this->assertSame( 'Add news post', $submenu['edit.php'][10][0] );
		$this->assertSame( 'Categories', $submenu['edit.php'][15][0] );
	}
}
```
- [ ] **Step 2:** run → FAIL.
- [ ] **Step 3: Implement** `src/Editors/Menu.php`:
```php
<?php
/**
 * The Editor menu: Favr's order, Posts called News, nothing else.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * Allow-list: entries not listed here are hidden for Editors, so new plugins can't add clutter.
 * Hidden is not locked: screens stay reachable if the Editor has the capability.
 */
final class Menu {

	public const ORDER = array(
		'index.php',
		'edit.php?post_type=page',
		'edit.php',
		'upload.php',
		'favr-approvals',
		'edit.php?post_type=favr_business',
		'edit.php?post_type=favr_event',
		'edit.php?post_type=favr_member',
		'profile.php',
		'tools.php',
	);

	/**
	 * Which entries stay (in Favr order) and which go.
	 *
	 * @param list<string> $slugs Current top-level slugs.
	 * @return array{keep: list<string>, remove: list<string>}
	 */
	public static function arrange( array $slugs ): array {
		return array(
			'keep'   => array_values( array_intersect( self::ORDER, $slugs ) ),
			'remove' => array_values( array_diff( $slugs, self::ORDER ) ),
		);
	}

	/**
	 * Posts → News in the menu arrays.
	 *
	 * @param array<int, array<int, string>>                $menu    $GLOBALS['menu'].
	 * @param array<string, array<int, array<int, string>>> $submenu $GLOBALS['submenu'].
	 * @return array{0: array, 1: array}
	 */
	public static function relabel( array $menu, array $submenu ): array {
		foreach ( $menu as $i => $item ) {
			if ( 'edit.php' === ( $item[2] ?? '' ) ) {
				$menu[ $i ][0] = __( 'News', 'favr-sites' );
			}
		}
		foreach ( $submenu['edit.php'] ?? array() as $i => $item ) {
			if ( 'edit.php' === ( $item[2] ?? '' ) ) {
				$submenu['edit.php'][ $i ][0] = __( 'All news', 'favr-sites' );
			} elseif ( 'post-new.php' === ( $item[2] ?? '' ) ) {
				$submenu['edit.php'][ $i ][0] = __( 'Add news post', 'favr-sites' );
			}
		}
		return array( $menu, $submenu );
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), PHP_INT_MAX );
		add_filter( 'custom_menu_order', array( $this, 'custom' ) );
		add_filter( 'menu_order', array( $this, 'order' ), PHP_INT_MAX );
		add_filter( 'post_type_labels_post', array( $this, 'labels' ) );
	}

	/** Remove what isn't allowed; rename Posts. */
	public function menu(): void {
		global $menu, $submenu;
		if ( ! Audience::current() || ! is_array( $menu ) ) {
			return;
		}
		foreach ( self::arrange( array_values( array_filter( array_map( static fn( $item ) => (string) ( $item[2] ?? '' ), $menu ) ) ) )['remove'] as $slug ) {
			remove_menu_page( $slug );
		}
		list( $menu, $submenu ) = self::relabel( $menu, is_array( $submenu ) ? $submenu : array() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Turn on custom ordering for Editors.
	 *
	 * @param bool $custom Custom.
	 */
	public function custom( $custom ): bool {
		return Audience::current() ? true : (bool) $custom;
	}

	/**
	 * Favr order for Editors.
	 *
	 * @param array<string> $order Current order.
	 * @return array<string>
	 */
	public function order( $order ) {
		return Audience::current() && is_array( $order ) ? self::arrange( $order )['keep'] : $order;
	}

	/**
	 * Posts are "News" wherever an Editor sees them.
	 *
	 * @param object $labels Labels.
	 * @return object
	 */
	public function labels( $labels ) {
		if ( ! Audience::current() ) {
			return $labels;
		}
		$labels->name           = __( 'News', 'favr-sites' );
		$labels->singular_name  = __( 'News post', 'favr-sites' );
		$labels->menu_name      = __( 'News', 'favr-sites' );
		$labels->name_admin_bar = __( 'News post', 'favr-sites' );
		$labels->add_new        = __( 'Add news post', 'favr-sites' );
		$labels->add_new_item   = __( 'Add news post', 'favr-sites' );
		$labels->edit_item      = __( 'Edit news post', 'favr-sites' );
		$labels->new_item       = __( 'New news post', 'favr-sites' );
		$labels->view_item      = __( 'View news post', 'favr-sites' );
		$labels->all_items      = __( 'All news', 'favr-sites' );
		$labels->search_items   = __( 'Search news', 'favr-sites' );
		$labels->not_found      = __( 'No news posts found.', 'favr-sites' );
		return $labels;
	}
}
```
Before implementing, confirm the real top-level slugs on sermonator-test as the Editor (`wp eval 'wp_set_current_user(19); require ABSPATH."wp-admin/includes/menu.php"; …'` or read `#adminmenu` ids from the Editor's dashboard HTML) and adjust `ORDER` if Members/Directory/Events/Approvals use different slugs (ledger a ruling).
- [ ] **Step 4:** `composer test && composer lint` → green.
- [ ] **Step 5:** Commit `feat: Favr menu order for Editors with Posts renamed News`.

---

### Task 5: Comments off

**Files:** Create `src/Comments/Off.php`; Test `tests/Unit/CommentsOffTest.php`.

**Interfaces:**
- Produces: `Off::endpoints( array $endpoints ): array`, `Off::xmlrpc( array $methods ): array`, `Off::columns( array $columns ): array`.

- [ ] **Step 1: Failing test**
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Comments\Off;

final class CommentsOffTest extends TestCase {

	public function test_comment_routes_are_removed(): void {
		$out = Off::endpoints( array( '/wp/v2/posts' => 1, '/wp/v2/comments' => 2, '/wp/v2/comments/(?P<id>[\d]+)' => 3 ) );
		$this->assertSame( array( '/wp/v2/posts' ), array_keys( $out ) );
	}

	public function test_pingbacks_are_removed_from_xmlrpc(): void {
		$out = Off::xmlrpc( array( 'wp.getPosts' => 'a', 'pingback.ping' => 'b', 'pingback.extensions.getPingbacks' => 'c' ) );
		$this->assertSame( array( 'wp.getPosts' ), array_keys( $out ) );
	}

	public function test_comment_column_is_removed(): void {
		$this->assertSame( array( 'title' ), array_keys( Off::columns( array( 'title' => 'T', 'comments' => 'C' ) ) ) );
	}
}
```
- [ ] **Step 2:** run → FAIL.
- [ ] **Step 3: Implement** `src/Comments/Off.php`:
```php
<?php
/**
 * Comments off, site-wide, for everyone. Nothing is deleted.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Comments;

/**
 * Comments only attract spam on Favr sites: no forms, no admin screens, no ways in.
 */
final class Off {

	/**
	 * Drop the comments REST routes.
	 *
	 * @param array<string, mixed> $endpoints Routes.
	 * @return array<string, mixed>
	 */
	public static function endpoints( array $endpoints ): array {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( (string) $route, '/wp/v2/comments' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Drop pingbacks from XML-RPC.
	 *
	 * @param array<string, mixed> $methods Methods.
	 * @return array<string, mixed>
	 */
	public static function xmlrpc( array $methods ): array {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Drop the Comments column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		unset( $columns['comments'] );
		return $columns;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'comments_open', '__return_false', PHP_INT_MAX );
		add_filter( 'pings_open', '__return_false', PHP_INT_MAX );
		add_filter( 'comments_array', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'get_comments_number', '__return_zero', PHP_INT_MAX );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		add_filter( 'rest_endpoints', array( self::class, 'endpoints' ) );
		add_filter( 'xmlrpc_methods', array( self::class, 'xmlrpc' ) );
		add_action( 'init', array( $this, 'removeSupport' ), PHP_INT_MAX );
		add_action( 'admin_menu', array( $this, 'menu' ), PHP_INT_MAX );
		add_action( 'admin_bar_menu', array( $this, 'adminBar' ), PHP_INT_MAX );
		add_action( 'load-edit-comments.php', array( $this, 'redirect' ) );
		add_action( 'load-options-discussion.php', array( $this, 'redirect' ) );
		add_filter( 'manage_posts_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_filter( 'manage_pages_columns', array( self::class, 'columns' ), PHP_INT_MAX );
		add_filter( 'manage_media_columns', array( self::class, 'columns' ), PHP_INT_MAX );
	}

	/** No comment or trackback support on any post type (removes the Discussion box). */
	public function removeSupport(): void {
		foreach ( get_post_types() as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
		}
	}

	/** No Comments menu or Discussion settings. */
	public function menu(): void {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * No comments bubble in the admin bar.
	 *
	 * @param \WP_Admin_Bar $bar Bar.
	 */
	public function adminBar( $bar ): void {
		$bar->remove_node( 'comments' );
	}

	/** Comment screens go to the dashboard. */
	public function redirect(): void {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
```
- [ ] **Step 4:** `composer test && composer lint` → green.
- [ ] **Step 5:** Commit `feat: comments off site-wide (data kept)`.

---

### Task 6: Wire up, real check, docs, release

**Files:** Modify `src/Plugin.php`, `favr-sites.php` (0.2.0), `README.md`, `CLAUDE.md`.

- [ ] **Step 1:** `src/Plugin.php` boot:
```php
		( new Comments\Off() )->hook();
		( new Editors\PageLock() )->hook();
		( new Editors\BlockList() )->hook();
		( new Editors\Routing() )->hook();

		if ( is_admin() ) {
			( new Admin\SettingsPage() )->hook();
			( new Dashboard\Takeover() )->hook();
			( new Editors\ListTables() )->hook();
			( new Editors\Menu() )->hook();
		}
```
- [ ] **Step 2:** Version 0.2.0 in `favr-sites.php` (header + constant). README: add an "Editor experience" section (pages in Elementor, locked pages, News editor, lists, menu, comments off). CLAUDE.md: add the `Editors\*` and `Comments\Off` bullets.
- [ ] **Step 3:** `composer test && composer lint` → green; commit `feat: wire the editor experience; 0.2.0`.
- [ ] **Step 4: Real check on sermonator-test** (Editor 19, Admin 1; cookies as in the dashboard plan):
  - `curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "…/wp-admin/post.php?post=<elementor page>&action=edit"` as Editor → `302 …action=elementor`; as Admin → `200`.
  - `…/wp-admin/post-new.php?post_type=page` as Editor → `302 …action=elementor_new_post…` (read the header only; don't follow).
  - A Members page (login/account; not Elementor-built): Pages list shows "Managed by Favr", its row has no edit link/checkbox; `post.php?post=<id>&action=edit` → 403-style "not allowed" page; `…&action=elementor` → not allowed.
  - `edit.php?post_type=page` HTML: row actions contain only `view` and `favr-trash`; no `comments`/`wpseo-` columns.
  - `post.php?post=<a post>&action=edit` as Editor: page source has `allowedBlockTypes` with the short list; no "Edit with Elementor".
  - Editor dashboard HTML: `#adminmenu` top-level order matches ORDER; label "News".
  - Front end: a post has no comment form; `curl -X POST …/wp-json/wp/v2/comments` → 404 `rest_no_route`; `…/wp-admin/edit-comments.php` → redirect to dashboard.
  - Screenshot the Editor's Pages list (desktop).
- [ ] **Step 5:** Fix anything found (TDD for logic), commit.
