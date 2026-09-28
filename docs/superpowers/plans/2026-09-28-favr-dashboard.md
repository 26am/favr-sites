# Favr Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A new Favr Sites plugin that replaces the wp-admin dashboard for Editors and below with a Favr home screen (attention, quick actions, plugin cards, recent activity, help).

**Architecture:** Standalone plugin. On `load-index.php` it takes over the screen for users without `manage_options`, rendering its own template inside the normal admin frame, so no dashboard widgets are ever built. Other Favr plugins contribute plain-array data through two filters; attention counts come from the existing `favr_approvals_providers` filter.

**Tech Stack:** PHP 8.1+, WordPress 6.7+, PHPUnit 9.6 + Brain Monkey, PHPCS/WPCS 3. No JS, no build step.

**Spec:** `docs/superpowers/specs/2026-09-28-favr-dashboard-design.md`

## Global Constraints

- Plugin slug `favr-sites`, namespace `FavrSites\`, hooks/options prefixed `favr_sites_`, text domain `favr-sites`.
- PHP ≥ 8.1, WordPress ≥ 6.7, GPL-2.0-or-later, no runtime Composer dependencies (bundled PSR-4 autoloader in the main file).
- Audience: logged-in users who can `read` and lack `manage_options`; `favr_sites_dashboard_enabled` filter can override.
- Contributions are plain arrays; no HTML from other plugins; templates escape at output.
- `stats` ≤ 3, `items` ≤ 5 per card; `priority` default 50; duplicate `id` → last wins.
- Help constants: `FAVR_SITES_HELP_URL`, `FAVR_SITES_SUPPORT_EMAIL`, `FAVR_SITES_SUPPORT_PHONE`, `FAVR_SITES_BOOKING_URL`; option `favr_sites_help`.
- All CSS scoped under `.favr-dash`; tokens only in `assets/dashboard/tokens.css`.
- `composer test` green, `composer lint` 0 errors.
- KISS: one class per concern, no caching layer, no JS.

## Review Focus

- A contributed card callback that throws or returns garbage → that card is skipped, the page renders (test in Task 3).
- Activity rows with empty titles or HTML in titles → "(no title)" / escaped text (test in Task 4).
- A user who can't edit a post type (e.g. members) → that type never appears in activity (test in Task 4).
- An approvals provider whose count throws or has no capability → skipped / treated as `manage_options` (test in Task 4).
- Plugin notices hooked on `admin_notices`/`all_admin_notices` → absent on the Favr screen (manual check in Task 5).

## File structure

```
favr-sites.php                 bootstrap, autoloader, boot
uninstall.php                  deletes favr_sites_help
src/Plugin.php                 wires units
src/Dashboard/Audience.php     who gets the Favr dashboard (pure)
src/Dashboard/Items.php        normalize quick actions + cards (pure)
src/Dashboard/Attention.php    approvals queues → counts (pure core + WP wrapper)
src/Dashboard/Activity.php     recent changes (pure helpers + one WP_Query)
src/Dashboard/Screen.php       view model + render template
src/Dashboard/Takeover.php     load-index.php hook, admin frame, exit
src/Dashboard/Icons.php        small inline SVG set
src/Help/Links.php             help links: constant → option → default
src/Admin/SettingsPage.php     Settings → Favr
templates/dashboard.php        markup
assets/dashboard/tokens.css    Favr tokens
assets/dashboard/dashboard.css layout/components
assets/fonts/*.woff2 + OFL.txt self-hosted fonts
tests/bootstrap.php, tests/Unit/*
composer.json, phpunit.xml.dist, phpcs.xml.dist, .gitignore, .gitattributes,
.github/workflows/ci.yml, README.md, CLAUDE.md, LICENSE
```

(Spec lists `Actions` and `Cards` as two units; KISS: they share one `Items` class since the normalization is the same shape of work.)

---

### Task 1: Scaffold + Audience

**Files:**
- Create: `favr-sites.php`, `src/Plugin.php`, `src/Dashboard/Audience.php`, `uninstall.php`, `composer.json`, `phpunit.xml.dist`, `phpcs.xml.dist`, `.gitignore`, `.gitattributes`, `LICENSE` (copy from `../favrevents/LICENSE`), `tests/bootstrap.php`, `tests/Unit/TestCase.php`
- Test: `tests/Unit/AudienceTest.php`

**Interfaces:**
- Produces: `FavrSites\Dashboard\Audience::includes( ?\WP_User $user ): bool`; `FavrSites\Plugin::boot(): void`; constants `FAVR_SITES_VERSION`, `FAVR_SITES_FILE`, `FAVR_SITES_PATH`, `FAVR_SITES_URL`.

- [ ] **Step 1: Tooling files**

`composer.json`:
```json
{
  "name": "favr/favr-sites",
  "description": "The Favr experience on every client site: a Favr dashboard for site editors. Part of Favr Sites.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": { "php": ">=8.1" },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "brain/monkey": "^2.6",
    "wp-coding-standards/wpcs": "^3.1",
    "phpcompatibility/phpcompatibility-wp": "^2.1"
  },
  "autoload": { "psr-4": { "FavrSites\\": "src/" } },
  "autoload-dev": { "psr-4": { "FavrSites\\Tests\\": "tests/" } },
  "config": {
    "platform": { "php": "8.1.0" },
    "allow-plugins": { "dealerdirect/phpcodesniffer-composer-installer": true }
  },
  "scripts": { "test": "phpunit", "lint": "phpcs" }
}
```

`phpunit.xml.dist`: copy `../favrevents/phpunit.xml.dist` verbatim.
`phpcs.xml.dist`: copy `../favrevents/phpcs.xml.dist`, change the ruleset name/description to "Favr Sites", the text domain property to `favr-sites`, and the prefixes to `favr_sites,FavrSites,FAVR_SITES`.
`.gitignore`: `/vendor/`, `.DS_Store`, `.phpunit.result.cache`.
`.gitattributes`: copy from `../favrevents/.gitattributes`.

- [ ] **Step 2: Test bootstrap + base case**

`tests/bootstrap.php`:
```php
<?php
/**
 * Unit test bootstrap: pure logic only; WordPress is stubbed with Brain Monkey.
 *
 * @package FavrSites
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

if ( ! class_exists( 'WP_User' ) ) {
	/** Minimal stand-in for tests. */
	class WP_User {
		/** @var int */
		public $ID;
		public function __construct( int $id = 0 ) {
			$this->ID = $id;
		}
		public function exists(): bool {
			return $this->ID > 0;
		}
	}
}
```

`tests/Unit/TestCase.php`:
```php
<?php
/**
 * Base test case.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubs(
			array(
				'sanitize_text_field' => static fn( $v ) => trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $v ) ) ),
				'sanitize_email'      => static fn( $v ) => filter_var( trim( (string) $v ), FILTER_VALIDATE_EMAIL ) ? trim( (string) $v ) : '',
				'esc_url_raw'         => static fn( $v ) => filter_var( $v, FILTER_VALIDATE_URL ) ? $v : '',
				'__'                  => static fn( $v ) => $v,
				'apply_filters'       => static fn( $hook, $value ) => $value,
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
```

- [ ] **Step 3: Write the failing test** — `tests/Unit/AudienceTest.php`
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;

final class AudienceTest extends TestCase {

	private function caps( array $caps ): void {
		Functions\when( 'user_can' )->alias( static fn( $user, string $cap ): bool => in_array( $cap, $caps, true ) );
	}

	public function test_editor_gets_the_favr_dashboard(): void {
		$this->caps( array( 'read', 'edit_pages' ) );
		$this->assertTrue( Audience::includes( new \WP_User( 5 ) ) );
	}

	public function test_administrator_keeps_the_stock_dashboard(): void {
		$this->caps( array( 'read', 'manage_options' ) );
		$this->assertFalse( Audience::includes( new \WP_User( 1 ) ) );
	}

	public function test_logged_out_or_null_user_is_excluded(): void {
		$this->caps( array( 'read' ) );
		$this->assertFalse( Audience::includes( null ) );
		$this->assertFalse( Audience::includes( new \WP_User( 0 ) ) );
	}

	public function test_filter_can_override(): void {
		$this->caps( array( 'read', 'manage_options' ) );
		Functions\when( 'apply_filters' )->alias( static fn( $hook, $value ) => 'favr_sites_dashboard_enabled' === $hook ? true : $value );
		$this->assertTrue( Audience::includes( new \WP_User( 1 ) ) );
	}
}
```

- [ ] **Step 4: Run it to see it fail**

Run: `composer install && composer test -- --filter AudienceTest`
Expected: FAIL, class `FavrSites\Dashboard\Audience` not found.

- [ ] **Step 5: Implement** — `src/Dashboard/Audience.php`
```php
<?php
/**
 * Who gets the Favr dashboard.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Editors and below get the Favr dashboard; Administrators keep the stock one.
 */
final class Audience {

	/**
	 * Does this user get the Favr dashboard?
	 *
	 * @param \WP_User|null $user User.
	 */
	public static function includes( ?\WP_User $user ): bool {
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$default = user_can( $user, 'read' ) && ! user_can( $user, 'manage_options' );
		return (bool) apply_filters( 'favr_sites_dashboard_enabled', $default, $user );
	}
}
```

- [ ] **Step 6: Bootstrap + composition root**

`favr-sites.php`:
```php
<?php
/**
 * Plugin Name:       Favr Sites
 * Plugin URI:        https://github.com/26am/favr-sites
 * Description:       The Favr experience on every client site, starting with a Favr dashboard for site editors.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Favr Sites
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       favr-sites
 *
 * @package FavrSites
 */

defined( 'ABSPATH' ) || exit;

define( 'FAVR_SITES_VERSION', '0.1.0' );
define( 'FAVR_SITES_FILE', __FILE__ );
define( 'FAVR_SITES_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAVR_SITES_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'FavrSites\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

\FavrSites\Plugin::boot();
```

`src/Plugin.php` (Task 5 adds `Takeover`, Task 2 adds `SettingsPage`):
```php
<?php
/**
 * Composition root.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites;

/**
 * Wires every unit.
 */
final class Plugin {

	/**
	 * Booted.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/** Boot once. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		if ( is_admin() ) {
			// Units are added here by later tasks.
			return;
		}
	}
}
```

`uninstall.php`:
```php
<?php
/**
 * Uninstall: the only stored data is the help-links option.
 *
 * @package FavrSites
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'favr_sites_help' );
```

- [ ] **Step 7: Run tests and lint**

Run: `composer test && composer lint`
Expected: 4 tests pass; PHPCS 0 errors.

- [ ] **Step 8: Commit**
```bash
git add -A && git commit -m "feat: scaffold Favr Sites and decide who gets the Favr dashboard"
```

---

### Task 2: Help links + Settings → Favr

**Files:**
- Create: `src/Help/Links.php`, `src/Admin/SettingsPage.php`
- Modify: `src/Plugin.php` (hook `SettingsPage`)
- Test: `tests/Unit/LinksTest.php`

**Interfaces:**
- Produces: `FavrSites\Help\Links::all(): array{help_url: string, support_email: string, support_phone: string, booking_url: string}`; `Links::clean( string $key, string $value ): string`; `Links::OPTION = 'favr_sites_help'`; `Links::DEFAULTS`.

- [ ] **Step 1: Write the failing test** — `tests/Unit/LinksTest.php`
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Help\Links;

final class LinksTest extends TestCase {

	public function test_option_values_override_defaults_and_blanks_fall_back(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'help_url'      => 'https://help.example.com/',
				'support_email' => '',
				'support_phone' => '407 555 0100',
			)
		);
		$links = Links::all();
		$this->assertSame( 'https://help.example.com/', $links['help_url'] );
		$this->assertSame( Links::DEFAULTS['support_email'], $links['support_email'] );
		$this->assertSame( '407 555 0100', $links['support_phone'] );
	}

	public function test_garbage_is_cleaned(): void {
		$this->assertSame( '', Links::clean( 'help_url', 'javascript:alert(1)' ) );
		$this->assertSame( '', Links::clean( 'support_email', 'not an email' ) );
		$this->assertSame( 'Call us', Links::clean( 'support_phone', '<b>Call us</b>' ) );
	}

	public function test_non_array_option_is_ignored(): void {
		Functions\when( 'get_option' )->justReturn( 'nonsense' );
		$this->assertSame( Links::DEFAULTS['help_url'], Links::all()['help_url'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constant_wins_over_option(): void {
		define( 'FAVR_SITES_HELP_URL', 'https://fleet.example.com/help' );
		Functions\when( 'get_option' )->justReturn( array( 'help_url' => 'https://site.example.com/' ) );
		$this->assertSame( 'https://fleet.example.com/help', Links::all()['help_url'] );
	}
}
```

- [ ] **Step 2: Run to see it fail**

Run: `composer test -- --filter LinksTest`
Expected: FAIL, class `FavrSites\Help\Links` not found.

- [ ] **Step 3: Implement** — `src/Help/Links.php`
```php
<?php
/**
 * Help and support links.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Help;

/**
 * Resolution: wp-config constant → site option → fleet default. Empty values are not shown.
 */
final class Links {

	public const OPTION = 'favr_sites_help';

	/** Fleet defaults (filled in once the Favr help site and support inbox exist). */
	public const DEFAULTS = array(
		'help_url'      => '',
		'support_email' => '',
		'support_phone' => '',
		'booking_url'   => '',
	);

	/**
	 * Resolved links.
	 *
	 * @return array{help_url: string, support_email: string, support_phone: string, booking_url: string}
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$constant = 'FAVR_SITES_' . strtoupper( $key );
			if ( defined( $constant ) ) {
				$value = (string) constant( $constant );
			} elseif ( isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ) {
				$value = (string) $saved[ $key ];
			} else {
				$value = $default;
			}
			$out[ $key ] = self::clean( $key, $value );
		}
		return $out;
	}

	/**
	 * Sanitize one value by key.
	 *
	 * @param string $key   Field key.
	 * @param string $value Raw value.
	 */
	public static function clean( string $key, string $value ): string {
		return match ( $key ) {
			'support_email' => (string) sanitize_email( $value ),
			'support_phone' => (string) sanitize_text_field( $value ),
			default         => (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) ),
		};
	}
}
```

- [ ] **Step 4: Run tests** — `composer test -- --filter LinksTest` → PASS.

- [ ] **Step 5: Settings page** — `src/Admin/SettingsPage.php`
```php
<?php
/**
 * Settings → Favr.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Admin;

use FavrSites\Help\Links;

/**
 * Administrators set the help links shown on the Favr dashboard.
 */
final class SettingsPage {

	private const PAGE = 'favr-sites';

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/** Menu entry. */
	public function menu(): void {
		add_options_page( __( 'Favr', 'favr-sites' ), __( 'Favr', 'favr-sites' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/** Setting. */
	public function register(): void {
		register_setting(
			self::PAGE,
			Links::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize the submitted array.
	 *
	 * @param mixed $input Raw.
	 * @return array<string, string>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( array_keys( Links::DEFAULTS ) as $key ) {
			$out[ $key ] = Links::clean( $key, (string) ( $input[ $key ] ?? '' ) );
		}
		return $out;
	}

	/** Page. */
	public function render(): void {
		$saved  = get_option( Links::OPTION, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$fields = array(
			'help_url'      => array( __( 'Help centre URL', 'favr-sites' ), 'url' ),
			'support_email' => array( __( 'Support email', 'favr-sites' ), 'email' ),
			'support_phone' => array( __( 'Support phone', 'favr-sites' ), 'text' ),
			'booking_url'   => array( __( 'Book a call URL', 'favr-sites' ), 'url' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Favr', 'favr-sites' ); ?></h1>
			<p><?php esc_html_e( 'Help links shown to site editors on the Favr dashboard. Leave a field empty to use the Favr default; empty links are hidden.', 'favr-sites' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $key => $field ) : ?>
						<tr>
							<th scope="row"><label for="favr-sites-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
							<td>
								<input class="regular-text" type="<?php echo esc_attr( $field[1] ); ?>" id="favr-sites-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( Links::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $saved[ $key ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( Links::DEFAULTS[ $key ] ); ?>">
								<?php if ( defined( 'FAVR_SITES_' . strtoupper( $key ) ) ) : ?>
									<p class="description"><?php esc_html_e( 'Set in wp-config.php; this field is ignored.', 'favr-sites' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
```

In `src/Plugin.php`, replace the `is_admin()` block body with:
```php
		if ( is_admin() ) {
			( new Admin\SettingsPage() )->hook();
		}
```

- [ ] **Step 6: Run tests + lint** — `composer test && composer lint` → green.

- [ ] **Step 7: Commit**
```bash
git add -A && git commit -m "feat: help links with fleet defaults and Settings → Favr"
```

---

### Task 3: Quick actions and cards (normalization)

**Files:**
- Create: `src/Dashboard/Items.php`
- Test: `tests/Unit/ItemsTest.php`

**Interfaces:**
- Produces:
  - `Items::actions( array $raw, callable $can ): list<array{id: string, label: string, url: string, icon: string, priority: int}>`
  - `Items::cards( array $raw, callable $can ): list<array{id: string, title: string, priority: int, stats: list<array{label: string, value: string, url: string}>, items: list<array{title: string, meta: string, url: string}>, link: ?array{label: string, url: string}, empty: ?array{text: string, label: string, url: string}}>`
  - `$can` is `callable( string $capability ): bool`. `$raw` entries for cards may be arrays **or** callables returning an array (callables run inside try/catch, so one plugin's failure can't take down the page).

- [ ] **Step 1: Write the failing test** — `tests/Unit/ItemsTest.php`
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Items;

final class ItemsTest extends TestCase {

	private static function can( array $caps ): callable {
		return static fn( string $cap ): bool => in_array( $cap, $caps, true );
	}

	public function test_actions_are_filtered_sorted_and_deduped(): void {
		$raw = array(
			array( 'id' => 'b', 'label' => 'Bravo', 'url' => '/b', 'priority' => 20 ),
			array( 'id' => 'a', 'label' => 'Alpha', 'url' => '/a' ),
			array( 'id' => 'secret', 'label' => 'Secret', 'url' => '/s', 'capability' => 'manage_options' ),
			array( 'id' => 'b', 'label' => 'Bravo 2', 'url' => '/b2', 'priority' => 10 ),
			array( 'label' => 'No id', 'url' => '/x' ),
			'garbage',
		);
		$out = Items::actions( $raw, self::can( array( 'edit_posts' ) ) );
		$this->assertSame( array( 'b', 'a' ), array_column( $out, 'id' ) );
		$this->assertSame( 'Bravo 2', $out[0]['label'] );
		$this->assertSame( 50, $out[1]['priority'] );
		$this->assertSame( '', $out[1]['icon'] );
	}

	public function test_cards_cap_stats_and_items_and_drop_invalid_rows(): void {
		$card = array(
			'id'    => 'events',
			'title' => 'Events',
			'stats' => array(
				array( 'label' => 'upcoming', 'value' => 2 ),
				array( 'label' => 'b', 'value' => '3' ),
				array( 'label' => 'c', 'value' => 4 ),
				array( 'label' => 'd', 'value' => 5 ),
				array( 'label' => '', 'value' => 9 ),
			),
			'items' => array_fill( 0, 7, array( 'title' => 'Fall Happy Hour', 'meta' => 'Thu 15 Oct', 'url' => '/e' ) ),
			'link'  => array( 'label' => 'All events', 'url' => '/events' ),
			'empty' => array( 'text' => 'No events yet.' ),
		);
		$out  = Items::cards( array( $card ), self::can( array() ) );
		$this->assertCount( 1, $out );
		$this->assertCount( 3, $out[0]['stats'] );
		$this->assertSame( '2', $out[0]['stats'][0]['value'] );
		$this->assertCount( 5, $out[0]['items'] );
		$this->assertSame( array( 'label' => 'All events', 'url' => '/events' ), $out[0]['link'] );
		$this->assertSame( array( 'text' => 'No events yet.', 'label' => '', 'url' => '' ), $out[0]['empty'] );
	}

	public function test_card_callables_are_isolated(): void {
		$raw = array(
			static function (): array {
				throw new \RuntimeException( 'boom' );
			},
			static fn() => 'not an array',
			static fn(): array => array( 'id' => 'ok', 'title' => 'Fine' ),
			array( 'id' => 'hidden', 'title' => 'Hidden', 'capability' => 'edit_favr_members' ),
		);
		$out = Items::cards( $raw, self::can( array() ) );
		$this->assertSame( array( 'ok' ), array_column( $out, 'id' ) );
		$this->assertNull( $out[0]['link'] );
		$this->assertSame( array(), $out[0]['items'] );
	}
}
```

- [ ] **Step 2: Run to see it fail** — `composer test -- --filter ItemsTest` → FAIL (class not found).

- [ ] **Step 3: Implement** — `src/Dashboard/Items.php`
```php
<?php
/**
 * Quick actions and cards contributed by Favr plugins.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Normalizes plain-array contributions: drops invalid rows and rows the user can't use,
 * dedupes by id (last wins), sorts by priority then label.
 */
final class Items {

	public const MAX_STATS = 3;
	public const MAX_ITEMS = 5;

	/**
	 * Quick actions.
	 *
	 * @param array<mixed> $raw Contributions.
	 * @param callable     $can callable( string $capability ): bool.
	 * @return list<array{id: string, label: string, url: string, icon: string, priority: int}>
	 */
	public static function actions( array $raw, callable $can ): array {
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! self::visible( $row, $can ) ) {
				continue;
			}
			$id    = self::str( $row['id'] ?? '' );
			$label = self::str( $row['label'] ?? '' );
			$url   = self::str( $row['url'] ?? '' );
			if ( '' === $id || '' === $label || '' === $url ) {
				continue;
			}
			$out[ $id ] = array(
				'id'       => $id,
				'label'    => $label,
				'url'      => $url,
				'icon'     => self::str( $row['icon'] ?? '' ),
				'priority' => (int) ( $row['priority'] ?? 50 ),
			);
		}
		return self::sorted( $out, 'label' );
	}

	/**
	 * Cards. Entries may be arrays or callables returning an array.
	 *
	 * @param array<mixed> $raw Contributions.
	 * @param callable     $can callable( string $capability ): bool.
	 * @return list<array<string, mixed>>
	 */
	public static function cards( array $raw, callable $can ): array {
		$out = array();
		foreach ( $raw as $row ) {
			if ( is_callable( $row ) ) {
				try {
					$row = call_user_func( $row );
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Favr Sites: dashboard card failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					continue;
				}
			}
			if ( ! is_array( $row ) || ! self::visible( $row, $can ) ) {
				continue;
			}
			$id    = self::str( $row['id'] ?? '' );
			$title = self::str( $row['title'] ?? '' );
			if ( '' === $id || '' === $title ) {
				continue;
			}
			$out[ $id ] = array(
				'id'       => $id,
				'title'    => $title,
				'priority' => (int) ( $row['priority'] ?? 50 ),
				'stats'    => self::rows( $row['stats'] ?? array(), self::MAX_STATS, 'label', array( 'value', 'url' ), 'value' ),
				'items'    => self::rows( $row['items'] ?? array(), self::MAX_ITEMS, 'title', array( 'meta', 'url' ) ),
				'link'     => self::link( $row['link'] ?? null ),
				'empty'    => self::emptyState( $row['empty'] ?? null ),
			);
		}
		return self::sorted( $out, 'title' );
	}

	/**
	 * Visible to this user?
	 *
	 * @param array<string, mixed> $row Row.
	 * @param callable             $can Check.
	 */
	private static function visible( array $row, callable $can ): bool {
		$cap = self::str( $row['capability'] ?? '' );
		return '' === $cap || (bool) $can( $cap );
	}

	/**
	 * Normalize sub-rows.
	 *
	 * @param mixed         $rows     Raw rows.
	 * @param int           $max      Cap.
	 * @param string        $key      Required key.
	 * @param list<string>  $optional Optional keys.
	 * @param string|null   $also     A second required key.
	 * @return list<array<string, string>>
	 */
	private static function rows( $rows, int $max, string $key, array $optional, ?string $also = null ): array {
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) || '' === self::str( $row[ $key ] ?? '' ) || ( $also && '' === self::str( $row[ $also ] ?? '' ) ) ) {
				continue;
			}
			$clean = array( $key => self::str( $row[ $key ] ) );
			foreach ( $optional as $name ) {
				$clean[ $name ] = self::str( $row[ $name ] ?? '' );
			}
			$out[] = $clean;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * A link needs both parts.
	 *
	 * @param mixed $link Raw.
	 * @return array{label: string, url: string}|null
	 */
	private static function link( $link ): ?array {
		if ( ! is_array( $link ) ) {
			return null;
		}
		$label = self::str( $link['label'] ?? '' );
		$url   = self::str( $link['url'] ?? '' );
		return '' !== $label && '' !== $url ? array( 'label' => $label, 'url' => $url ) : null;
	}

	/**
	 * Empty state needs text; its call to action is optional.
	 *
	 * @param mixed $state Raw.
	 * @return array{text: string, label: string, url: string}|null
	 */
	private static function emptyState( $state ): ?array {
		if ( ! is_array( $state ) || '' === self::str( $state['text'] ?? '' ) ) {
			return null;
		}
		return array(
			'text'  => self::str( $state['text'] ),
			'label' => self::str( $state['label'] ?? '' ),
			'url'   => self::str( $state['url'] ?? '' ),
		);
	}

	/**
	 * Scalars to trimmed strings; anything else to ''.
	 *
	 * @param mixed $value Raw.
	 */
	private static function str( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Sort by priority, then a label key.
	 *
	 * @param array<string, array<string, mixed>> $rows Rows keyed by id.
	 * @param string                              $by   Tie-break key.
	 * @return list<array<string, mixed>>
	 */
	private static function sorted( array $rows, string $by ): array {
		$rows = array_values( $rows );
		usort( $rows, static fn( array $a, array $b ): int => array( $a['priority'], strtolower( $a[ $by ] ) ) <=> array( $b['priority'], strtolower( $b[ $by ] ) ) );
		return $rows;
	}
}
```

- [ ] **Step 4: Run tests + lint** — `composer test && composer lint` → green.

- [ ] **Step 5: Commit**
```bash
git add -A && git commit -m "feat: normalize quick actions and dashboard cards from Favr plugins"
```

---

### Task 4: Needs your attention + recent activity

**Files:**
- Create: `src/Dashboard/Attention.php`, `src/Dashboard/Activity.php`
- Test: `tests/Unit/AttentionTest.php`, `tests/Unit/ActivityTest.php`

**Interfaces:**
- Produces:
  - `Attention::queues( array $providers, callable $can ): list<array{id: string, label: string, count: int}>` (pure); `Attention::current(): list<…>` (reads the `favr_approvals_providers` filter with `current_user_can`); `Attention::url(): string` → `admin_url( 'admin.php?page=favr-approvals' )`.
  - `Activity::types( list<string> $candidates, callable $editable ): list<string>` (pure: keeps types where `$editable( $type )` is true); `Activity::verb( string $created_gmt, string $modified_gmt ): string` → `'added'|'updated'`; `Activity::title( string $raw ): string`; `Activity::recent( int $limit = 10 ): list<array{who: string, verb: string, title: string, url: string, ago: string}>`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/AttentionTest.php`:
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Attention;

final class AttentionTest extends TestCase {

	public function test_counts_only_what_the_user_can_review(): void {
		$providers = array(
			array( 'id' => 'listings', 'label' => 'Listing updates', 'capability' => 'edit_others_favr_businesses', 'count' => static fn(): int => 3, 'items' => static fn(): array => array() ),
			array( 'id' => 'claims', 'label' => 'Claims', 'capability' => 'edit_others_favr_businesses', 'items' => static fn(): array => array( 1 ) ),
			array( 'id' => 'none', 'label' => 'Nothing', 'capability' => 'edit_others_favr_businesses', 'count' => static fn(): int => 0, 'items' => static fn(): array => array() ),
			array( 'id' => 'members', 'label' => 'Applications', 'capability' => 'edit_others_favr_members', 'count' => static fn(): int => 9, 'items' => static fn(): array => array() ),
			array( 'id' => 'nocap', 'label' => 'Admin only', 'count' => static fn(): int => 4, 'items' => static fn(): array => array() ),
			array( 'id' => 'broken', 'label' => 'Broken', 'capability' => 'edit_others_favr_businesses', 'count' => static function (): int {
				throw new \RuntimeException( 'db down' );
			}, 'items' => static fn(): array => array() ),
			'garbage',
		);
		$can = static fn( string $cap ): bool => 'edit_others_favr_businesses' === $cap;
		$this->assertSame(
			array(
				array( 'id' => 'listings', 'label' => 'Listing updates', 'count' => 3 ),
				array( 'id' => 'claims', 'label' => 'Claims', 'count' => 1 ),
			),
			Attention::queues( $providers, $can )
		);
	}
}
```

`tests/Unit/ActivityTest.php`:
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Activity;

final class ActivityTest extends TestCase {

	public function test_only_editable_types_are_listed(): void {
		$editable = static fn( string $type ): bool => in_array( $type, array( 'page', 'post', 'favr_event' ), true );
		$this->assertSame( array( 'page', 'post', 'favr_event' ), Activity::types( array( 'page', 'post', 'favr_member', 'favr_event' ), $editable ) );
	}

	public function test_verb_says_added_when_never_edited_after_creation(): void {
		$this->assertSame( 'added', Activity::verb( '2026-09-28 10:00:00', '2026-09-28 10:00:40' ) );
		$this->assertSame( 'updated', Activity::verb( '2026-09-28 10:00:00', '2026-09-28 12:00:00' ) );
	}

	public function test_title_strips_markup_and_falls_back(): void {
		$this->assertSame( 'About Us', Activity::title( '<em>About</em> Us' ) );
		$this->assertSame( '(no title)', Activity::title( '   ' ) );
	}
}
```

- [ ] **Step 2: Run to see them fail** — `composer test -- --filter 'AttentionTest|ActivityTest'` → FAIL (classes not found).

- [ ] **Step 3: Implement** — `src/Dashboard/Attention.php`
```php
<?php
/**
 * Needs your attention: approval queues with something waiting.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Reads the plugin-neutral `favr_approvals_providers` contract (see favr/core Approvals\Inbox).
 */
final class Attention {

	/**
	 * Queues the user can review that have items waiting.
	 *
	 * @param array<mixed> $providers Providers.
	 * @param callable     $can       callable( string $capability ): bool.
	 * @return list<array{id: string, label: string, count: int}>
	 */
	public static function queues( array $providers, callable $can ): array {
		$out = array();
		foreach ( $providers as $provider ) {
			if ( ! is_array( $provider ) || ! isset( $provider['id'], $provider['label'], $provider['items'] ) ) {
				continue;
			}
			if ( ! $can( (string) ( $provider['capability'] ?? 'manage_options' ) ) ) {
				continue;
			}
			try {
				$count = isset( $provider['count'] ) && is_callable( $provider['count'] )
					? (int) call_user_func( $provider['count'] )
					: count( (array) call_user_func( $provider['items'] ) );
			} catch ( \Throwable $e ) {
				continue;
			}
			if ( $count > 0 ) {
				$out[] = array(
					'id'    => (string) $provider['id'],
					'label' => (string) $provider['label'],
					'count' => $count,
				);
			}
		}
		return $out;
	}

	/**
	 * For the current user.
	 *
	 * @return list<array{id: string, label: string, count: int}>
	 */
	public static function current(): array {
		return self::queues( (array) apply_filters( 'favr_approvals_providers', array() ), 'current_user_can' );
	}

	/** Where the queues are reviewed. */
	public static function url(): string {
		return admin_url( 'admin.php?page=favr-approvals' );
	}
}
```

`src/Dashboard/Activity.php`:
```php
<?php
/**
 * Recent activity across the site's content.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * The most recently changed items the user may edit. Uses WordPress's own "last edited by"
 * record; nothing new is stored.
 */
final class Activity {

	private const TYPES = array( 'page', 'post', 'favr_business', 'favr_event', 'favr_member' );

	/**
	 * Keep types the user may edit.
	 *
	 * @param list<string> $candidates Post types.
	 * @param callable     $editable   callable( string $type ): bool.
	 * @return list<string>
	 */
	public static function types( array $candidates, callable $editable ): array {
		return array_values( array_filter( $candidates, static fn( $type ): bool => is_string( $type ) && $editable( $type ) ) );
	}

	/**
	 * "added" when the item hasn't been edited since it was created (within a minute), else "updated".
	 *
	 * @param string $created_gmt  post_date_gmt.
	 * @param string $modified_gmt post_modified_gmt.
	 */
	public static function verb( string $created_gmt, string $modified_gmt ): string {
		return abs( strtotime( $modified_gmt . ' UTC' ) - strtotime( $created_gmt . ' UTC' ) ) <= MINUTE_IN_SECONDS ? 'added' : 'updated';
	}

	/**
	 * Plain-text title with a fallback.
	 *
	 * @param string $raw Title.
	 */
	public static function title( string $raw ): string {
		$title = trim( wp_strip_all_tags( $raw ) );
		return '' !== $title ? $title : __( '(no title)', 'favr-sites' );
	}

	/**
	 * Recent changes.
	 *
	 * @param int $limit Max rows.
	 * @return list<array{who: string, verb: string, title: string, url: string, ago: string}>
	 */
	public static function recent( int $limit = 10 ): array {
		$candidates = (array) apply_filters( 'favr_sites_activity_post_types', self::TYPES );
		$types      = self::types(
			$candidates,
			static function ( string $type ): bool {
				$object = get_post_type_object( $type );
				return $object && current_user_can( $object->cap->edit_posts );
			}
		);
		if ( ! $types ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future' ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'posts_per_page'         => $limit,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
			)
		);
		$out = array();
		foreach ( $query->posts as $post ) {
			$user_id = (int) get_post_meta( $post->ID, '_edit_last', true ) ?: (int) $post->post_author;
			$user    = $user_id ? get_userdata( $user_id ) : false;
			$out[]   = array(
				'who'   => $user ? $user->display_name : __( 'Someone', 'favr-sites' ),
				'verb'  => self::verb( $post->post_date_gmt, $post->post_modified_gmt ),
				'title' => self::title( $post->post_title ),
				'url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
				/* translators: %s: human time difference, e.g. "2 hours". */
				'ago'   => sprintf( __( '%s ago', 'favr-sites' ), human_time_diff( (int) strtotime( $post->post_modified_gmt . ' UTC' ) ) ),
			);
		}
		return $out;
	}
}
```

Add to `TestCase::setUp()` stubs: `'wp_strip_all_tags' => static fn( $v ) => strip_tags( (string) $v )`, and to `tests/bootstrap.php`: `if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }`.

- [ ] **Step 4: Run tests + lint** — `composer test && composer lint` → green.

- [ ] **Step 5: Commit**
```bash
git add -A && git commit -m "feat: approval counts and recent activity for the Favr dashboard"
```

---

### Task 5: The screen (takeover, template, styles) + real check

**Files:**
- Create: `src/Dashboard/Takeover.php`, `src/Dashboard/Screen.php`, `src/Dashboard/Icons.php`, `templates/dashboard.php`, `assets/dashboard/tokens.css`, `assets/dashboard/dashboard.css`, `assets/fonts/` (woff2 + `OFL.txt`)
- Modify: `src/Plugin.php`
- Test: `tests/Unit/ScreenTest.php` (greeting)

**Interfaces:**
- Consumes: `Audience::includes`, `Items::actions`, `Items::cards`, `Attention::current`, `Attention::url`, `Activity::recent`, `Links::all`.
- Produces: `Screen::greeting( int $hour ): string`; `Screen::render(): void`; `Takeover::hook(): void`; `Icons::svg( string $name ): string`.

- [ ] **Step 1: Failing test** — `tests/Unit/ScreenTest.php`
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Screen;

final class ScreenTest extends TestCase {

	public function test_greeting_follows_the_clock(): void {
		$this->assertSame( 'Good morning', Screen::greeting( 6 ) );
		$this->assertSame( 'Good afternoon', Screen::greeting( 12 ) );
		$this->assertSame( 'Good evening', Screen::greeting( 18 ) );
		$this->assertSame( 'Good evening', Screen::greeting( 2 ) );
	}
}
```
Run: `composer test -- --filter ScreenTest` → FAIL (class not found).

- [ ] **Step 2: Screen** — `src/Dashboard/Screen.php`
```php
<?php
/**
 * The Favr dashboard view.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

use FavrSites\Help\Links;

/**
 * Builds the view model and renders templates/dashboard.php.
 */
final class Screen {

	/**
	 * Greeting for an hour of the day (site timezone).
	 *
	 * @param int $hour 0–23.
	 */
	public static function greeting( int $hour ): string {
		if ( $hour >= 5 && $hour < 12 ) {
			return __( 'Good morning', 'favr-sites' );
		}
		if ( $hour >= 12 && $hour < 17 ) {
			return __( 'Good afternoon', 'favr-sites' );
		}
		return __( 'Good evening', 'favr-sites' );
	}

	/** Render. */
	public function render(): void {
		$user    = wp_get_current_user();
		$name    = $user->first_name ?: $user->display_name;
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$view    = array(
			'site'      => get_bloginfo( 'name' ),
			'site_url'  => home_url( '/' ),
			'logo'      => $logo_id ? (string) wp_get_attachment_image( $logo_id, 'medium', false, array( 'class' => 'favr-dash__logo', 'alt' => '' ) ) : '',
			'icon'      => $logo_id ? '' : (string) get_site_icon_url( 96 ),
			'greeting'  => self::greeting( (int) wp_date( 'G' ) ) . ', ' . $name,
			'attention' => Attention::current(),
			'review'    => Attention::url(),
			'actions'   => Items::actions( (array) apply_filters( 'favr_sites_quick_actions', self::coreActions() ), 'current_user_can' ),
			'cards'     => Items::cards( (array) apply_filters( 'favr_sites_dashboard_cards', array() ), 'current_user_can' ),
			'activity'  => Activity::recent(),
			'help'      => Links::all(),
		);
		include FAVR_SITES_PATH . 'templates/dashboard.php';
	}

	/**
	 * Actions every site has.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function coreActions(): array {
		return array(
			array(
				'id'         => 'add-post',
				'label'      => __( 'Add news post', 'favr-sites' ),
				'url'        => admin_url( 'post-new.php' ),
				'capability' => 'edit_posts',
				'icon'       => 'news',
				'priority'   => 10,
			),
			array(
				'id'         => 'edit-pages',
				'label'      => __( 'Edit pages', 'favr-sites' ),
				'url'        => admin_url( 'edit.php?post_type=page' ),
				'capability' => 'edit_pages',
				'icon'       => 'page',
				'priority'   => 90,
			),
		);
	}
}
```

- [ ] **Step 3: Icons** — `src/Dashboard/Icons.php`: a `svg( string $name ): string` returning a 20×20 stroke SVG (`fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" focusable="false"`) for `news`, `page`, `calendar`, `users`, `store`, `plus`, `external`, `mail`, `phone`, `help`, and `''` for unknown names. Paths (24-unit viewBox):
```php
private const PATHS = array(
	'news'     => '<path d="M4 5h13v14H6a2 2 0 0 1-2-2z"/><path d="M17 9h3v8a2 2 0 0 1-2 2"/><path d="M8 9h5M8 13h5"/>',
	'page'     => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
	'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
	'users'    => '<circle cx="9" cy="9" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3.5 3.5 0 0 1 0 7M21 20a6 6 0 0 0-4-5.6"/>',
	'store'    => '<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9v11h16V9"/><path d="M4 9a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M10 20v-5h4v5"/>',
	'plus'     => '<path d="M12 5v14M5 12h14"/>',
	'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5H5V6h5"/>',
	'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
	'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
	'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14"/><path d="M12 17h.01"/>',
);
```

- [ ] **Step 4: Template** — `templates/dashboard.php` renders, in order, under `<div class="wrap favr-dash">`:
  1. `<header class="favr-dash__head">`: logo (`$view['logo']` via `wp_kses_post`) or site icon `<img>` or nothing; site name; `<h1>` greeting; "View site" link with external icon; `<span class="favr-dash__brand">favr</span>`.
  2. If `$view['attention']`: `<section class="favr-dash__attention" aria-label="Needs your attention">` with "Needs your attention", then each queue as `<a href="{review}#{id}">{count} {label}</a>`.
  3. If `$view['actions']`: `<nav class="favr-dash__actions" aria-label="Quick actions">` of `<a class="favr-dash__action">{icon}{label}</a>`.
  4. If `$view['cards']`: `<div class="favr-dash__cards">` of `<section class="favr-dash__card">` with `<h2>` title, stats as `<a|span class="favr-dash__stat"><strong>{value}</strong> {label}</a|span>`, items as `<ul>` of `<li><a|span>{title}</a|span> <span class="favr-dash__meta">{meta}</span></li>`, the empty state when there are no stats and no items (`<p>{text}</p>` + optional link), and the footer link.
  5. `<div class="favr-dash__bottom">`: Recent activity `<section>` (`<ol>` of "<strong>{who}</strong> {verb} <a>{title}</a> · {ago}", or "Nothing has changed recently." when empty) and Help `<section>` with each non-empty link (help centre ↗, email as `mailto:`, phone as `tel:` with the number shown, book a call ↗). If every help value is empty the card says "Your Favr team is here to help. Ask your site manager for the best way to reach us."
  Every dynamic value is escaped (`esc_html`, `esc_url`, `esc_attr`); icons are static SVG from `Icons::svg()` (phpcs ignore with reason). Verb strings are translated in the template: `'added' => __( 'added', 'favr-sites' )`, `'updated' => __( 'updated', 'favr-sites' )`.

- [ ] **Step 5: Fonts** — download latin woff2 for Newsreader (500) and Instrument Sans (400, 600) from the Google Fonts CSS2 API (`curl -A "Mozilla/5.0 (Macintosh) Chrome/120" "https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500&family=Instrument+Sans:wght@400;600&display=swap"`, then fetch each `latin` `src: url(...)`) into `assets/fonts/newsreader-500.woff2`, `instrument-sans-400.woff2`, `instrument-sans-600.woff2`; add `assets/fonts/OFL.txt` (SIL Open Font License 1.1 text).

- [ ] **Step 6: Styles**

`assets/dashboard/tokens.css` (the only place Favr's look is defined):
```css
@font-face { font-family: "Favr Display"; src: url("../fonts/newsreader-500.woff2") format("woff2"); font-weight: 500; font-display: swap; }
@font-face { font-family: "Favr Sans"; src: url("../fonts/instrument-sans-400.woff2") format("woff2"); font-weight: 400; font-display: swap; }
@font-face { font-family: "Favr Sans"; src: url("../fonts/instrument-sans-600.woff2") format("woff2"); font-weight: 600; font-display: swap; }

.favr-dash {
	--favr-ink: #1B1F24;
	--favr-muted: #5E636B;
	--favr-paper: #F6F6F3;
	--favr-surface: #FFFFFF;
	--favr-line: #E3E3DD;
	--favr-accent: #1F6F5C;
	--favr-accent-soft: #E4F0EB;
	--favr-attention: #A5540A;
	--favr-attention-soft: #FBEFE3;
	--favr-radius: 12px;
	--favr-gap: 20px;
	--favr-font-display: "Favr Display", Georgia, "Times New Roman", serif;
	--favr-font-sans: "Favr Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
```

`assets/dashboard/dashboard.css` — layout per the spec, everything under `.favr-dash`:
- `body.index-php` background `var(--favr-paper)` only when the Favr screen is active (Takeover adds admin body class `favr-dash-screen`; selector `.favr-dash-screen #wpcontent`).
- `.favr-dash` max-width 1200px, font `var(--favr-font-sans)` 14px/1.55, color ink; `display: grid; gap: var(--favr-gap)`.
- Header: flex row, logo max-height 48px, site name muted uppercase 12px letter-spacing .08em, `h1` in `--favr-font-display` 32px/1.15 weight 500, `.favr-dash__brand` right-aligned, display font 18px, muted.
- Attention: surface `--favr-attention-soft`, 1px border `color-mix(in srgb, var(--favr-attention) 25%, transparent)`, radius, links in `--favr-attention` weight 600.
- Actions: flex wrap gap 10px; each action a 40px-high pill button, surface background, 1px line border, ink text, icon in accent; hover border accent + accent-soft background; `:focus-visible` 2px accent outline offset 2px.
- Cards: `grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))`; card surface, 1px line, radius, padding 20px; `h2` display font 19px; stats row with `strong` 28px tabular-nums display font; list items separated by 1px line; footer link accent weight 600.
- Bottom: `grid-template-columns: 2fr 1fr`, collapsing to one column under 782px (so do cards: minmax already handles).
- Links inherit colour, underline on hover only; `@media (prefers-reduced-motion: reduce)` removes transitions.
- Hide leftover chrome: `.favr-dash-screen #screen-meta-links { display: none; }`.

- [ ] **Step 7: Takeover** — `src/Dashboard/Takeover.php`
```php
<?php
/**
 * Replace wp-admin/index.php for the Favr audience.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Runs on load-index.php, before WordPress sets up dashboard widgets: renders the Favr screen
 * inside the normal admin frame and stops, so no core or plugin widget is ever built.
 */
final class Takeover {

	/** Hooks. */
	public function hook(): void {
		add_action( 'load-index.php', array( $this, 'maybeTakeOver' ) );
	}

	/** Take over when the user is in the audience. */
	public function maybeTakeOver(): void {
		if ( ! Audience::includes( wp_get_current_user() ) ) {
			return;
		}
		$version = defined( 'WP_DEBUG' ) && WP_DEBUG ? (string) filemtime( FAVR_SITES_PATH . 'assets/dashboard/dashboard.css' ) : FAVR_SITES_VERSION;
		wp_enqueue_style( 'favr-sites-tokens', FAVR_SITES_URL . 'assets/dashboard/tokens.css', array(), $version );
		wp_enqueue_style( 'favr-sites-dashboard', FAVR_SITES_URL . 'assets/dashboard/dashboard.css', array( 'favr-sites-tokens' ), $version );
		add_filter( 'admin_body_class', static fn( string $classes ): string => $classes . ' favr-dash-screen' );
		// Plugin notices ("rate us", licence nags) don't belong on the Favr screen.
		add_action(
			'in_admin_header',
			static function (): void {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
				remove_all_actions( 'network_admin_notices' );
			},
			PHP_INT_MAX
		);

		// Globals admin-header.php reads (index.php would set them after this hook).
		$GLOBALS['title']       = __( 'Dashboard', 'favr-sites' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['parent_file'] = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		require_once ABSPATH . 'wp-admin/admin-header.php';
		( new Screen() )->render();
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}
}
```

In `src/Plugin.php` `is_admin()` block add `( new Dashboard\Takeover() )->hook();`.

- [ ] **Step 8: Run tests + lint** — `composer test && composer lint` → green.

- [ ] **Step 9: Real check on sermonator-test**

```bash
SITE="$HOME/Local Sites/sermonator-test/app/public"
ln -s ~/Repo/favrsite/favrsites "$SITE/wp-content/plugins/favr-sites"
SOCK=$(ls -d "$HOME/Library/Application Support/Local/run/"*/mysql/mysqld.sock | head -1)   # pick the sermonator-test one
WP="php -d mysqli.default_socket=$SOCK /path/to/wp-cli.phar --path=$SITE"
$WP plugin activate favr-sites
$WP user list --role=editor --fields=ID,user_login
```
Fetch the dashboard HTML as the Editor with a short-lived auth cookie (local test site only):
```bash
EDITOR_ID=<id from above>
COOKIE=$($WP eval "echo wp_generate_auth_cookie($EDITOR_ID, time()+600, 'logged_in');")
curl -s -b "wordpress_logged_in_$($WP eval 'echo COOKIEHASH;')=$COOKIE" http://sermonator-test.local/wp-admin/ -o /tmp/favr-dash.html
```
(If `admin` access needs the `auth` cookie too, generate `'auth'` scheme for `wordpress_<COOKIEHASH>` as well.)
Check: `grep -c 'favr-dash' /tmp/favr-dash.html` > 0; `grep -c 'dashboard-widgets-wrap\|welcome-panel\|notice ' /tmp/favr-dash.html` = 0. Repeat as an Administrator: the stock dashboard (`dashboard-widgets-wrap`) is present.
Screenshot desktop and phone widths with headless Chrome against the saved HTML (asset URLs are absolute to the local site):
```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --window-size=1440,1100 --screenshot=/tmp/favr-dash-desktop.png file:///tmp/favr-dash.html
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --window-size=390,1600 --screenshot=/tmp/favr-dash-phone.png file:///tmp/favr-dash.html
```
Look at both screenshots; fix anything visibly off (one pass).

- [ ] **Step 10: Commit**
```bash
git add -A && git commit -m "feat: the Favr dashboard screen for editors"
```

---

### Task 6: Cards and quick actions from Directory, Members and Events

Each is a small class in the sibling repo, hooked from its `Plugin::boot()` with `( new Integration\FavrSites() )->hook();`. They only add filters, so nothing runs unless Favr Sites asks.

**Files:**
- Create: `../favrdir/src/Integration/FavrSites.php`, `../favrmembers/src/Integration/FavrSites.php`, `../favrevents/src/Integration/FavrSites.php`
- Modify: each repo's `src/Plugin.php` (one line), version bump (Directory 1.2.0, Members minor, Events 1.1.0) in the main file + `readme.txt` changelog line.

- [ ] **Step 1: Directory** — `../favrdir/src/Integration/FavrSites.php`
```php
<?php
/**
 * Favr dashboard card and quick action (Favr Sites plugin).
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration;

use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Settings;

/**
 * Contributes plain data to the Favr dashboard; inert when Favr Sites isn't installed.
 */
final class FavrSites {

	/** Hooks. */
	public function hook(): void {
		add_filter( 'favr_sites_quick_actions', array( $this, 'actions' ) );
		add_filter( 'favr_sites_dashboard_cards', array( $this, 'cards' ) );
	}

	/**
	 * Quick action.
	 *
	 * @param array<mixed> $actions Actions.
	 * @return array<mixed>
	 */
	public function actions( array $actions ): array {
		$actions[] = array(
			'id'         => 'add-listing',
			/* translators: %s: singular noun, e.g. "listing" or "member". */
			'label'      => sprintf( __( 'Add %s', 'favr-directory' ), Settings::noun( false ) ),
			'url'        => admin_url( 'post-new.php?post_type=' . ID::POST_TYPE ),
			'capability' => 'edit_' . ID::CAP_TYPE_PLURAL,
			'icon'       => 'store',
			'priority'   => 40,
		);
		return $actions;
	}

	/**
	 * Card (built lazily).
	 *
	 * @param array<mixed> $cards Cards.
	 * @return array<mixed>
	 */
	public function cards( array $cards ): array {
		$cards[] = array( $this, 'card' );
		return $cards;
	}

	/**
	 * The directory card.
	 *
	 * @return array<string, mixed>
	 */
	public function card(): array {
		$list   = admin_url( 'edit.php?post_type=' . ID::POST_TYPE );
		$count  = (int) ( wp_count_posts( ID::POST_TYPE )->publish ?? 0 );
		$newest = get_posts( array( 'post_type' => ID::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 3, 'orderby' => 'date', 'order' => 'DESC' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		return array(
			'id'         => 'directory',
			'title'      => __( 'Directory', 'favr-directory' ),
			'capability' => 'edit_' . ID::CAP_TYPE_PLURAL,
			'priority'   => 20,
			'stats'      => $count ? array( array( 'label' => Settings::noun( true ), 'value' => $count, 'url' => $list ) ) : array(),
			'items'      => array_map(
				static fn( \WP_Post $post ): array => array(
					'title' => get_the_title( $post ),
					/* translators: %s: date. */
					'meta'  => sprintf( __( 'added %s', 'favr-directory' ), get_the_date( 'M j', $post ) ),
					'url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
				),
				$newest
			),
			'link'       => array( 'label' => __( 'Open the directory', 'favr-directory' ), 'url' => $list ),
			'empty'      => array( 'text' => __( 'No listings yet.', 'favr-directory' ), 'label' => __( 'Add the first one', 'favr-directory' ), 'url' => admin_url( 'post-new.php?post_type=' . ID::POST_TYPE ) ),
		);
	}
}
```
Check `Settings::noun()` signature in `../favrdir/src/Settings.php` before use (it exists from 1.1.0: `noun( bool $plural )`).

- [ ] **Step 2: Events** — `../favrevents/src/Integration/FavrSites.php`: same shape. Action `add-event` → `post-new.php?post_type=favr_event`, capability `edit_favr_events`, icon `calendar`, priority 30. Card `events`, priority 30, capability `edit_favr_events`: `$next = Model\Repository::upcoming( 3 )`; stats `array( 'label' => __( 'upcoming', 'favr-events' ), 'value' => count( Model\Repository::upcoming( 50 ) ) )` when > 0; items from `$next`: `'title' => $row['event']->title(), 'meta' => wp_date( 'D j M', $row['start']->getTimestamp() ), 'url' => get_edit_post_link( $row['event']->id(), 'raw' )`; link "All events" → `edit.php?post_type=favr_event`; empty "No upcoming events." + "Add an event".

- [ ] **Step 3: Members** — `../favrmembers/src/Integration/FavrSites.php`: action `add-member` → `post-new.php?post_type=favr_member`, capability `edit_favr_members`, icon `users`, priority 50. Card `members`, priority 40, capability `edit_favr_members`: `$active = Model\Repository::statusCounts()[ ID::STATUS_ACTIVE ] ?? 0`; renewals due = `get_posts` of `favr_member` with meta `ID::meta( 'renewal_date' )` BETWEEN `wp_date( 'Y-m-d' )` and `wp_date( 'Y-m-d', strtotime( '+30 days' ) )` (type DATE, orderby meta_value ASC, 3 rows) plus a count via `'fields' => 'ids', 'posts_per_page' => -1`; stats `active` and `renewals due in 30 days`; items: name (`Member::find( $id )->name()`) + "renews {M j}"; link "All members"; empty "No members yet." + "Add a member". Confirm the renewal date is stored as `Y-m-d` by reading `../favrmembers/src/Model/Fields.php` (field `renewal_date`, type `date`).

- [ ] **Step 4: Each repo: test, lint, commit**

In each of `../favrdir`, `../favrevents`, `../favrmembers`:
```bash
composer test && composer lint
git add -A && git commit -m "feat: Favr dashboard card and quick action"
```

- [ ] **Step 5: Real check** — reload the Editor dashboard on sermonator-test (curl as in Task 5 Step 9); the three cards and quick actions appear for an Editor who has those capabilities. Screenshot again.

---

### Task 7: Docs, CI, publish

- [ ] **Step 1:** `README.md` (what it is, who sees it, the two filters with the examples from the spec, the help constants), `CLAUDE.md` (same shape as siblings: commands, architecture bullets, conventions), `.github/workflows/ci.yml` (copy `../favrevents/.github/workflows/ci.yml`, drop the "Prefixed library" step and use `find src templates -name '*.php'`).
- [ ] **Step 2:** `composer test && composer lint`; commit `docs: README, CLAUDE.md and CI`.
- [ ] **Step 3:** Create the GitHub repo `26am/favr-sites` (public, like the siblings) and push `main`; push the three sibling repos.
- [ ] **Step 4:** Build `~/Downloads/favr-plugins/favr-sites.zip` (and the three bumped siblings) with `git archive --prefix=<slug>/ -o … HEAD`.
