# Alert Banner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Favr "Alert banner" screen where Editors put one message across the top of every public page, optionally scheduled, and take it down again.

**Architecture:** A pure, immutable `Banner\Banner` value carries every rule (cleaning a submitted form, off/pending/live, the browser's time window, the dismissal version) and is unit-tested with `Banner\Colors`. `Banner\Store` reads and writes one autoloaded option. `Banner\Screen` is a Favr admin screen saving through `admin-post.php`. `Banner\Display` prints the banner on `wp_body_open` with a small inline script that re-checks the times and the dismissal in the browser, so cached pages stay correct.

**Tech Stack:** PHP 8.1+, WordPress 6.7+, PHPUnit 9 + Brain Monkey, WPCS. No runtime dependencies, no build step, no favr/core.

**Spec:** `docs/superpowers/specs/2026-10-06-alert-banner-design.md`

## Global Constraints

- Work on branch `feature/alert-banner` in `~/Repo/favrsite/favrsites`. Namespace `FavrSites\`, text domain `favr-sites`, PSR-4 classes in `src/`, camelCase methods, WPCS formatting (tabs).
- Screen `admin.php?page=favr-banner`, capability `edit_pages`, menu position 22.
- Option `favr_sites_banner`, autoloaded. One banner per site.
- Message: plain text, at most 200 characters. Link label: at most 40 characters. Link address: whatever `FavrSites\Menus\Tree::cleanUrl()` accepts.
- Styles: `standard` (Elementor kit Primary, else `#2E2230`; text `#ffffff` or `#1a1a1a` by contrast) and `urgent` (`#B42318` with `#ffffff`).
- Times are stored as local `Y-m-d H:i` strings in the site time zone.
- Not shown in wp-admin, feeds or the Elementor editor preview.
- Saving fires `litespeed_purge_all` and `favr_sites_banner_saved`.
- Editor menu order: Dashboard, Pages, News, Header & Footer, Alert banner, Media, Approvals, Directory, Events, Members, Documents, People, Sponsors, Profile.
- Templates escape everything at output. Never load `action=elementor` on a page that isn't built with Elementor.
- `composer test` green and `composer lint` at 0 errors before every commit. Commit messages follow the repo (`feat:`, `fix:`, `docs:`) and end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

- A message containing HTML or a script tag → stored and shown as plain text (test in Task 1).
- A `datetime-local` value with seconds, or nonsense such as `2026-13-45T99:99` → the first is accepted, the second is ignored, nothing fatals (test in Task 1).
- A corrupted or non-array `favr_sites_banner` option → the site renders with no banner (test in Task 2).
- An Elementor kit colour that is not a plain hex value (`rgba(…)`, 8-digit hex) → the fallback colour, readable text (test in Task 3).
- A theme that never fires `wp_body_open` → the banner still appears at the top of the page, once (real check in Task 4).

---

### Task 1: `Banner\Colors` and `Banner\Banner` (pure)

**Files:** Create `src/Banner/Colors.php`, `src/Banner/Banner.php`; Test `tests/Unit/BannerColorsTest.php`, `tests/Unit/BannerTest.php`.

**Interfaces — Produces:**
- `Colors::valid( string $hex ): bool` — true for `#RGB` and `#RRGGBB` only.
- `Colors::textOn( string $hex ): string` — `#ffffff` or `#1a1a1a`, whichever has the higher WCAG contrast ratio against `$hex`; `#ffffff` when `$hex` is not valid.
- `Banner::STYLES = array( 'standard', 'urgent' )`, `Banner::MESSAGE_MAX = 200`, `Banner::LABEL_MAX = 40`.
- `Banner::fromArray( $data ): self` — any non-array is treated as `array()`, and any non-scalar value inside it as missing. Keys: `enabled`, `message`, `link_label`, `link_url`, `style`, `starts`, `ends`, `dismissible`, `updated`. Defaults: `enabled` false, `style` `standard`, `dismissible` true, `updated` 0, strings empty.
- `Banner::clean( array $input, callable $clean_url, int $now ): array{banner: Banner, warnings: list<string>}` — `$clean_url( string ): string` returns `''` for an address it rejects. A checkbox key that is absent means off.
- `$banner->state( \DateTimeImmutable $now, \DateTimeZone $zone ): string` — `off`, `pending` or `live`.
- `$banner->window( \DateTimeZone $zone ): array{from: ?int, until: ?int}` — UTC timestamps.
- `$banner->version(): string` — 10 hex characters.
- `$banner->toArray(): array` (the stored shape), and getters `enabled(): bool`, `message(): string`, `linkLabel(): string`, `linkUrl(): string`, `style(): string`, `starts(): string`, `ends(): string`, `dismissible(): bool`, `updated(): int`.

- [ ] **Step 1: Failing tests**

`tests/Unit/BannerColorsTest.php`:
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Colors;

final class BannerColorsTest extends TestCase {

	public function test_only_plain_hex_colours_are_valid(): void {
		$this->assertTrue( Colors::valid( '#B42318' ) );
		$this->assertTrue( Colors::valid( '#fff' ) );
		$this->assertFalse( Colors::valid( 'B42318' ) );
		$this->assertFalse( Colors::valid( '#B42318CC' ) );
		$this->assertFalse( Colors::valid( 'rgba(0,0,0,.5)' ) );
		$this->assertFalse( Colors::valid( '' ) );
	}

	public function test_text_colour_follows_contrast(): void {
		$this->assertSame( '#ffffff', Colors::textOn( '#2E2230' ) );
		$this->assertSame( '#ffffff', Colors::textOn( '#B42318' ) );
		$this->assertSame( '#1a1a1a', Colors::textOn( '#6EC1E4' ) );
		$this->assertSame( '#1a1a1a', Colors::textOn( '#fff' ) );
		$this->assertSame( '#ffffff', Colors::textOn( 'nonsense' ) );
	}
}
```

`tests/Unit/BannerTest.php`:
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Banner;

final class BannerTest extends TestCase {

	private const NOW = 1791216000;

	private static function clean( array $input, int $now = self::NOW ): array {
		$url = static fn( string $value ): string => preg_match( '#^(https://|mailto:|tel:|/[^/])#', $value ) ? $value : '';
		return Banner::clean( $input, $url, $now );
	}

	private static function at( string $local, string $zone = 'America/New_York' ): \DateTimeImmutable {
		return new \DateTimeImmutable( $local, new \DateTimeZone( $zone ) );
	}

	public function test_defaults_are_off_standard_and_closable(): void {
		foreach ( array( array(), 'nonsense', null ) as $stored ) {
			$banner = Banner::fromArray( $stored );
			$this->assertFalse( $banner->enabled() );
			$this->assertSame( '', $banner->message() );
			$this->assertSame( 'standard', $banner->style() );
			$this->assertTrue( $banner->dismissible() );
		}
	}

	public function test_non_scalar_stored_values_count_as_missing(): void {
		$banner = Banner::fromArray( array( 'enabled' => array( 'x' ), 'message' => array( 'y' ), 'style' => array(), 'starts' => 5, 'updated' => 'soon' ) );
		$this->assertFalse( $banner->enabled() );
		$this->assertSame( '', $banner->message() );
		$this->assertSame( 'standard', $banner->style() );
		$this->assertSame( '', $banner->starts() );
		$this->assertSame( 0, $banner->updated() );
	}

	public function test_message_is_plain_text_trimmed_and_limited(): void {
		$banner = self::clean( array( 'message' => "  <b>Office closed</b>\n today <script>alert(1)</script> " ) )['banner'];
		$this->assertSame( 'Office closed today alert(1)', $banner->message() );

		$long = self::clean( array( 'message' => str_repeat( 'é', 250 ) ) )['banner'];
		$this->assertSame( 200, mb_strlen( $long->message() ) );
	}

	public function test_link_needs_an_address_and_gets_a_default_label(): void {
		$both = self::clean( array( 'link_label' => str_repeat( 'a', 60 ), 'link_url' => 'https://example.com/x' ) )['banner'];
		$this->assertSame( 40, mb_strlen( $both->linkLabel() ) );
		$this->assertSame( 'https://example.com/x', $both->linkUrl() );

		$no_label = self::clean( array( 'link_url' => '/closures/' ) )['banner'];
		$this->assertSame( 'Learn more', $no_label->linkLabel() );

		$no_url = self::clean( array( 'link_label' => 'Details' ) );
		$this->assertSame( '', $no_url['banner']->linkLabel() );
		$this->assertSame( array(), $no_url['warnings'] );

		$bad = self::clean( array( 'link_label' => 'Details', 'link_url' => 'javascript:alert(1)' ) );
		$this->assertSame( '', $bad['banner']->linkUrl() );
		$this->assertSame( '', $bad['banner']->linkLabel() );
		$this->assertCount( 1, $bad['warnings'] );
	}

	public function test_style_is_whitelisted(): void {
		$this->assertSame( 'urgent', self::clean( array( 'style' => 'urgent' ) )['banner']->style() );
		$this->assertSame( 'standard', self::clean( array( 'style' => 'rainbow' ) )['banner']->style() );
	}

	public function test_times_come_from_datetime_local_inputs(): void {
		$this->assertSame( '2026-10-07 09:30', self::clean( array( 'starts' => '2026-10-07T09:30' ) )['banner']->starts() );
		$this->assertSame( '2026-10-07 09:30', self::clean( array( 'starts' => '2026-10-07T09:30:15' ) )['banner']->starts() );
		$this->assertSame( '', self::clean( array( 'starts' => '2026-13-45T99:99' ) )['banner']->starts() );
		$this->assertSame( '', self::clean( array( 'starts' => 'tomorrow' ) )['banner']->starts() );
		$this->assertSame( '', self::clean( array( 'starts' => array( 'x' ) ) )['banner']->starts() );
	}

	public function test_hide_time_must_be_after_show_time(): void {
		foreach ( array( '2026-10-07T09:00', '2026-10-07T08:00' ) as $ends ) {
			$out = self::clean( array( 'starts' => '2026-10-07T09:00', 'ends' => $ends ) );
			$this->assertSame( '2026-10-07 09:00', $out['banner']->starts() );
			$this->assertSame( '', $out['banner']->ends() );
			$this->assertCount( 1, $out['warnings'] );
		}
		$ok = self::clean( array( 'starts' => '2026-10-07T09:00', 'ends' => '2026-10-07T17:00' ) );
		$this->assertSame( '2026-10-07 17:00', $ok['banner']->ends() );
		$this->assertSame( array(), $ok['warnings'] );
	}

	public function test_absent_checkboxes_are_off(): void {
		$off = self::clean( array( 'message' => 'Hi' ) )['banner'];
		$this->assertFalse( $off->enabled() );
		$this->assertFalse( $off->dismissible() );
		$on = self::clean( array( 'message' => 'Hi', 'enabled' => '1', 'dismissible' => '1' ) )['banner'];
		$this->assertTrue( $on->enabled() );
		$this->assertTrue( $on->dismissible() );
	}

	public function test_state_follows_the_window_in_the_site_time_zone(): void {
		$zone   = new \DateTimeZone( 'America/New_York' );
		$banner = self::clean( array( 'enabled' => '1', 'message' => 'Hi', 'starts' => '2026-10-07T09:00', 'ends' => '2026-10-07T17:00' ) )['banner'];
		$this->assertSame( 'pending', $banner->state( self::at( '2026-10-07 08:59' ), $zone ) );
		$this->assertSame( 'live', $banner->state( self::at( '2026-10-07 09:00' ), $zone ) );
		$this->assertSame( 'live', $banner->state( self::at( '2026-10-07 16:59' ), $zone ) );
		$this->assertSame( 'off', $banner->state( self::at( '2026-10-07 17:00' ), $zone ) );
		// The same instant expressed in another zone gives the same answer.
		$this->assertSame( 'live', $banner->state( self::at( '2026-10-07 13:00', 'UTC' ), $zone ) );

		$always = self::clean( array( 'enabled' => '1', 'message' => 'Hi' ) )['banner'];
		$this->assertSame( 'live', $always->state( self::at( '2030-01-01 00:00' ), $zone ) );
		$this->assertSame( 'off', self::clean( array( 'message' => 'Hi' ) )['banner']->state( self::at( '2026-10-07 12:00' ), $zone ) );
		$this->assertSame( 'off', self::clean( array( 'enabled' => '1', 'message' => '   ' ) )['banner']->state( self::at( '2026-10-07 12:00' ), $zone ) );
	}

	public function test_window_gives_utc_timestamps(): void {
		$zone   = new \DateTimeZone( 'America/New_York' );
		$banner = self::clean( array( 'starts' => '2026-10-07T09:00' ) )['banner'];
		$window = $banner->window( $zone );
		$this->assertSame( ( new \DateTimeImmutable( '2026-10-07 13:00', new \DateTimeZone( 'UTC' ) ) )->getTimestamp(), $window['from'] );
		$this->assertNull( $window['until'] );
	}

	public function test_version_changes_with_every_save(): void {
		$first  = self::clean( array( 'message' => 'Hi' ), 100 )['banner'];
		$again  = self::clean( array( 'message' => 'Hi' ), 100 )['banner'];
		$second = self::clean( array( 'message' => 'Hi' ), 200 )['banner'];
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{10}$/', $first->version() );
		$this->assertSame( $first->version(), $again->version() );
		$this->assertNotSame( $first->version(), $second->version() );
	}

	public function test_stored_shape_round_trips(): void {
		$banner = self::clean( array( 'enabled' => '1', 'message' => 'Hi', 'link_url' => '/x', 'style' => 'urgent', 'starts' => '2026-10-07T09:00', 'dismissible' => '1' ) )['banner'];
		$this->assertSame( $banner->toArray(), Banner::fromArray( $banner->toArray() )->toArray() );
		$this->assertSame( self::NOW, $banner->updated() );
	}
}
```

- [ ] **Step 2:** `composer test -- --filter 'BannerTest|BannerColorsTest'` → FAIL (classes not found).

- [ ] **Step 3: Implement `src/Banner/Colors.php`.** `valid()`: `1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $hex )`. `textOn()`: return `#ffffff` if not valid; expand `#RGB` to `#RRGGBB`; relative luminance `L = 0.2126 R + 0.7152 G + 0.0722 B` where each channel `c = value / 255` becomes `c / 12.92` when `c <= 0.03928`, else `( ( c + 0.055 ) / 1.055 ) ** 2.4`; contrast with white is `1.05 / ( L + 0.05 )`; contrast with `#1a1a1a` is `( L + 0.05 ) / ( Ld + 0.05 )` with `Ld` computed the same way for `#1a1a1a`; return `#1a1a1a` only when its contrast is strictly higher.

- [ ] **Step 4: Implement `src/Banner/Banner.php`** as a `final class` with a private constructor holding the nine values.
  - `fromArray()`: read each key through a private `scalar( array $data, string $key )` that returns the value only when `is_scalar()`, else `null`; cast strings with `(string)`, `enabled` with `(bool)` (so a stored array is false), `updated` with `(int)` only when `is_numeric()`, else 0; `style` falls back to `standard` unless in `STYLES`; `starts`/`ends` pass through `self::time()`; `dismissible` is `true` when the key is absent, else `(bool)`.
  - Private `time( $raw ): string`: non-strings → `''`; match `/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::\d{2})?$/`; build `"$1 $2"`, parse with `\DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $value )` and return it only if the parse succeeded **and** `->format( 'Y-m-d H:i' ) === $value` (this rejects month 13 and hour 99).
  - `clean()`: `message` = `mb_substr( sanitize_text_field( (string) ( $input['message'] ?? '' ) ), 0, self::MESSAGE_MAX )`; label the same with `LABEL_MAX`; `$raw_url = trim( (string) ( $input['link_url'] ?? '' ) )`, `$url = '' === $raw_url ? '' : (string) $clean_url( $raw_url )`; if `$raw_url` was not empty and `$url` is empty add the warning `__( 'The link address can’t be used, so the link was not saved.', 'favr-sites' )`; if `$url` is empty the label becomes `''`, else an empty label becomes `__( 'Learn more', 'favr-sites' )`. Non-scalar inputs are treated as empty (`is_scalar()` check before casting). If both times are set and `ends <= starts` (string comparison is correct for this format) clear `ends` and add `__( 'The hide time was not after the show time, so it was not saved.', 'favr-sites' )`. `enabled` and `dismissible` are `! empty( $input[…] )`. `updated` is `$now`.
  - `window()`: each non-empty time → `( new \DateTimeImmutable( $time, $zone ) )->getTimestamp()`, else `null`.
  - `state()`: `off` when not enabled or the message is empty; with `$ts = $now->getTimestamp()`: `off` when `until` is set and `$ts >= until`; `pending` when `from` is set and `$ts < from`; else `live`.
  - `version()`: `substr( md5( implode( '|', array( message, linkLabel, linkUrl, style, starts, ends, dismissible ? '1' : '0', (string) updated ) ) ), 0, 10 )`.

- [ ] **Step 5:** `composer test && composer lint` → green, 0 errors.

- [ ] **Step 6: Commit** `feat: alert banner rules (clean, state, window, version, colours)`.

---

### Task 2: Store, the Alert banner screen, menu and dashboard

**Files:** Create `src/Banner/Store.php`, `src/Banner/Screen.php`, `templates/banner-screen.php`, `assets/banner/screen.css`; Modify `src/Plugin.php`, `src/Editors/Menu.php` (`ORDER`), `src/Dashboard/Activity.php` (`TYPES`), `src/Dashboard/Icons.php` (`PATHS`), `src/Dashboard/Screen.php` (`coreActions()`); Test `tests/Unit/BannerStoreTest.php`, `tests/Unit/BannerScreenTest.php`, `tests/Unit/MenuTest.php`.

**Interfaces — Consumes:** `Banner` from Task 1; `FavrSites\Menus\Tree::cleanUrl( string ): string`; `FavrSites\Dashboard\Icons::svg()`.

**Interfaces — Produces:**
- `Store::OPTION = 'favr_sites_banner'`; `Store::get(): Banner`; `Store::save( Banner $banner ): void`.
- `Screen::PAGE = 'favr-banner'`; `Screen::status( Banner $banner, \DateTimeImmutable $now, \DateTimeZone $zone, callable $format ): string` where `$format( int $timestamp ): string`.

- [ ] **Step 1: Failing tests**

`tests/Unit/BannerStoreTest.php`:
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Banner\Banner;
use FavrSites\Banner\Store;

final class BannerStoreTest extends TestCase {

	public function test_a_corrupted_option_means_no_banner(): void {
		foreach ( array( 'nonsense', false, 42, array( 'enabled' => array( 'x' ), 'message' => array( 'y' ) ) ) as $stored ) {
			Functions\when( 'get_option' )->justReturn( $stored );
			$this->assertSame( 'off', Store::get()->state( new \DateTimeImmutable( 'now' ), new \DateTimeZone( 'UTC' ) ) );
		}
	}

	public function test_save_writes_an_autoloaded_option_and_tells_caches(): void {
		$saved = null;
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value, $autoload ) use ( &$saved ) {
				$saved = array( $name, $value, $autoload );
				return true;
			}
		);
		$banner = Banner::fromArray( array( 'enabled' => true, 'message' => 'Hi' ) );
		Store::save( $banner );
		$this->assertSame( array( 'favr_sites_banner', $banner->toArray(), true ), $saved );
		$this->assertSame( 1, did_action( 'litespeed_purge_all' ) );
		$this->assertSame( 1, did_action( 'favr_sites_banner_saved' ) );
	}
}
```
`tests/Unit/BannerScreenTest.php`:
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Banner;
use FavrSites\Banner\Screen;

final class BannerScreenTest extends TestCase {

	private static function status( array $stored, string $now ): string {
		$zone   = new \DateTimeZone( 'UTC' );
		$format = static fn( int $timestamp ): string => gmdate( 'j M H:i', $timestamp );
		return Screen::status( Banner::fromArray( $stored ), new \DateTimeImmutable( $now, $zone ), $zone, $format );
	}

	public function test_status_line_for_every_state(): void {
		$on = array( 'enabled' => true, 'message' => 'Hi' );
		$this->assertSame( 'The banner is off.', self::status( array( 'message' => 'Hi' ), '2026-10-07 12:00' ) );
		$this->assertSame( 'Add a message to show the banner.', self::status( array( 'enabled' => true ), '2026-10-07 12:00' ) );
		$this->assertSame( 'The banner is showing now.', self::status( $on, '2026-10-07 12:00' ) );
		$this->assertSame( 'The banner is showing now. It will hide on 7 Oct 17:00.', self::status( $on + array( 'ends' => '2026-10-07 17:00' ), '2026-10-07 12:00' ) );
		$this->assertSame( 'The banner will show from 7 Oct 09:00.', self::status( $on + array( 'starts' => '2026-10-07 09:00' ), '2026-10-07 08:00' ) );
		$this->assertSame( 'The banner stopped showing on 7 Oct 17:00.', self::status( $on + array( 'ends' => '2026-10-07 17:00' ), '2026-10-07 18:00' ) );
	}
}
```

In `tests/Unit/MenuTest.php`, replace the first test's input and expectation:
```php
	public function test_keeps_known_entries_in_favr_order_and_removes_the_rest(): void {
		$slugs = array( 'index.php', 'separator1', 'favr-menus', 'favr-banner', 'edit.php', 'upload.php', 'edit.php?post_type=page', 'favr-approvals', 'edit-comments.php', 'edit.php?post_type=elementor_library', 'edit.php?post_type=favr_sponsor', 'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event', 'edit.php?post_type=favr_person', 'edit.php?post_type=favr_member', 'edit.php?post_type=favr_document', 'elementor', 'separator2', 'profile.php', 'tools.php', 'wpseo_workouts' );
		$out   = Menu::arrange( $slugs );
		$this->assertSame( array( 'index.php', 'edit.php?post_type=page', 'edit.php', 'favr-menus', 'favr-banner', 'upload.php', 'favr-approvals', 'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event', 'edit.php?post_type=favr_member', 'edit.php?post_type=favr_document', 'edit.php?post_type=favr_person', 'edit.php?post_type=favr_sponsor', 'profile.php' ), $out['keep'] );
		$this->assertSame( array( 'separator1', 'edit-comments.php', 'edit.php?post_type=elementor_library', 'elementor', 'separator2', 'tools.php', 'wpseo_workouts' ), $out['remove'] );
	}
```

- [ ] **Step 2:** `composer test -- --filter 'BannerStoreTest|BannerScreenTest|MenuTest'` → FAIL.

- [ ] **Step 3: `src/Banner/Store.php`.** `get()` returns `Banner::fromArray( get_option( self::OPTION, array() ) )`. `save()` calls `update_option( self::OPTION, $banner->toArray(), true )`, then `do_action( 'litespeed_purge_all' )` (with `// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's purge hook; a no-op without it.`), then `do_action( 'favr_sites_banner_saved', $banner )` with a docblock: "Fires after the alert banner is saved. Purge other page caches here."

- [ ] **Step 4: Menu, activity and icon.** In `Editors\Menu::ORDER` insert `'favr-banner'` after `'favr-menus'`, and `'edit.php?post_type=favr_document'`, `'edit.php?post_type=favr_person'`, `'edit.php?post_type=favr_sponsor'` after `'edit.php?post_type=favr_member'`. In `Dashboard\Activity::TYPES` append `'favr_document', 'favr_person', 'favr_sponsor'`. In `Dashboard\Icons::PATHS` add `'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/>',`.

- [ ] **Step 5: `src/Banner/Screen.php`**, modelled on `Menus\Screen`.
  - Constants `PAGE = 'favr-banner'`, `private const SAVE = 'favr_sites_save_banner'`.
  - `hook()`: `admin_menu` → `add_menu_page( __( 'Alert banner', 'favr-sites' ), __( 'Alert banner', 'favr-sites' ), 'edit_pages', self::PAGE, array( $this, 'render' ), 'dashicons-megaphone', 22 )` and, when it returns a hook, `load-{hook}` → `load()`; `admin_post_` . `self::SAVE` → `save()`.
  - `load()`: enqueue `favr-sites-tokens` and `favr-sites-dashboard` exactly as `Menus\Screen::load()` does, plus `assets/banner/screen.css` (handle `favr-sites-banner-screen`, depends on `favr-sites-tokens`). Add body classes `favr-dash-screen favr-banner-screen`; blank `admin_footer_text` and `update_footer`.
  - `status()`: with `$window = $banner->window( $zone )` and `$state = $banner->state( $now, $zone )`: not enabled → `__( 'The banner is off.', 'favr-sites' )`; empty message → `__( 'Add a message to show the banner.', 'favr-sites' )`; `live` → `__( 'The banner is showing now.', 'favr-sites' )`, followed by a space and `sprintf( __( 'It will hide on %s.', 'favr-sites' ), $format( $window['until'] ) )` when `until` is set; `pending` → `sprintf( __( 'The banner will show from %s.', 'favr-sites' ), $format( $window['from'] ) )`; otherwise (ended) → `sprintf( __( 'The banner stopped showing on %s.', 'favr-sites' ), $format( $window['until'] ) )`. Each `sprintf` gets a `/* translators: %s: date and time. */` comment.
  - `render()`: builds `$view` = `banner` (`Store::get()`), `state`, `status` (`self::status( $banner, new \DateTimeImmutable( 'now', wp_timezone() ), wp_timezone(), static fn( int $t ): string => wp_date( 'j M Y, g:i a', $t ) )`), `zone` (`wp_timezone_string()`), `saved` (`isset( $_GET['saved'] )`), `warnings` (transient `favr_sites_banner_warnings_{user id}`, read then deleted), `action` (`self::SAVE`); then `include FAVR_SITES_PATH . 'templates/banner-screen.php'`.
  - `save()`: `check_admin_referer( self::SAVE )`; without `edit_pages` → `wp_die( esc_html__( 'Sorry, you are not allowed to edit the banner.', 'favr-sites' ), 403 )`; `$input = isset( $_POST['banner'] ) && is_array( $_POST['banner'] ) ? wp_unslash( $_POST['banner'] ) : array()` (phpcs:ignore `InputNotSanitized` — every field is cleaned by `Banner::clean()`); `$out = Banner::clean( $input, array( Tree::class, 'cleanUrl' ), time() )`; `Store::save( $out['banner'] )`; when there are warnings `set_transient( key, $out['warnings'], 60 )`; `wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&saved=1' ) ); exit;`.

- [ ] **Step 6: `templates/banner-screen.php`.** `defined( 'ABSPATH' ) || exit;`, then inside `<div class="wrap favr-dash favr-banner-admin">`:
  - Header: `<h1>` "Alert banner" and a sub line "One message across the top of every page. Switch it on when you need it."
  - Success notice when `$view['saved']` ("Banner saved.", `role="status"`, class `favr-banner-admin__notice is-success`), then each warning in a `favr-banner-admin__notice is-error` with `role="alert"`. (Copy the look of `.favr-menus__notice` from `assets/menus/menus.css` into `screen.css` under the new class name.)
  - A `favr-dash__card` with the status line (`<p class="favr-banner-admin__status" data-state="{state}">`). The preview is added in Task 3.
  - The form (`method="post"`, `action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"`), with `<input type="hidden" name="action" value="…">` and `wp_nonce_field( $view['action'] )`, and these fields, each a `<label>` with a visible text label:
    - `banner[enabled]` checkbox, value `1`: "Show the banner".
    - `banner[message]` `<textarea rows="2" maxlength="200">`: "Message", help "Plain text, up to 200 characters."
    - `banner[link_label]` text, `maxlength="40"`: "Link label (optional)"; `banner[link_url]` text: "Link address (optional)", placeholder `https://… or /page/`.
    - `banner[style]` two radios: "Standard (your site’s main colour)" value `standard`, "Urgent (red)" value `urgent`.
    - `banner[starts]` and `banner[ends]` `datetime-local`, values `str_replace( ' ', 'T', … )`: "Show from (optional)", "Hide after (optional)", with a note `sprintf( __( 'Times are in the site’s time zone (%s).', 'favr-sites' ), $view['zone'] )`.
    - `banner[dismissible]` checkbox, value `1`: "Visitors can close it".
    - A primary `button` "Save banner" and a link "View site" (`home_url( '/' )`, new tab).
  - Every dynamic value goes through `esc_html`, `esc_attr`, `esc_url` or `checked()`.

- [ ] **Step 7: `assets/banner/screen.css`.** Scoped under `.favr-banner-admin`: a single-column form with 16px gaps, labels in the dashboard's label style, inputs at `max-width: 560px`, the status line in bold with a coloured dot by `data-state` (`live` green `#1a7f37`, `pending` gold `var(--favr-gold, #E3A044)`, `off` grey `#8c8f94`), the preview with a 1px border and 8px radius, and the two notice variants. Use only tokens from `assets/dashboard/tokens.css` for brand colours.

- [ ] **Step 8: Wire it.** In `Plugin::boot()` add `( new Banner\Screen() )->hook();` inside the `is_admin()` block after `Menus\Screen`. In `Dashboard\Screen::coreActions()` append, before `return`:
```php
		$actions[] = array(
			'id'         => 'banner',
			'label'      => 'live' === Store::get()->state( new \DateTimeImmutable( 'now', wp_timezone() ), wp_timezone() ) ? __( 'Alert banner (on)', 'favr-sites' ) : __( 'Alert banner', 'favr-sites' ),
			'url'        => admin_url( 'admin.php?page=' . BannerScreen::PAGE ),
			'capability' => 'edit_pages',
			'icon'       => 'bell',
			'priority'   => 22,
		);
```
with `use FavrSites\Banner\Screen as BannerScreen;` and `use FavrSites\Banner\Store;`. If `tests/Unit/ScreenTest.php` stubs functions for `coreActions()`, add `get_option` → `array()` and `wp_timezone` → `new \DateTimeZone( 'UTC' )` there.

- [ ] **Step 9:** `composer test && composer lint` → green, 0 errors.

- [ ] **Step 10: Commit** `feat: Alert banner screen, store, Editor menu and dashboard action`.

---

### Task 3: `Banner\Display` (the public site)

**Files:** Create `src/Banner/Display.php`, `templates/banner.php`, `assets/banner/banner.css`, `assets/banner/banner.js`; Modify `src/Plugin.php`, `src/Banner/Screen.php` and `templates/banner-screen.php` (use the shared template and real colours); Test `tests/Unit/BannerDisplayTest.php`.

**Interfaces — Consumes:** `Store::get()`, `Banner`, `Colors`.

**Interfaces — Produces:**
- `Display::FALLBACK = '#2E2230'`, `Display::URGENT = '#B42318'`.
- `Display::colors( string $style, string $kit ): array{bg: string, fg: string}` (pure).
- `Display::kitColor(): string` (`''` when unavailable).
- `Display::markup( Banner $banner, string $state, array $colors, bool $late = false, bool $preview = false ): string`.

- [ ] **Step 1: Failing test** `tests/Unit/BannerDisplayTest.php`:
```php
<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Display;

final class BannerDisplayTest extends TestCase {

	public function test_urgent_is_always_red_with_white_text(): void {
		$this->assertSame( array( 'bg' => '#B42318', 'fg' => '#ffffff' ), Display::colors( 'urgent', '#6EC1E4' ) );
	}

	public function test_standard_uses_the_kit_colour_with_readable_text(): void {
		$this->assertSame( array( 'bg' => '#6EC1E4', 'fg' => '#1a1a1a' ), Display::colors( 'standard', '#6EC1E4' ) );
		$this->assertSame( array( 'bg' => '#123456', 'fg' => '#ffffff' ), Display::colors( 'standard', '#123456' ) );
	}

	public function test_anything_but_a_plain_hex_kit_colour_falls_back(): void {
		foreach ( array( '', 'rgba(1,2,3,.5)', '#6EC1E4CC', 'var(--x)' ) as $kit ) {
			$this->assertSame( array( 'bg' => '#2E2230', 'fg' => '#ffffff' ), Display::colors( 'standard', $kit ) );
		}
	}
}
```

- [ ] **Step 2:** `composer test -- --filter BannerDisplayTest` → FAIL.

- [ ] **Step 3: `templates/banner.php`.** Variables: `$banner` (Banner), `$state`, `$colors`, `$window` (`array{from, until}`), `$late` (bool), `$preview` (bool).
```php
<div class="favr-banner favr-banner--<?php echo esc_attr( $banner->style() ); ?>" role="region" aria-label="<?php esc_attr_e( 'Announcement', 'favr-sites' ); ?>"
	<?php if ( ! $preview ) : ?>
		data-favr-banner data-version="<?php echo esc_attr( $banner->version() ); ?>" data-from="<?php echo esc_attr( (string) ( $window['from'] ?? '' ) ); ?>" data-until="<?php echo esc_attr( (string) ( $window['until'] ?? '' ) ); ?>"<?php echo $late ? ' data-late' : ''; ?><?php echo 'pending' === $state ? ' hidden' : ''; ?>
	<?php endif; ?>
	style="--favr-banner-bg:<?php echo esc_attr( $colors['bg'] ); ?>;--favr-banner-fg:<?php echo esc_attr( $colors['fg'] ); ?>">
	<div class="favr-banner__inner">
		<p class="favr-banner__text">
			<?php echo esc_html( $banner->message() ); ?>
			<?php if ( '' !== $banner->linkUrl() ) : ?>
				<a class="favr-banner__link" href="<?php echo esc_url( $banner->linkUrl() ); ?>"><?php echo esc_html( $banner->linkLabel() ); ?></a>
			<?php endif; ?>
		</p>
		<?php if ( $banner->dismissible() ) : ?>
			<button type="button" class="favr-banner__close" aria-label="<?php esc_attr_e( 'Close', 'favr-sites' ); ?>"<?php echo $preview ? ' disabled' : ' hidden'; ?>>&times;</button>
		<?php endif; ?>
	</div>
</div>
```
(`esc_url()` keeps `mailto:`, `tel:` and `/path` values.)

- [ ] **Step 4: `assets/banner/banner.css`.**
```css
.favr-banner { background: var(--favr-banner-bg, #2E2230); color: var(--favr-banner-fg, #fff); font: 500 15px/1.4 inherit; }
.favr-banner[hidden] { display: none; }
.favr-banner__inner { display: flex; align-items: flex-start; gap: 12px; max-width: 1200px; margin: 0 auto; padding: 10px 16px; }
.favr-banner__text { flex: 1; margin: 0; text-align: center; color: inherit; }
.favr-banner__link { color: inherit; text-decoration: underline; font-weight: 700; margin-left: 6px; white-space: nowrap; }
.favr-banner__link:hover, .favr-banner__link:focus { color: inherit; text-decoration-thickness: 2px; }
.favr-banner__close { flex: none; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 4px; background: transparent; color: inherit; font-size: 22px; line-height: 1; cursor: pointer; }
.favr-banner__close:hover { background: rgba(127, 127, 127, 0.25); }
.favr-banner__close:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
.favr-banner__close[hidden] { display: none; }
@media (max-width: 600px) { .favr-banner__text { text-align: left; } .favr-banner__link { white-space: normal; } }
```

- [ ] **Step 5: `assets/banner/banner.js`** (printed inline, so no `DOMContentLoaded`):
```js
( function () {
	var el = document.querySelector( '[data-favr-banner]' );
	if ( ! el ) {
		return;
	}
	if ( el.hasAttribute( 'data-late' ) && document.body ) {
		document.body.insertBefore( el, document.body.firstChild );
	}
	var key = 'favrBanner';
	var version = el.getAttribute( 'data-version' );
	var from = parseInt( el.getAttribute( 'data-from' ), 10 );
	var until = parseInt( el.getAttribute( 'data-until' ), 10 );
	var now = Date.now() / 1000;
	var closed = false;
	try {
		closed = window.localStorage.getItem( key ) === version;
	} catch ( e ) {}
	el.hidden = closed || ( ! isNaN( from ) && now < from ) || ( ! isNaN( until ) && now >= until );
	var button = el.querySelector( '.favr-banner__close' );
	if ( button ) {
		button.hidden = false;
		button.addEventListener( 'click', function () {
			el.hidden = true;
			try {
				window.localStorage.setItem( key, version );
			} catch ( e ) {}
		} );
	}
} )();
```

- [ ] **Step 6: `src/Banner/Display.php`.**
  - `colors()`: `urgent` → `array( 'bg' => self::URGENT, 'fg' => '#ffffff' )`; otherwise `$bg = Colors::valid( $kit ) ? $kit : self::FALLBACK` and `array( 'bg' => $bg, 'fg' => Colors::textOn( $bg ) )`.
  - `kitColor()`: return `''` unless `did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' )`; inside `try { … } catch ( \Throwable $e ) { return ''; }` get `\Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend()`, read `(array) $kit->get_settings_for_display( 'system_colors' )` and return the `color` of the entry whose `_id` is `primary`; `''` otherwise.
  - `markup()`: `ob_start()`, set the template variables (`$window = $banner->window( wp_timezone() )`), `include FAVR_SITES_PATH . 'templates/banner.php'`, return the buffer.
  - Private `current(): ?array{banner: Banner, state: string}`: `null` when `is_admin()`, `is_feed()`, or `isset( $_GET['elementor-preview'] )` (phpcs:ignore `NonceVerification.Recommended` — display only); otherwise the banner and its state for `new \DateTimeImmutable( 'now', wp_timezone() )`, or `null` when the state is `off`. Memoize in a private property so the option is read once.
  - `hook()`: `wp_enqueue_scripts` → `enqueue()`; `wp_body_open` (priority 5) → `open()`; `wp_footer` (priority 5) → `footer()`.
  - `enqueue()`: when `current()` is not null, `wp_enqueue_style( 'favr-sites-banner', FAVR_SITES_URL . 'assets/banner/banner.css', array(), FAVR_SITES_VERSION )`.
  - `open()` and `footer()` both call private `output( bool $late )`: return if already printed (private `bool $printed`) or `current()` is null; set `$printed = true`; echo `self::markup( … , $late )` (phpcs:ignore `OutputNotEscaped` — the template escapes), then `wp_print_inline_script_tag( (string) file_get_contents( FAVR_SITES_PATH . 'assets/banner/banner.js' ) )` (phpcs:ignore `file_get_contents` — a local plugin file). `open()` passes `false`, `footer()` passes `true`.

- [ ] **Step 7: Preview on the screen.** In `Screen::load()` also enqueue `assets/banner/banner.css` (handle `favr-sites-banner`). In `Screen::render()` add `colors` to `$view` from `Display::colors( $banner->style(), Display::kitColor() )`. In `templates/banner-screen.php`, under the status line and only when the message is not empty, print `<div class="favr-banner-admin__preview">` containing `echo Display::markup( $view['banner'], 'live', $view['colors'], false, true );` (phpcs:ignore `OutputNotEscaped` — the template escapes). In `Plugin::boot()` add `( new Banner\Display() )->hook();` before the `is_admin()` block.

- [ ] **Step 8:** `composer test && composer lint` → green, 0 errors.

- [ ] **Step 9: Commit** `feat: alert banner on the public site (cache-safe times and dismissal)`.

---

### Task 4: Docs, version 0.9.0 and the real check

**Files:** Modify `favr-sites.php` (header `Version` and `FAVR_SITES_VERSION` → `0.9.0`), `README.md`, `CLAUDE.md`.

- [ ] **Step 1: Docs.** In `README.md` add a section after "Header & Footer" in the Editor experience list:
  `- **Alert banner** (0.9): one message across the top of every page, from a Favr screen. Plain text with an optional link, a Standard (site colour) or Urgent (red) style, optional show and hide times in the site's time zone, and a close button visitors' browsers remember until the banner is saved again. Printed on \`wp_body_open\`; the browser re-checks the times and the dismissal, so cached pages stay correct. Saving fires \`litespeed_purge_all\` and \`favr_sites_banner_saved\`.`
  Update the Menu line to the new order. In `CLAUDE.md` add under Architecture:
  `- \`Banner\*\` (0.9): \`Banner\` (pure value: \`clean()\` a submitted form, \`state()\` off/pending/live, \`window()\`, \`version()\`), \`Colors\`, \`Store\` (option \`favr_sites_banner\`, autoloaded), \`Screen\` (\`admin.php?page=favr-banner\`, cap \`edit_pages\`), \`Display\` (\`wp_body_open\`, footer fallback, inline \`assets/banner/banner.js\`). The stored times are local strings; keep \`banner.js\`'s window check in step with \`Banner::state()\`.`
  and change the Conventions line to "JS only for the Elementor editor tweak, the Menus screen and the banner's close button".

- [ ] **Step 2: Version.** Set both version strings to `0.9.0`. `composer test && composer lint`.

- [ ] **Step 3: Tools for the real check.** The local site is `http://sermonator-test.local/` (Local by WP Engine; this repo is already symlinked as `wp-content/plugins/favr-sites`). Editor user id 19, Administrator id 1. In a scratch directory:
```bash
SCRATCH="${SCRATCH:-${TMPDIR:-/tmp}/favr-check}"; mkdir -p "$SCRATCH"
[ -f "$SCRATCH/wp-cli.phar" ] || curl -sL -o "$SCRATCH/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
wp() { php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/xZ7KeYHRQ/mysql/mysqld.sock" "$SCRATCH/wp-cli.phar" --path="$HOME/Local Sites/sermonator-test/app/public" "$@" 2>/dev/null; }
cat > "$SCRATCH/cookies.php" <<'PHP'
<?php
$uid   = (int) $args[0];
$exp   = time() + 900;
$token = WP_Session_Tokens::get_instance( $uid )->create( $exp );
echo AUTH_COOKIE . '=' . wp_generate_auth_cookie( $uid, $exp, 'auth', $token ) . '; ' . LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $uid, $exp, 'logged_in', $token );
PHP
EDITOR_COOKIE="$(wp eval-file "$SCRATCH/cookies.php" 19 | tail -1)"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
shot() { "$CHROME" --headless=new --disable-gpu --hide-scrollbars --window-size="${3:-1280,900}" --screenshot="$SCRATCH/$1.png" "$2" >/dev/null 2>&1; }
```

- [ ] **Step 4: The screen, as the Editor.**
```bash
curl -s -b "$EDITOR_COOKIE" "http://sermonator-test.local/wp-admin/admin.php?page=favr-banner" -o "$SCRATCH/screen.html"
grep -c 'name="banner\[message\]"' "$SCRATCH/screen.html"     # 1
grep -o 'The banner is off\.' "$SCRATCH/screen.html"
NONCE="$(grep -o 'name="_wpnonce" value="[^"]*"' "$SCRATCH/screen.html" | head -1 | sed 's/.*value="//;s/"//')"
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' -b "$EDITOR_COOKIE" \
  --data-urlencode "action=favr_sites_save_banner" --data-urlencode "_wpnonce=$NONCE" \
  --data-urlencode "banner[enabled]=1" --data-urlencode "banner[message]=Office closed <b>Friday</b>" \
  --data-urlencode "banner[link_url]=/news/" --data-urlencode "banner[style]=urgent" --data-urlencode "banner[dismissible]=1" \
  "http://sermonator-test.local/wp-admin/admin-post.php"      # 302 …page=favr-banner&saved=1
wp option get favr_sites_banner --format=json                 # message "Office closed Friday", link_label "Learn more", style "urgent"
# Refusals: no nonce → 403; logged out → nothing happens (admin-post.php has no handler for visitors).
curl -s -o /dev/null -w '%{http_code}\n' -b "$EDITOR_COOKIE" --data "action=favr_sites_save_banner&banner[message]=x" "http://sermonator-test.local/wp-admin/admin-post.php"   # 403
curl -s -o /dev/null -w '%{http_code}\n' --data "action=favr_sites_save_banner&banner[message]=x" "http://sermonator-test.local/wp-admin/admin-post.php"                       # 200 with an empty body
wp option get favr_sites_banner --format=json | grep -c 'Office closed Friday'   # 1 (unchanged by either refusal)
```
Then, as the Editor, fetch `wp-admin/index.php` and confirm the quick action reads "Alert banner (on)", and fetch any wp-admin page and confirm the menu order from Global Constraints (the entries that exist on this site, in that order).

- [ ] **Step 5: The public site.**
```bash
curl -s http://sermonator-test.local/ | grep -c 'data-favr-banner'            # 1
curl -s http://sermonator-test.local/ | grep -o 'Office closed Friday'        # plain text, no <b>
curl -s http://sermonator-test.local/ | grep -c 'favr-sites-banner-css'       # 1 (stylesheet in <head>)
curl -s "http://sermonator-test.local/?elementor-preview=1" | grep -c 'data-favr-banner'   # 0
curl -s http://sermonator-test.local/feed/ | grep -c 'favr-banner'            # 0
shot banner-desktop http://sermonator-test.local/
shot banner-phone http://sermonator-test.local/ 500,900
```
Read both screenshots: the red bar is above the header, the text and "Learn more" are readable, the close button is at the right, and at 500px the message wraps without overlapping the button. Repeat `shot` on a page that uses the Elementor Pro header and on a page using Elementor's Canvas template (find one with `wp post list --post_type=page --meta_key=_wp_page_template --meta_value=elementor_canvas --fields=ID,post_title`; if none exists, set that template on the draft "Favr widgets demo (Elementor)" page with `wp post meta update <id> _wp_page_template elementor_canvas`, check its preview as the Editor, and set it back).

- [ ] **Step 6: Times.** With `wp eval` build a banner one minute ahead and check the pending state:
```bash
wp eval '$z = wp_timezone(); $s = ( new DateTimeImmutable( "+1 minute", $z ) )->format( "Y-m-d H:i" ); update_option( "favr_sites_banner", array( "enabled" => true, "message" => "Soon", "style" => "standard", "starts" => $s, "ends" => "", "dismissible" => true, "updated" => time() ) );'
curl -s http://sermonator-test.local/ | grep -o 'data-favr-banner[^>]*' | grep -c ' hidden'   # 1 (pending: printed but hidden)
sleep 70; curl -s http://sermonator-test.local/ | grep -o 'data-favr-banner[^>]*' | grep -c ' hidden'   # 0 (live)
wp eval '$z = wp_timezone(); $e = ( new DateTimeImmutable( "-1 minute", $z ) )->format( "Y-m-d H:i" ); update_option( "favr_sites_banner", array( "enabled" => true, "message" => "Gone", "style" => "standard", "starts" => "", "ends" => $e, "dismissible" => true, "updated" => time() ) );'
curl -s http://sermonator-test.local/ | grep -c 'data-favr-banner'            # 0 (ended)
```
Screenshot the Standard style once while it is live and read it: the bar uses the site's primary colour (or ink) with readable text.

- [ ] **Step 7: Closing, and a theme without `wp_body_open`.** Drive headless Chrome over the DevTools protocol (Node 22 has a built-in `WebSocket`; launch Chrome with `--remote-debugging-port=9222 --headless=new`): load the home page with a live banner, click `.favr-banner__close`, confirm `document.querySelector('[data-favr-banner]').hidden === true` and `localStorage.favrBanner` equals the element's `data-version`; reload and confirm it is still hidden; save the banner again from the screen (Step 4's POST) and confirm a reload shows it. Then drop a temporary must-use plugin into the local site and re-check:
```bash
MU="$HOME/Local Sites/sermonator-test/app/public/wp-content/mu-plugins"; mkdir -p "$MU"
printf '<?php\nadd_action( "wp_head", static function () { remove_all_actions( "wp_body_open" ); }, 999 );\n' > "$MU/favr-no-body-open.php"
curl -s http://sermonator-test.local/ | grep -c 'data-favr-banner'                       # 1 (exactly one)
curl -s http://sermonator-test.local/ | grep -o 'data-favr-banner[^>]*' | grep -c 'data-late'   # 1
shot banner-late http://sermonator-test.local/    # read it: the banner is at the top of the page
rm "$MU/favr-no-body-open.php"
```

- [ ] **Step 8: Leave the local site clean.** `wp option delete favr_sites_banner`. Fix anything the checks found (with a test where the rule is pure), re-run `composer test && composer lint`.

- [ ] **Step 9: Commit** `docs: alert banner; 0.9.0`. Merging to `main`, tagging `v0.9.0` and pushing are David's call: stop here and report.
