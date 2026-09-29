# CLAUDE.md

## What this is

**Favr Sites** (namespace `FavrSites\`, prefix `favr_sites_`) is the plugin that owns the Favr
experience on every client site. v1 replaces the wp-admin dashboard for users with the Editor role with a
Favr home screen. PHP 8.1+, WP 6.7+, no runtime dependencies, no JS build step. Design:
`docs/superpowers/specs/2026-09-28-favr-dashboard-design.md`.

Not to be confused with `favr/core` (the shared library bundled inside Directory, Members and
Events). Favr Sites does not use it.

## Commands

```bash
composer install   # dev tools only; the plugin runs from a plain zip (bundled autoloader)
composer test      # PHPUnit + Brain Monkey (pure logic only)
composer lint      # WPCS, keep at 0 errors
```

Local test site: `http://sermonator-test.local/`, with this repo symlinked to
`wp-content/plugins/favr-sites`. The site has an `editor` user for checking the Favr screen.

## Architecture

- `Dashboard\Audience`: who gets the Favr screen (the `editor` role, lacks `manage_options`, then the
  `favr_sites_dashboard_enabled` filter).
- `Dashboard\Takeover`: on `load-index.php` it draws the admin frame, renders `Screen` and exits,
  so `wp_dashboard_setup()` never runs and no widget is built. It also strips notices and the
  WordPress footer on that screen.
- `Dashboard\Screen` builds the view model; `templates/dashboard.php` escapes everything.
- Other plugins contribute **plain data only** via `favr_sites_quick_actions` and
  `favr_sites_dashboard_cards` (arrays, or closures/[object, method] returning an array, which are
  built inside try/catch). `Dashboard\Items` normalizes, filters by capability and sorts.
- `Dashboard\Attention` reads the existing `favr_approvals_providers` contract (favr/core inbox).
- `Dashboard\Activity`: 10 most recent changes across editable post types; nothing is stored.
- `Help\Links`: constant (`FAVR_SITES_HELP_URL`, …) → `favr_sites_help` option → default.
- `Editors\*` (Editor role only, via `Audience::current()`): `PageLock` (map_meta_cap denies
  edit/delete/publish incl. `edit_page`/`delete_page`, which Elementor checks, on pages not built
  with Elementor and on News posts built with Elementor), `Routing` (pages → Elementor, no Elementor on posts), `BlockList`,
  `ListTables`, `Menu` (allow-list + "News"; hooked on the front end too for the admin bar
  labels), `AdminBar` (Favr help menu, account menu, short "New", no Elementor dropdown). Opening a page in Elementor stamps it as an Elementor
  page immediately, so never "test" a locked page by loading `action=elementor` on a real site.
- `ElementorEditor` + `assets/elementor/editor.js` (the plugin's only JS): re-registers Elementor
  app-bar items by id with `overwrite: true` (ids/groups from Elementor's editor-app-bar package) and
  dequeues `e-editor-notifications`. Check the ids when Elementor updates.
- `Comments\Off`: comments off for everyone; no data touched.
- `Brand\ColorScheme`: the "Favr" admin colour scheme (default for Editors with no saved choice).
  Edit `assets/admin-colors/favr/colors.scss`, then rebuild `colors.css` with
  `npx --yes sass@1 --no-source-map --style=compressed assets/admin-colors/favr/colors.scss assets/admin-colors/favr/colors.css`
  and commit both (no build step on client sites).
- Styles: brand tokens only in `assets/dashboard/tokens.css`; everything else in
  `dashboard.css`, scoped under `.favr-dash-screen` / `.favr-dash`.

## Conventions

- Match the siblings: WPCS formatting, camelCase methods, PSR-4 classes in `src/`.
- Keep it simple: no JS beyond the Elementor editor tweak, no caching layer, no new tables. Add unit tests for pure logic.
