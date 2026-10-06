# Favr Sites: Alert banner (v0.9)

Status: approved in conversation 2026-10-06, pending spec review.

## Why

"Site-wide alert banner" is on the Favr Sites feature list (2026-10-05) and nothing in the suite
provides it. Clients need to tell every visitor one thing quickly: the office is closed, an event
moved, registration ends Friday. Today that means asking Favr to edit the header.

Success: an Editor can put a message across the top of every page in under a minute, schedule it
to appear and disappear on its own, and take it down again, without touching a page design.

## Decisions

| Question | Decision |
|---|---|
| Where | A Favr "Alert banner" screen (`admin.php?page=favr-banner`), capability `edit_pages`, menu position 22 (right after Header & Footer). Administrators see it too, like Header & Footer. |
| How many | One banner per site. Option `favr_sites_banner`, autoloaded (it is read on every public page). No post type. |
| Content | Message: plain text, up to 200 characters. Optional link: label (up to 40 characters) and address (`https://`, `mailto:`, `tel:` or a `/path`, cleaned by `Menus\Tree::cleanUrl`). An address without a label gets "Learn more"; a label without an address is dropped. |
| Style | **Standard**: the site's main colour (Elementor Site Kit "Primary"), with black or white text chosen by contrast; ink `#2E2230` with white text when there is no kit colour. **Urgent**: `#B42318` with white text. |
| Schedule | Optional "Show from" and "Hide after" (`datetime-local`, site time zone, the zone named beside the fields). Stored as local `Y-m-d H:i` strings, like Favr Events. A hide time at or before the show time is not saved, with a warning. |
| Closing | "Visitors can close it" (on by default). Closing is remembered in that browser (`localStorage`) until the banner is saved again. |
| Placement | Above the header on every public page, in normal page flow (not sticky). Printed on `wp_body_open` (fired by Hello Elementor, Elementor Pro headers and Elementor's Canvas template; checked 2026-10-05). Not shown in wp-admin, feeds, REST or the Elementor editor preview. |
| Caching | The server prints the banner; the browser re-checks the show and hide times and the dismissal, so a cached page never shows a banner past its hide time. Saving fires `litespeed_purge_all` (a no-op without LiteSpeed Cache) and `favr_sites_banner_saved`. |
| Dashboard | A core quick action "Alert banner" ("Alert banner (on)" while it is showing), priority 22, new `bell` icon. |
| Editor menu | `Editors\Menu::ORDER` becomes Dashboard, Pages, News, Header & Footer, Alert banner, Media, Approvals, Directory, Events, Members, Documents, People, Sponsors, Profile. The last three are Favr Content's (separate spec); listing them here is inert until that plugin is installed. `Dashboard\Activity::TYPES` gains `favr_document`, `favr_person`, `favr_sponsor` the same way. |

## Units

- `Banner\Banner` (pure, unit-tested). An immutable value built from the stored array.
  - `fromArray( array $data ): self`: fills defaults (`enabled` false, `style` `standard`,
    `dismissible` true, everything else empty).
  - `clean( array $input, callable $clean_url, int $now ): array{banner: self, warnings: list<string>}`:
    submitted form → a banner. Trims and length-limits the message and link label, pairs the link
    as above, whitelists the style, parses the two times (anything unparseable becomes empty),
    drops a hide time that is not after the show time (warning), and stamps `updated` with `$now`.
  - `state( \DateTimeImmutable $now, \DateTimeZone $zone ): string`: `off` (not enabled, no
    message, or past the hide time), `pending` (enabled, before the show time) or `live`.
  - `window( \DateTimeZone $zone ): array{from: ?int, until: ?int}`: the two times as UTC
    timestamps for the browser.
  - `version(): string`: a short hash of the content and `updated`; the dismissal key.
  - `toArray(): array`.
- `Banner\Colors` (pure, unit-tested): `valid( string $hex ): bool`, `textOn( string $hex ): string`
  (`#ffffff` or `#1a1a1a`, whichever has the higher WCAG contrast ratio).
- `Banner\Store`: `get(): Banner`, `save( Banner $banner ): void` (updates the option, then the
  two cache actions).
- `Banner\Screen`: registers the page; renders `templates/banner-screen.php`; handles the save
  (`admin-post.php?action=favr_sites_save_banner`, nonce `favr_sites_save_banner`,
  `current_user_can( 'edit_pages' )`), then redirects back with "Banner saved." and any warnings.
  The screen shows a status line ("The banner is showing now." / "It will show from {date}." /
  "The banner is off." / "It stopped showing on {date}." / "Add a message to show the banner.")
  and a preview of the saved banner. Uses the dashboard tokens and styles, like Header & Footer.
- `Banner\Display` (front end):
  - `wp_enqueue_scripts`: when the state is `live` or `pending`, enqueues
    `assets/banner/banner.css` and adds the two colours as inline custom properties.
  - `wp_body_open` (priority 5): prints `templates/banner.php` (a `region` labelled "Announcement",
    `hidden` while pending, with `data-version`, `data-from`, `data-until`) followed by
    `assets/banner/banner.js` inline, so the banner is hidden before first paint when it was
    closed or is outside its window.
  - `wp_footer`: if `wp_body_open` never fired, prints the same markup there and the script moves
    it to the top of `<body>`.
  - The kit colour is read inside a guard (`class_exists`, try/catch); any failure means the
    fallback colour.
- `Dashboard\Screen::coreActions()` gains the banner action; `Dashboard\Icons` gains `bell`.
- `Plugin::boot()`: `Banner\Display` always; `Banner\Screen` in wp-admin.
- README and CLAUDE.md: the banner section; the JS note becomes "the Elementor editor tweak, the
  Menus screen and the banner's close button". Version 0.9.0.

## Edge cases

- Enabled with an empty message: treated as off; the screen says to add a message.
- JavaScript off: a live banner shows and cannot be closed; a pending banner appears on the first
  page rendered after its show time.
- `localStorage` unavailable (private browsing): closing lasts for that page view.
- The site's time zone changes: the stored local times follow the new zone.
- Long messages wrap; the close button stays at the top right. Checked at 500px.
- Two Editors saving: last save wins.
- Elementor inactive or no kit colour: fallback colour.
- A page cached by something other than LiteSpeed before the banner was switched on shows it when
  that cache refreshes. `favr_sites_banner_saved` is the hook for other caches.

## Testing

Unit: `Banner::clean` (trimming, length limits, link pairing, rejected addresses, style whitelist,
time parsing, hide-before-show), `Banner::state` at each boundary and in a non-UTC zone,
`Banner::window`, `Banner::version` (changes on every save), `Colors::textOn` and `Colors::valid`,
`Menu::arrange` with the new order.

Real check on sermonator-test as the Editor: switch the banner on and see it above the header on a
normal page, a page with the Elementor Pro header, and a Canvas page; Urgent style; a show time
one minute ahead (hidden, then appears); a past hide time; close it and reload; save again and see
it return; no banner in the Elementor editor; the menu order and the dashboard action. Screenshots
at desktop width and 500px.

## Out of scope

More than one banner, per-page or per-audience targeting, rich text, countdowns, sticky position,
view or click counts.
