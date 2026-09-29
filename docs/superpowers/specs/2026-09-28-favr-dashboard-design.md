# Favr Sites: the Favr dashboard (v1)

Status: approved in conversation 2026-09-28, pending spec review.

## Why

Every Favr client (GOAABA, AACC, …) gets an Editor account on their WordPress site. The stock
dashboard greets them with WordPress, Yoast and Elementor widgets that have nothing to do with
their job. Favr Sites is the plugin that owns the experience that is the same on every client site.
Its first piece replaces that dashboard with a Favr home screen: what needs attention, what to do
next, what's happening, and where to get help.

Success: an Editor logs in and, without reading anything twice, knows what needs them and where
to click. No stock or third-party widgets, no plugin nags, no WordPress jargon.

## Decisions (from the design conversation)

| Question | Decision |
|---|---|
| Plugin name | **Favr Sites**, slug `favr-sites`, namespace `FavrSites\`, prefix `favr_sites_`. (`favr/core` stays the bundled library.) |
| Who sees it | Users with the Editor role (the role Favr clients get). Everyone else, Administrators included, keeps the stock dashboard. (Changed 2026-09-28 from "Editors and below".) |
| Takeover | Render on the existing `wp-admin/index.php` before WordPress sets up dashboard widgets. Same URL; no redirect. |
| Content | Needs your attention, Quick actions, Plugin summaries, Recent activity, Help. |
| Help | Links to a Favr help site that doesn't exist yet: URLs live in one setting with fleet defaults (care@favr.site, 407-889-9987). |
| Branding | Favr-branded. The client's logo and site name appear in the header for orientation only. |
| Brand assets | None yet. Favr Sites defines a restrained palette and type pair as tokens in one file. |

## Architecture

Standalone plugin at `~/Repo/favrsite/favrsites` (github.com/26am/favr-sites). PHP 8.1+,
WordPress 6.7+, no JS build step, GPL-2.0-or-later. Same tooling as the sibling plugins: PHPUnit
with Brain Monkey (`composer test`), PHPCS with WordPress Coding Standards (`composer lint`, 0
errors), a bundled PSR-4 autoloader so the plugin runs from a plain zip. No `favr/core` dependency
in v1.

Units (`src/`):

- `Plugin`: boots the units on `plugins_loaded`.
- `Dashboard\Audience`: pure decision "does this user get the Favr dashboard?". True when the user
  is logged in, has the `editor` role, lacks `manage_options`, and the `favr_sites_dashboard_enabled`
  filter (receives the default and the `WP_User`) agrees.
- `Dashboard\Takeover`: on `load-index.php`, if `Audience` says yes: registers the screen's assets,
  removes `admin_notices`/`all_admin_notices`/`network_admin_notices` callbacks for this request,
  hides Screen Options and Help tabs, then includes `admin-header.php`, renders `Screen`, includes
  `admin-footer.php` and exits. `wp_dashboard_setup()` never runs, so no widget (core or plugin) is
  built.
- `Dashboard\Screen`: builds the view model (header, attention, actions, cards, activity, help) and
  renders `templates/dashboard.php`. Templates escape at output.
- `Dashboard\Attention`: reads `favr_approvals_providers` (the existing plugin-neutral contract used
  by favr/core's Approvals inbox). Keeps providers the user can act on (`current_user_can(
  capability )`), calls each provider's `count`, drops zeros. Links to
  `admin.php?page=favr-approvals`.
- `Dashboard\Actions`: collects `favr_sites_quick_actions`, normalizes, drops items the user
  can't use, sorts by `priority` then label.
- `Dashboard\Cards`: collects `favr_sites_dashboard_cards`, normalizes, drops cards the user can't
  see, sorts. Each card is built in its own `try/catch (\Throwable)`: a failing card is skipped and
  logged with `error_log()` when `WP_DEBUG` is on.
- `Dashboard\Activity`: one `WP_Query` for the 10 most recently modified published/draft/pending
  items across `page`, `post`, and the Favr types that exist (`favr_business`, `favr_event`,
  `favr_member`), plus any added via `favr_sites_activity_post_types`. Only types whose
  `cap->edit_posts` the current user has are queried (member records never leak to someone who
  can't manage members). Author from `_edit_last`,
  falling back to `post_author`. Wording: "Anna updated About Us · 2 hours ago" /
  "Anna added Fall Happy Hour · yesterday".
- `Help\Links`: help centre URL, support email, support phone, booking URL. Resolution order:
  constant (`FAVR_SITES_HELP_URL`, `FAVR_SITES_SUPPORT_EMAIL`, `FAVR_SITES_SUPPORT_PHONE`,
  `FAVR_SITES_BOOKING_URL`) → site option `favr_sites_help` → fleet default in code. Empty values
  aren't shown.
- `Admin\SettingsPage`: Settings → Favr (Administrators only) for the four help fields. Settings
  API, sanitized (`esc_url_raw`, `sanitize_email`, `sanitize_text_field`).

Assets (`assets/dashboard/`): `tokens.css` (the only place Favr colors, type and spacing are
defined), `dashboard.css` (layout, scoped under `.favr-dash`), and self-hosted OFL woff2 fonts. No
JavaScript in v1.

## Contracts for the other plugins

All plain arrays: no HTML, no dependency on Favr Sites classes. A plugin that isn't installed
contributes nothing; a plugin works the same whether or not Favr Sites is installed.

```php
add_filter( 'favr_sites_quick_actions', function ( array $actions ): array {
	$actions[] = array(
		'id'         => 'add-event',          // unique
		'label'      => 'Add event',
		'url'        => admin_url( 'post-new.php?post_type=favr_event' ),
		'capability' => 'edit_favr_events',
		'icon'       => 'calendar',           // one of the bundled icon names; unknown → none
		'priority'   => 30,                   // lower first; default 50
	);
	return $actions;
} );

add_filter( 'favr_sites_dashboard_cards', function ( array $cards ): array {
	$cards[] = array(
		'id'         => 'events',
		'title'      => 'Events',
		'capability' => 'edit_favr_events',
		'priority'   => 30,
		'stats'      => array( array( 'label' => 'upcoming', 'value' => 2, 'url' => '…' ) ), // 0–3
		'items'      => array( array( 'title' => 'Fall Happy Hour', 'meta' => 'Thu 15 Oct', 'url' => '…' ) ), // 0–5
		'link'       => array( 'label' => 'All events', 'url' => '…' ),
		'empty'      => array( 'text' => 'No upcoming events.', 'label' => 'Add an event', 'url' => '…' ),
	);
	return $cards;
} );
```

Normalization rules (unit-tested): missing `id`/`title`/`label`/`url` → item dropped; non-string
values cast or dropped; `stats` capped at 3 and `items` at 5; `url` passed through `esc_url` at
render; `priority` defaults to 50; duplicate `id` → last one wins; `capability` empty → visible to
anyone who sees the dashboard.

Favr Sites contributes: quick actions "Add news post" (`edit_posts`) and "Edit pages"
(`edit_pages`). Each sibling plugin adds a small `Integration\FavrSites` class (Directory: listings
count, 3 newest; Members: active members, renewals due in 30 days; Events: upcoming count, next 3)
and its quick action. Those are separate, small changes in each plugin's repo.

## Screen

Order: header → Needs your attention (only if anything is waiting) → Quick actions → plugin cards
(grid, up to 3 columns) → Recent activity (wide) + Help (narrow). One column under 782px (the
wp-admin mobile breakpoint).

- Header: client logo (`custom_logo`, else site icon, else none), site name, "Good morning/
  afternoon/evening, {first name or display name}" by site timezone, "View site ↗". Small Favr
  wordmark on the right.
- Empty states say what happens next ("No events yet · Add your first event").
- Visible focus rings, `prefers-reduced-motion` respected, no emoji, tabular numerals for stats.
- Everything scoped under `.favr-dash`; the rest of wp-admin is untouched in v1.

Proposed tokens (to be replaced when the Favr brand is set): ink `#1B1F24`, muted `#5E636B`,
paper `#F6F6F3`, surface `#FFFFFF`, line `#E3E3DD`, accent `#1F6F5C` (pine), accent-soft `#E4F0EB`,
attention `#A5540A`. Type: Newsreader (greeting, card titles), Instrument Sans (UI). Both OFL,
self-hosted.

## Edge cases

- No Favr plugins active: header, core quick actions, activity and help still render.
- User can't approve anything: attention card hidden.
- No logo: site name only.
- A provider/card throws: that card is skipped; the page renders.
- Plugin deactivated: stock dashboard returns. Uninstall deletes the `favr_sites_help` option only.

## Testing

Unit (`tests/Unit`, Brain Monkey): Audience rule; Actions and Cards normalization, capability
filtering, sorting, dedupe; failing card isolation; Activity wording and relative time; Help
resolution order (constant → option → default).

Real check on the Local site `sermonator-test.local` (Favr Directory, Members and Events active)
as the existing Editor user: takeover renders, no stock/plugin widgets or notices, cards and
actions appear, Administrator still sees the stock dashboard. Screenshots at desktop and phone
width.

## Out of scope (queued)

Admin menu clean-up, login screen, admin bar, wider wp-admin branding, AI controls, Favr-staff
roles.
