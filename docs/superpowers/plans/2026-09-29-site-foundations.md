# Site Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every Favr site always has a Home (front page), News (posts page), and a site-wide Elementor Header and Footer; Favr adopts, repairs or creates them, and Editors can edit but not delete, unpublish or retarget them.

**Architecture:** A pure planner (`Site\Foundations::plan()`) turns a snapshot of the site into decisions per role (home, news, header, footer). `Site\Setup` gathers the snapshot and applies the decisions on `admin_init` for Administrators, using Elementor's document API and Pro's conditions manager. `Site\Starter` (pure) builds the starter Elementor elements. `Site\Protect` enforces the Editor locks with capabilities and save filters. The Menus screen becomes "Header & Footer" with design buttons.

**Tech Stack:** PHP 8.1+, WordPress 6.7+, Elementor 4.3 + Elementor Pro 4.3 (Theme Builder), PHPUnit + Brain Monkey, WPCS.

**Spec:** `docs/superpowers/specs/2026-09-29-site-foundations-design.md`

## Global Constraints

- Runs on `admin_init` for `manage_options` users only, never during AJAX; changes nothing when the site is already in order.
- Home/News source of truth: Settings → Reading (`show_on_front`, `page_on_front`, `page_for_posts`). Template ids: option `favr_sites_foundations` = `{header: int, footer: int}`.
- Header/Footer only when Elementor Pro's Theme Builder is active; main Header/Footer conditions exactly `['include/general']`, status `publish`.
- Editor locks apply to `Audience` users only (the Editor role without `manage_options`); Administrators are never locked.
- Starter content uses Kit globals only (no hardcoded colours/fonts); menus from `Menus\Slots::current()`.
- Screen slug stays `favr-menus`; its label becomes "Header & Footer".
- `composer test` green, `composer lint` 0 errors. Version 0.8.0.

## Review Focus

- A settled site (GOAABA: front 111, posts 182, header 53 and footer 55 published at Entire Site) → the plan is empty and Setup writes nothing (test in Task 1; real check in Task 6).
- An Editor's normal Publish of the Header in Elementor → conditions stay `include/general` and status `publish` (real check in Task 6).
- An Administrator's extra, more specific header (e.g. `include/singular/page`) → never adopted as the main one, never modified (test in Task 1).
- Front page and posts page set to the same page → Home kept, News replaced (test in Task 1).
- Elementor Pro inactive → no template decisions and no fatal error (test in Task 1; Setup guards with `class_exists`).

---

### Task 1: `Site\Foundations` (pure planner)

**Files:** Create `src/Site/Foundations.php`; Test `tests/Unit/FoundationsTest.php`.

**Interfaces — Produces:**
- `Foundations::OPTION = 'favr_sites_foundations'`, `Foundations::ROLES = ['header', 'footer']`, `Foundations::ENTIRE_SITE = 'include/general'`.
- `Foundations::plan( array $state ): array` where `$state` is
  `{show_on_front: string, page_on_front: int, page_for_posts: int, pages: array<int, {status, title, slug}>, pro: bool, recorded: array{header?: int, footer?: int}, templates: array<int, {type, status, conditions: list<string>, date: string}>}`
  and the result is
  `{home: {id: int, publish: bool}, news: {id: int, publish: bool}, header: ?{id: int, restore: bool, conditions: bool}, footer: ?{…}}` — `id` 0 means create; `publish`/`restore` mean make it published (untrash first if trashed); `conditions` means reset to Entire Site; `null` means no Pro.
- `Foundations::settled( array $plan, array $state ): bool` — true when applying the plan would change nothing.

- [ ] **Step 1: Failing tests**

```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Site\Foundations;

final class FoundationsTest extends TestCase {

	private static function goaaba(): array {
		return array(
			'show_on_front'  => 'page',
			'page_on_front'  => 111,
			'page_for_posts' => 182,
			'pages'          => array(
				111 => array( 'status' => 'publish', 'title' => 'Home', 'slug' => 'home' ),
				182 => array( 'status' => 'publish', 'title' => 'News', 'slug' => 'news' ),
			),
			'pro'            => true,
			'recorded'       => array(),
			'templates'      => array(
				53  => array( 'type' => 'header', 'status' => 'publish', 'conditions' => array( 'include/general' ), 'date' => '2026-09-24 03:15:50' ),
				55  => array( 'type' => 'footer', 'status' => 'publish', 'conditions' => array( 'include/general' ), 'date' => '2026-09-24 18:48:32' ),
				400 => array( 'type' => 'header', 'status' => 'publish', 'conditions' => array( 'include/singular/page' ), 'date' => '2026-09-25 10:00:00' ),
			),
		);
	}

	public function test_a_settled_site_is_adopted_as_is(): void {
		$state = self::goaaba();
		$plan  = Foundations::plan( $state );
		$this->assertSame( array( 'id' => 111, 'publish' => false ), $plan['home'] );
		$this->assertSame( array( 'id' => 182, 'publish' => false ), $plan['news'] );
		$this->assertSame( array( 'id' => 53, 'restore' => false, 'conditions' => false ), $plan['header'] );
		$this->assertSame( array( 'id' => 55, 'restore' => false, 'conditions' => false ), $plan['footer'] );
		$this->assertFalse( Foundations::settled( $plan, $state ) ); // Not recorded yet.
		$state['recorded'] = array( 'header' => 53, 'footer' => 55 );
		$this->assertTrue( Foundations::settled( Foundations::plan( $state ), $state ) );
	}

	public function test_a_fresh_site_gets_everything_created(): void {
		$plan = Foundations::plan( array( 'show_on_front' => 'posts', 'page_on_front' => 0, 'page_for_posts' => 0, 'pages' => array(), 'pro' => true, 'recorded' => array(), 'templates' => array() ) );
		$this->assertSame( 0, $plan['home']['id'] );
		$this->assertSame( 0, $plan['news']['id'] );
		$this->assertSame( 0, $plan['header']['id'] );
		$this->assertSame( 0, $plan['footer']['id'] );
	}

	public function test_pages_are_adopted_by_name_and_trashed_or_shared_ones_are_replaced(): void {
		$plan = Foundations::plan(
			array(
				'show_on_front'  => 'page',
				'page_on_front'  => 5,
				'page_for_posts' => 5,
				'pages'          => array(
					5  => array( 'status' => 'draft', 'title' => 'Welcome', 'slug' => 'welcome' ),
					8  => array( 'status' => 'publish', 'title' => 'news', 'slug' => 'latest' ),
					9  => array( 'status' => 'trash', 'title' => 'News', 'slug' => 'news' ),
				),
				'pro'            => false,
				'recorded'       => array(),
				'templates'      => array(),
			)
		);
		$this->assertSame( array( 'id' => 5, 'publish' => true ), $plan['home'] );  // Adopted, republished.
		$this->assertSame( array( 'id' => 8, 'publish' => false ), $plan['news'] ); // Not the Home page; by title.
		$this->assertNull( $plan['header'] );                                         // No Pro.
		$trashed = Foundations::plan( array( 'show_on_front' => 'page', 'page_on_front' => 9, 'page_for_posts' => 0, 'pages' => array( 9 => array( 'status' => 'trash', 'title' => 'Home', 'slug' => 'home' ) ), 'pro' => false, 'recorded' => array(), 'templates' => array() ) );
		$this->assertSame( 0, $trashed['home']['id'] );
	}

	public function test_recorded_templates_are_repaired_and_the_newest_entire_site_one_is_adopted(): void {
		$state              = self::goaaba();
		$state['recorded']  = array( 'header' => 53, 'footer' => 99 ); // 99 was deleted.
		$state['templates'][53]['status']     = 'trash';
		$state['templates'][53]['conditions'] = array( 'include/singular' );
		$state['templates'][60]               = array( 'type' => 'footer', 'status' => 'publish', 'conditions' => array( 'include/general', 'exclude/singular/page/7' ), 'date' => '2026-09-26 09:00:00' );
		$plan               = Foundations::plan( $state );
		$this->assertSame( array( 'id' => 53, 'restore' => true, 'conditions' => true ), $plan['header'] );
		$this->assertSame( array( 'id' => 60, 'restore' => false, 'conditions' => true ), $plan['footer'] ); // Newest Entire Site footer.
	}
}
```

- [ ] **Step 2:** `composer test -- --filter FoundationsTest` → FAIL (class not found).
- [ ] **Step 3: Implement**

```php
<?php
/**
 * What every Favr site must have, and what to do about it (pure).
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

/**
 * Home (front page), News (posts page), and the site-wide Header and Footer templates. From a
 * snapshot of the site, decides per role: adopt this id (0 = create), publish/restore it, reset its
 * conditions. Setup applies the decisions.
 */
final class Foundations {

	public const OPTION      = 'favr_sites_foundations';
	public const ROLES       = array( 'header', 'footer' );
	public const ENTIRE_SITE = 'include/general';

	/**
	 * Decisions for the site.
	 *
	 * @param array<string, mixed> $state Snapshot (see Setup::state()).
	 * @return array{home: array{id: int, publish: bool}, news: array{id: int, publish: bool}, header: ?array{id: int, restore: bool, conditions: bool}, footer: ?array{id: int, restore: bool, conditions: bool}}
	 */
	public static function plan( array $state ): array {
		$pages = (array) $state['pages'];
		$home  = self::page( (int) $state['page_on_front'], $pages, 'home', 0 );
		$plan  = array(
			'home'   => $home,
			'news'   => self::page( (int) $state['page_for_posts'], $pages, 'news', $home['id'] ),
			'header' => null,
			'footer' => null,
		);
		if ( $state['pro'] ) {
			foreach ( self::ROLES as $role ) {
				$plan[ $role ] = self::template( $role, (int) ( $state['recorded'][ $role ] ?? 0 ), (array) $state['templates'] );
			}
		}
		return $plan;
	}

	/**
	 * Would applying the plan change nothing?
	 *
	 * @param array<string, mixed> $plan  From plan().
	 * @param array<string, mixed> $state Snapshot.
	 */
	public static function settled( array $plan, array $state ): bool {
		if ( 'page' !== $state['show_on_front'] || $plan['home']['id'] !== (int) $state['page_on_front'] || $plan['news']['id'] !== (int) $state['page_for_posts'] ) {
			return false;
		}
		if ( ! $plan['home']['id'] || ! $plan['news']['id'] || $plan['home']['publish'] || $plan['news']['publish'] ) {
			return false;
		}
		foreach ( self::ROLES as $role ) {
			$decision = $plan[ $role ];
			if ( null !== $decision && ( ! $decision['id'] || $decision['restore'] || $decision['conditions'] || $decision['id'] !== (int) ( $state['recorded'][ $role ] ?? 0 ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The current page if usable, else a published page named like the role, else 0 (create).
	 *
	 * @param int                                $current Current Reading setting.
	 * @param array<int, array<string, string>> $pages   Known pages.
	 * @param string                             $name    "home" or "news".
	 * @param int                                $not     A page it can't be (Home, for News).
	 * @return array{id: int, publish: bool}
	 */
	private static function page( int $current, array $pages, string $name, int $not ): array {
		if ( $current && $current !== $not && isset( $pages[ $current ] ) && 'trash' !== $pages[ $current ]['status'] ) {
			return array(
				'id'      => $current,
				'publish' => 'publish' !== $pages[ $current ]['status'],
			);
		}
		$best = 0;
		foreach ( $pages as $id => $page ) {
			$named = strtolower( trim( (string) $page['title'] ) ) === $name || $name === $page['slug'];
			if ( $named && 'publish' === $page['status'] && (int) $id !== $not && (int) $id > $best ) {
				$best = (int) $id;
			}
		}
		return array(
			'id'      => $best,
			'publish' => false,
		);
	}

	/**
	 * The recorded template if it's still one of this role, else the newest published Entire Site
	 * one, else 0 (create).
	 *
	 * @param string                            $role      header|footer.
	 * @param int                               $recorded  Recorded id.
	 * @param array<int, array<string, mixed>> $templates Theme templates.
	 * @return array{id: int, restore: bool, conditions: bool}
	 */
	private static function template( string $role, int $recorded, array $templates ): array {
		if ( $recorded && isset( $templates[ $recorded ] ) && $role === $templates[ $recorded ]['type'] ) {
			return self::repair( $recorded, $templates[ $recorded ] );
		}
		$best = 0;
		foreach ( $templates as $id => $template ) {
			if ( $role !== $template['type'] || 'publish' !== $template['status'] || ! in_array( self::ENTIRE_SITE, (array) $template['conditions'], true ) ) {
				continue;
			}
			if ( ! $best || array( $template['date'], (int) $id ) > array( $templates[ $best ]['date'], $best ) ) {
				$best = (int) $id;
			}
		}
		return $best ? self::repair( $best, $templates[ $best ] ) : array(
			'id'         => 0,
			'restore'    => false,
			'conditions' => false,
		);
	}

	/**
	 * What an adopted template needs.
	 *
	 * @param int                  $id       Id.
	 * @param array<string, mixed> $template Template.
	 * @return array{id: int, restore: bool, conditions: bool}
	 */
	private static function repair( int $id, array $template ): array {
		return array(
			'id'         => $id,
			'restore'    => 'publish' !== $template['status'],
			'conditions' => array( self::ENTIRE_SITE ) !== array_values( (array) $template['conditions'] ),
		);
	}
}
```

- [ ] **Step 4:** tests → PASS; `composer lint` clean.
- [ ] **Step 5:** commit `feat: site foundations planner (Home, News, Header, Footer)`.

### Task 2: `Site\Starter` (pure starter content)

**Files:** Create `src/Site/Starter.php`; Test `tests/Unit/StarterTest.php`.

**Interfaces — Produces:** `Starter::header( bool $has_logo, string $menu ): array`, `Starter::footer( string $menu, string $site_name, string $year_tag, int $year ): array`, `Starter::home( string $site_name, string $tagline ): array` — each a list of Elementor elements (containers with widgets). `$menu` is a nav menu slug ('' = let Elementor pick); `$year_tag` is an `[elementor-tag …]` string ('' = static year).

- [ ] **Step 1: Failing tests**

```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Site\Starter;

final class StarterTest extends TestCase {

	private static function widgets( array $elements ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( 'widget' === $element['elType'] ) {
				$out[] = $element;
			}
			$out = array_merge( $out, self::widgets( $element['elements'] ) );
		}
		return $out;
	}

	public function test_header_is_brand_plus_the_header_menu(): void {
		$with_logo = self::widgets( Starter::header( true, 'header' ) );
		$this->assertSame( array( 'theme-site-logo', 'nav-menu' ), array_column( $with_logo, 'widgetType' ) );
		$this->assertSame( 'header', $with_logo[1]['settings']['menu'] );
		$without = self::widgets( Starter::header( false, '' ) );
		$this->assertSame( 'theme-site-title', $without[0]['widgetType'] );
		$this->assertArrayNotHasKey( 'menu', $without[1]['settings'] );
	}

	public function test_footer_is_the_footer_menu_and_a_copyright_line(): void {
		$widgets = self::widgets( Starter::footer( 'footer', 'GOAABA', '[elementor-tag id="x" name="current-date-time" settings="%7B%7D"]', 2026 ) );
		$this->assertSame( array( 'nav-menu', 'heading' ), array_column( $widgets, 'widgetType' ) );
		$this->assertSame( 'footer', $widgets[0]['settings']['menu'] );
		$this->assertStringContainsString( 'current-date-time', $widgets[1]['settings']['__dynamic__']['title'] );
		$static = self::widgets( Starter::footer( 'footer', 'GOAABA', '', 2026 ) );
		$this->assertSame( '© 2026 GOAABA', $static[1]['settings']['title'] );
	}

	public function test_home_says_the_site_name_and_ids_are_unique(): void {
		$home    = Starter::home( 'GOAABA', 'Greater Orlando Asian American Bar Association' );
		$widgets = self::widgets( $home );
		$this->assertSame( 'GOAABA', $widgets[0]['settings']['title'] );
		$this->assertSame( 'h1', $widgets[0]['settings']['header_size'] );
		$ids = array_merge( array_column( $home, 'id' ), array_column( $widgets, 'id' ) );
		$this->assertSame( $ids, array_unique( $ids ) );
		$this->assertCount( 1, self::widgets( Starter::home( 'GOAABA', '' ) ) ); // No empty tagline.
	}
}
```

- [ ] **Step 2:** `composer test -- --filter StarterTest` → FAIL (class not found).
- [ ] **Step 3: Implement**

```php
<?php
/**
 * Starter Header, Footer and Home content (pure).
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

/**
 * Just enough to work the moment it's created; Favr designs each site from here. No colours or
 * fonts: widgets inherit the Site Kit's globals.
 */
final class Starter {

	/**
	 * Site logo (or title) on the left, the Header menu on the right.
	 *
	 * @param bool   $has_logo A custom logo is set.
	 * @param string $menu     Header menu slug ('' = Elementor's first menu).
	 * @return list<array<string, mixed>>
	 */
	public static function header( bool $has_logo, string $menu ): array {
		$brand = $has_logo ? self::widget( 'fvh0002', 'theme-site-logo', array( 'align' => 'left' ) ) : self::widget( 'fvh0002', 'theme-site-title', array( 'header_size' => 'p' ) );
		return array(
			self::container(
				'fvh0001',
				array(
					'flex_direction'       => 'row',
					'flex_justify_content' => 'space-between',
					'flex_align_items'     => 'center',
					'flex_wrap'            => 'wrap',
				),
				array( $brand, self::widget( 'fvh0003', 'nav-menu', self::menu( $menu, 'end' ) ) )
			),
		);
	}

	/**
	 * The Footer menu, then "© {year} {site}" (year from Elementor's current-date tag).
	 *
	 * @param string $menu      Footer menu slug.
	 * @param string $site_name Site name.
	 * @param string $year_tag  Dynamic tag text for the year line ('' = static year).
	 * @param int    $year      Current year (static fallback).
	 * @return list<array<string, mixed>>
	 */
	public static function footer( string $menu, string $site_name, string $year_tag, int $year ): array {
		$line = array(
			'title'       => '© ' . $year . ' ' . $site_name,
			'header_size' => 'p',
			'align'       => 'center',
		);
		if ( '' !== $year_tag ) {
			$line['__dynamic__'] = array( 'title' => $year_tag );
		}
		return array(
			self::container(
				'fvf0001',
				array(
					'flex_direction'   => 'column',
					'flex_align_items' => 'center',
				),
				array(
					self::widget( 'fvf0002', 'nav-menu', self::menu( $menu, 'center' ) + array( 'toggle' => '' ) ),
					self::widget( 'fvf0003', 'heading', $line ),
				)
			),
		);
	}

	/**
	 * Site name and tagline.
	 *
	 * @param string $site_name Site name.
	 * @param string $tagline   Tagline ('' = none).
	 * @return list<array<string, mixed>>
	 */
	public static function home( string $site_name, string $tagline ): array {
		$widgets = array(
			self::widget(
				'fvp0002',
				'heading',
				array(
					'title'       => $site_name,
					'header_size' => 'h1',
					'align'       => 'center',
				)
			),
		);
		if ( '' !== $tagline ) {
			$widgets[] = self::widget( 'fvp0003', 'text-editor', array( 'editor' => '<p style="text-align:center">' . esc_html( $tagline ) . '</p>' ) );
		}
		return array(
			self::container(
				'fvp0001',
				array(
					'flex_direction'   => 'column',
					'flex_align_items' => 'center',
					'min_height'       => array(
						'unit' => 'vh',
						'size' => 50,
					),
					'flex_justify_content' => 'center',
				),
				$widgets
			),
		);
	}

	/**
	 * Nav Menu settings.
	 *
	 * @param string $menu  Slug.
	 * @param string $align start|center|end.
	 * @return array<string, string>
	 */
	private static function menu( string $menu, string $align ): array {
		$settings = array(
			'layout'      => 'horizontal',
			'align_items' => $align,
		);
		if ( '' !== $menu ) {
			$settings['menu'] = $menu;
		}
		return $settings;
	}

	/**
	 * A container.
	 *
	 * @param string                     $id       Element id.
	 * @param array<string, mixed>       $settings Settings.
	 * @param list<array<string, mixed>> $elements Children.
	 * @return array<string, mixed>
	 */
	private static function container( string $id, array $settings, array $elements ): array {
		return array(
			'id'       => $id,
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => $settings,
			'elements' => $elements,
		);
	}

	/**
	 * A widget.
	 *
	 * @param string               $id       Element id.
	 * @param string               $type     Widget type.
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	private static function widget( string $id, string $type, array $settings ): array {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}
}
```

(`esc_html` is stubbed in the test base; if not, add `Functions\when( 'esc_html' )->returnArg()` to the test's setUp.)

- [ ] **Step 4:** tests → PASS; lint clean.
- [ ] **Step 5:** commit `feat: starter Header, Footer and Home content`.

### Task 3: `Site\Setup` (gather and apply)

**Files:** Create `src/Site/Setup.php`; Modify `src/Plugin.php` (hook it inside `is_admin()` after `Menus\Slots`).

**Interfaces — Consumes:** `Foundations::plan/settled/OPTION/ROLES`, `Starter::*`, `Menus\Slots::current()`.
**Produces:** `Setup::hook()` (admin_init priority 20), `Setup::state(): array`, `Setup::run(): void`.

- [ ] **Step 1: Implement** (WordPress/Elementor glue; covered by the real check, the rules are in Task 1)

```php
<?php
/**
 * Keeps every Favr site's foundations in place.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

use FavrSites\Menus\Slots;

/**
 * On an Administrator's wp-admin visit: snapshot the site, plan, and apply only what's missing or
 * broken (create Home/News/Header/Footer, set Reading, republish, reset conditions, record ids).
 */
final class Setup {

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_init', array( $this, 'maybeRun' ), 20 ); // After Slots::ensureDefaults (10).
	}

	/** Administrators only, never during AJAX. */
	public function maybeRun(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::run();
	}

	/** Plan and apply. */
	public static function run(): void {
		$state = self::state();
		$plan  = Foundations::plan( $state );
		if ( Foundations::settled( $plan, $state ) ) {
			return;
		}

		$home = $plan['home']['id'] ? $plan['home']['id'] : self::createHome();
		$news = $plan['news']['id'] ? $plan['news']['id'] : self::createPage( __( 'News', 'favr-sites' ) );
		foreach ( array( $home => $plan['home']['publish'], $news => $plan['news']['publish'] ) as $id => $publish ) {
			if ( $id && $publish ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			}
		}
		if ( $home && $news ) {
			self::setOption( 'show_on_front', 'page' );
			self::setOption( 'page_on_front', $home );
			self::setOption( 'page_for_posts', $news );
		}

		$recorded = (array) get_option( Foundations::OPTION, array() );
		foreach ( Foundations::ROLES as $role ) {
			$decision = $plan[ $role ];
			if ( null === $decision || ! self::pro() ) {
				continue;
			}
			$id = $decision['id'] ? $decision['id'] : self::createTemplate( $role );
			if ( ! $id ) {
				continue;
			}
			if ( $decision['restore'] ) {
				if ( 'trash' === get_post_status( $id ) ) {
					wp_untrash_post( $id );
				}
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			}
			if ( $decision['conditions'] || ! $decision['id'] ) {
				self::entireSite( $id );
			}
			$recorded[ $role ] = $id;
		}
		self::setOption( Foundations::OPTION, $recorded );
	}

	/**
	 * Snapshot for Foundations::plan().
	 *
	 * @return array<string, mixed>
	 */
	public static function state(): array {
		global $wpdb;
		$front = (int) get_option( 'page_on_front' );
		$posts = (int) get_option( 'page_for_posts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only, two small queries.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts}
				WHERE post_type = 'page' AND post_status NOT IN ('auto-draft', 'inherit')
				AND ( ID IN (%d, %d) OR post_name IN ('home', 'news') OR LOWER(post_title) IN ('home', 'news') )",
				$front,
				$posts
			)
		);
		$pages = array();
		foreach ( $rows as $row ) {
			$pages[ (int) $row->ID ] = array(
				'status' => (string) $row->post_status,
				'title'  => (string) $row->post_title,
				'slug'   => (string) $row->post_name,
			);
		}

		$templates = array();
		if ( self::pro() ) {
			$rows = $wpdb->get_results(
				"SELECT p.ID, p.post_status, p.post_date, m.meta_value AS type FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_template_type' AND m.meta_value IN ('header', 'footer')
				WHERE p.post_type = 'elementor_library' AND p.post_status NOT IN ('auto-draft', 'inherit')"
			);
			foreach ( $rows as $row ) {
				$templates[ (int) $row->ID ] = array(
					'type'       => (string) $row->type,
					'status'     => (string) $row->post_status,
					'conditions' => array_values( (array) get_post_meta( (int) $row->ID, '_elementor_conditions', true ) ),
					'date'       => (string) $row->post_date,
				);
			}
		}
		// phpcs:enable

		return array(
			'show_on_front'  => (string) get_option( 'show_on_front' ),
			'page_on_front'  => $front,
			'page_for_posts' => $posts,
			'pages'          => $pages,
			'pro'            => self::pro(),
			'recorded'       => array_map( 'intval', (array) get_option( Foundations::OPTION, array() ) ),
			'templates'      => $templates,
		);
	}

	/** Elementor Pro's Theme Builder is available. */
	private static function pro(): bool {
		return did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' );
	}

	/** A new Home: an Elementor page (full width) when Elementor runs, else a plain page. */
	private static function createHome(): int {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return self::createPage( __( 'Home', 'favr-sites' ) );
		}
		$document = \Elementor\Plugin::$instance->documents->create(
			'wp-page',
			array(
				'post_title'  => __( 'Home', 'favr-sites' ),
				'post_status' => 'publish',
			),
			array( '_wp_page_template' => 'elementor_header_footer' )
		);
		if ( is_wp_error( $document ) ) {
			return 0;
		}
		$document->save( array( 'elements' => Starter::home( get_bloginfo( 'name' ), get_bloginfo( 'description' ) ) ) );
		return (int) $document->get_main_id();
	}

	/**
	 * A plain published page.
	 *
	 * @param string $title Title.
	 */
	private static function createPage( string $title ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * A starter Header or Footer template.
	 *
	 * @param string $role header|footer.
	 */
	private static function createTemplate( string $role ): int {
		$document = \Elementor\Plugin::$instance->documents->create(
			$role,
			array(
				'post_title'  => 'footer' === $role ? __( 'Footer', 'favr-sites' ) : __( 'Header', 'favr-sites' ),
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $document ) ) {
			return 0;
		}
		$slots    = Slots::current();
		$slug     = static fn( ?int $id ): string => ( $id && ( $menu = wp_get_nav_menu_object( $id ) ) ) ? (string) $menu->slug : ''; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
		$site     = get_bloginfo( 'name' );
		$elements = 'footer' === $role
			? Starter::footer( $slug( $slots['footer'] ), $site, self::yearTag( $site ), (int) wp_date( 'Y' ) )
			: Starter::header( (bool) get_theme_mod( 'custom_logo' ), $slug( $slots['header'] ) );
		$document->save( array( 'elements' => $elements ) );
		return (int) $document->get_main_id();
	}

	/**
	 * "© {year} {site}" as Elementor's current-date tag (so the year stays current).
	 *
	 * @param string $site Site name.
	 */
	private static function yearTag( string $site ): string {
		$tags = \Elementor\Plugin::$instance->dynamic_tags ?? null;
		if ( ! $tags || ! method_exists( $tags, 'tag_data_to_tag_text' ) ) {
			return '';
		}
		return (string) $tags->tag_data_to_tag_text(
			substr( md5( 'favr-year' ), 0, 7 ),
			'current-date-time',
			array(
				'date_format'   => 'custom',
				'custom_format' => 'Y',
				'before'        => '© ',
				'after'         => ' ' . $site,
			)
		);
	}

	/**
	 * Display conditions: Entire Site only.
	 *
	 * @param int $id Template.
	 */
	private static function entireSite( int $id ): void {
		\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions(
			$id,
			array(
				array(
					'type' => 'include',
					'name' => 'general',
				),
			)
		);
	}

	/**
	 * Update an option only when it differs.
	 *
	 * @param string $key   Option.
	 * @param mixed  $value Value.
	 */
	private static function setOption( string $key, $value ): void {
		if ( get_option( $key ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- options come back as strings.
			update_option( $key, $value );
		}
	}
}
```

Plugin.php, inside `is_admin()` after `( new Menus\Slots() )->hook();`: `( new Site\Setup() )->hook();`

Activation (spec: "and on plugin activation"): WordPress redirects to an admin page right after activating, where `admin_init` runs this check, so no separate activation hook is added.

- [ ] **Step 2: Smoke check** on sermonator-test (Elementor Pro 4.3.0 installed; site has no front page, no templates): load `wp-admin/` as the Administrator (user 1) with the cookie recipe, then `wp option get show_on_front` → `page`; `page_on_front`/`page_for_posts` → Home/News ids; `wp option get favr_sites_foundations --format=json` → header/footer ids; `wp post list --post_type=elementor_library --fields=ID,post_title,post_status` shows Header and Footer; `_elementor_conditions` = `include/general`. Load wp-admin again → no new posts (`wp post list --post_type=page,elementor_library --format=count` unchanged).
- [ ] **Step 3:** tests + lint; commit `feat: Favr keeps Home, News, Header and Footer in place`.

### Task 4: `Site\Protect` (Editor locks)

**Files:** Create `src/Site/Protect.php`; Modify `src/Editors/PageLock.php` (News locked), `src/Plugin.php`; Test `tests/Unit/ProtectTest.php`, `tests/Unit/PageLockTest.php`.

**Interfaces — Produces:** `Protect::ids(): array<string, int>` (home/news/header/footer, zeros removed), `Protect::role( int $post_id, array $ids ): ?string`, `Protect::refusesCap( string $cap, int $post_id, array $ids ): bool`, `Protect::status( string $status, int $post_id, array $ids ): string`, `Protect::refusesMeta( string $key ): bool`; `PageLock::isLocked( string $post_type, string $status, bool $built_with_elementor, bool $elementor_active, bool $posts_page = false ): bool`.

- [ ] **Step 1: Failing tests**

```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Site\Protect;

final class ProtectTest extends TestCase {

	private const IDS = array( 'home' => 111, 'news' => 182, 'header' => 53, 'footer' => 55 );

	public function test_the_four_can_not_be_deleted(): void {
		foreach ( self::IDS as $id ) {
			$this->assertTrue( Protect::refusesCap( 'delete_post', $id, self::IDS ) );
			$this->assertTrue( Protect::refusesCap( 'delete_page', $id, self::IDS ) );
			$this->assertFalse( Protect::refusesCap( 'edit_post', $id, self::IDS ) );
		}
		$this->assertFalse( Protect::refusesCap( 'delete_post', 999, self::IDS ) );
	}

	public function test_the_four_stay_published(): void {
		foreach ( array( 'draft', 'pending', 'private', 'trash', 'future' ) as $status ) {
			$this->assertSame( 'publish', Protect::status( $status, 53, self::IDS ) );
		}
		$this->assertSame( 'draft', Protect::status( 'draft', 999, self::IDS ) );
	}

	public function test_roles_and_conditions(): void {
		$this->assertSame( 'header', Protect::role( 53, self::IDS ) );
		$this->assertNull( Protect::role( 7, self::IDS ) );
		$this->assertTrue( Protect::refusesMeta( '_elementor_conditions' ) );
		$this->assertFalse( Protect::refusesMeta( '_elementor_data' ) );
	}
}
```

Add to `PageLockTest::test_only_existing_non_elementor_pages_are_locked_when_elementor_runs`:

```php
		$this->assertTrue( PageLock::isLocked( 'page', 'publish', true, true, true ) );    // The News (posts) page, even if built with Elementor.
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', true, false, true ) );  // …unless Elementor is off.
```

- [ ] **Step 2:** `composer test -- --filter 'ProtectTest|PageLockTest'` → FAIL.
- [ ] **Step 3: Implement** `src/Site/Protect.php`:

```php
<?php
/**
 * Editors can't break the site's foundations.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

use FavrSites\Dashboard\Audience;

/**
 * For Editors: Home, News, Header and Footer can't be deleted or taken off "published", and no
 * Elementor template's display conditions can change. Administrators are unaffected.
 */
final class Protect {

	private const DELETE_CAPS = array( 'delete_post', 'delete_page' );
	private const CONDITIONS  = '_elementor_conditions';

	/**
	 * The protected ids by role.
	 *
	 * @return array<string, int>
	 */
	public static function ids(): array {
		$recorded = (array) get_option( Foundations::OPTION, array() );
		return array_filter(
			array(
				'home'   => 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0,
				'news'   => (int) get_option( 'page_for_posts' ),
				'header' => (int) ( $recorded['header'] ?? 0 ),
				'footer' => (int) ( $recorded['footer'] ?? 0 ),
			)
		);
	}

	/**
	 * Which role an id plays.
	 *
	 * @param int                $post_id Post.
	 * @param array<string, int> $ids     Protected ids.
	 */
	public static function role( int $post_id, array $ids ): ?string {
		$role = $post_id ? array_search( $post_id, $ids, true ) : false;
		return is_string( $role ) ? $role : null;
	}

	/**
	 * Is this capability refused on this post?
	 *
	 * @param string             $cap     Meta cap.
	 * @param int                $post_id Post.
	 * @param array<string, int> $ids     Protected ids.
	 */
	public static function refusesCap( string $cap, int $post_id, array $ids ): bool {
		return in_array( $cap, self::DELETE_CAPS, true ) && null !== self::role( $post_id, $ids );
	}

	/**
	 * The status a save may set.
	 *
	 * @param string             $status  Requested status.
	 * @param int                $post_id Post.
	 * @param array<string, int> $ids     Protected ids.
	 */
	public static function status( string $status, int $post_id, array $ids ): string {
		return null !== self::role( $post_id, $ids ) ? 'publish' : $status;
	}

	/**
	 * Is this meta off-limits to Editors?
	 *
	 * @param string $key Meta key.
	 */
	public static function refusesMeta( string $key ): bool {
		return self::CONDITIONS === $key;
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'map_meta_cap', array( $this, 'caps' ), 10, 4 );
		add_filter( 'wp_insert_post_data', array( $this, 'keepPublished' ), PHP_INT_MAX, 2 );
		foreach ( array( 'add', 'update', 'delete' ) as $op ) {
			add_filter( $op . '_post_metadata', array( $this, 'meta' ), 10, 3 );
		}
	}

	/**
	 * Refuse deleting the four.
	 *
	 * @param array<string> $caps    Required caps.
	 * @param string        $cap     Checked cap.
	 * @param int           $user_id User.
	 * @param array<mixed>  $args    [ post ].
	 * @return array<string>
	 */
	public function caps( $caps, $cap, $user_id, $args ) {
		if ( empty( $args[0] ) || ! in_array( $cap, self::DELETE_CAPS, true ) ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post || ! self::refusesCap( (string) $cap, (int) $post->ID, self::ids() ) ) {
			return $caps;
		}
		$user = get_userdata( (int) $user_id );
		return $user && Audience::includes( $user ) ? array( 'do_not_allow' ) : $caps;
	}

	/**
	 * An Editor's save keeps the four published.
	 *
	 * @param array<string, mixed> $data    Post data.
	 * @param array<string, mixed> $postarr Raw input.
	 * @return array<string, mixed>
	 */
	public function keepPublished( $data, $postarr ) {
		if ( ! empty( $postarr['ID'] ) && isset( $data['post_status'] ) && Audience::current() ) {
			$data['post_status'] = self::status( (string) $data['post_status'], (int) $postarr['ID'], self::ids() );
		}
		return $data;
	}

	/**
	 * Editors can't write display conditions (returning false short-circuits the write).
	 *
	 * @param mixed  $check     Null to continue.
	 * @param int    $object_id Post.
	 * @param string $meta_key  Key.
	 * @return mixed
	 */
	public function meta( $check, $object_id, $meta_key ) {
		return self::refusesMeta( (string) $meta_key ) && Audience::current() ? false : $check;
	}
}
```

`PageLock::isLocked()` gains `bool $posts_page = false` (checked first after the Elementor/auto-draft guard: `if ( $posts_page && 'page' === $post_type ) { return true; }`) and `lockedPost()` passes `(int) get_option( 'page_for_posts' ) === $post->ID`. Plugin.php: `( new Site\Protect() )->hook();` next to `PageLock` (outside `is_admin()`, REST saves too).

- [ ] **Step 4:** tests → PASS; lint clean.
- [ ] **Step 5:** commit `feat: Editors can't delete, unpublish or retarget the site's foundations`.

### Task 5: Editor surfaces

**Files:** Modify `src/Menus/Screen.php`, `templates/menus.php`, `assets/menus/menus.css`, `src/Dashboard/Screen.php`, `src/Editors/ElementorEditor.php`, `assets/elementor/editor.js`, `src/Editors/Routing.php`; Test `tests/Unit/ElementorEditorTest.php`.

**Interfaces — Consumes:** `Protect::ids()`, `Protect::role()`, `Routing::elementorUrl( int $post_id ): string`.
**Produces:** `ElementorEditor::back( ?string $role ): array{label: string, href: string}`.

- [ ] **Step 1: Failing test** (add to `ElementorEditorTest`):

```php
	public function test_exit_returns_to_where_the_editor_came_from(): void {
		Functions\when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://x.test/wp-admin/' . $path );
		$this->assertSame( array( 'label' => 'Back to Pages', 'href' => 'https://x.test/wp-admin/edit.php?post_type=page' ), ElementorEditor::back( null ) );
		$this->assertSame( array( 'label' => 'Back to Header & Footer', 'href' => 'https://x.test/wp-admin/admin.php?page=favr-menus' ), ElementorEditor::back( 'header' ) );
		$this->assertSame( 'Back to Pages', ElementorEditor::back( 'home' )['label'] );
	}
```

- [ ] **Step 2:** run → FAIL (method missing).
- [ ] **Step 3: Implement**
  - `ElementorEditor::back( ?string $role )`: header/footer → "Back to Header & Footer" + `admin_url( 'admin.php?page=favr-menus' )`; otherwise "Back to Pages" + `admin_url( 'edit.php?post_type=page' )`. `enqueue()` uses `self::back( Protect::role( isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0, Protect::ids() ) )` and adds `'hide' => array( 'document-save-draft', 'document-display-conditions' )` to the localized config.
  - `editor.js`: after the existing replacements —
    ```js
    // Favr keeps foundations published and site-wide: no drafts, no display conditions.
    ( config.hide || [] ).forEach( function ( id ) {
    	replace( bar.documentOptionsMenu, 'registerAction', id, 'document-save-draft' === id ? 'save' : 'default', hidden );
    } );
    ```
  - `Routing::exitUrl()`: when the document's main id is the Header/Footer (`Protect::role()`), return `admin_url( 'admin.php?page=favr-menus' )`. `Routing::redirectEdit()`: also redirect `elementor_library` posts whose role is header/footer (Editors, `edit_post` allowed) to `self::elementorUrl()`.
  - `Menus\Screen`: menu title and page title "Header & Footer"; per slot `'design' => $role_id ? Routing::elementorUrl( $role_id ) : ''` when Pro is active (`Protect::ids()['header'|'footer']`).
  - `templates/menus.php`: h1 "Header & Footer"; sub "Your site's header and footer appear on every page. Edit their design in Elementor; manage their menu links here."; card title "Header"/"Footer" with "Shown on every page" and the design link button (`.favr-menu__design`, pencil-free text "Edit header design"/"Edit footer design", opens in the same tab); then an `h3` "Menu links" above the existing usage line and list.
  - `Dashboard\Screen::coreActions()`: when `Protect::ids()` has header/footer and Pro is active, add `edit-header` ("Edit header", icon `page`, priority 20) and `edit-footer` ("Edit footer", priority 21), capability `edit_pages`, URL `Routing::elementorUrl( $id )`.
  - Update `MenuTest`/`ScreenTest` expectations only if they assert the old labels.
- [ ] **Step 4:** tests + lint; commit `feat: Header & Footer screen, dashboard actions and Elementor editor for the foundations`.

### Task 6: Real check, docs, release

- [ ] On sermonator-test (Elementor Pro 4.3.0, local only), Administrator visit already ran in Task 3. As the Editor (user 19), with the CDP recipe:
  - Front end: starter Header (site title + Header menu) and Footer (Footer menu + "© 2026 …") render on Home, a page and a News post.
  - Header & Footer screen shows both design buttons; "Edit header design" opens Elementor on the Header; the Publish menu has no "Save as draft" or "Display Conditions"; Exit reads "Back to Header & Footer"; an Editor Publish keeps `_elementor_conditions` = `include/general` and status `publish`.
  - Refused for the Editor: trashing Home/News/Header via `wp_trash_post` over REST (`DELETE /wp/v2/pages/{home}` → 403), REST `POST /wp/v2/pages/{home}` with `status=draft` → stays `publish`; News page shows "Managed by Favr" and can't be edited.
  - Administrator drafts the Header (`wp post update {header} --post_status=draft`), loads wp-admin → Header published again; changes its conditions to `include/singular` via WP-CLI meta → reset to `include/general`.
  - Settled site: a further Administrator visit changes nothing (`post_modified` of the four and the option unchanged).
  - Screenshots (desktop + 500px) of the Header & Footer screen and the front end.
- [ ] GOAABA (read-only, via its MCP query tool): feed its real state into `Foundations::plan()` (it's the `goaaba()` fixture in Task 1) — the plan adopts 111/182/53/55 with no repairs.
- [ ] README ("Site foundations (0.8)" section; Menus → Header & Footer), CLAUDE.md (`Site\*` units), version 0.8.0; final review, merge, push, tag, zip.
