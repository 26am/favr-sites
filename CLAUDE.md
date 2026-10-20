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
  built inside try/catch). `Dashboard\Items` normalizes, filters by capability and sorts by
  priority, then puts the ids it knows in the menu's order (`ACTION_ORDER`, `CARD_ORDER`; unknown
  ids follow). A new sibling action or card needs its id added there to get a fixed place.
- `Dashboard\Attention` reads the existing `favr_approvals_providers` contract (favr/core inbox).
- `Dashboard\Activity`: 10 most recent changes across editable post types; nothing is stored.
  `changed()` picks the first real date (a draft made by one insert has `0000-00-00` GMT dates).
- `Help\Links`: constant (`FAVR_SITES_HELP_URL`, …) → `favr_sites_help` option → default.
- `Editors\*` (Editor role only, via `Audience::current()`): `PageLock` (map_meta_cap denies
  edit/delete/publish incl. `edit_page`/`delete_page`, which Elementor checks, on pages not built
  with Elementor and on News posts built with Elementor), `Routing` (pages → Elementor, no Elementor on posts), `BlockList`,
  `ListTables` (row actions, no Author, Comments or SEO columns, "Oct 8, 2026" dates with a status
  word only when not published, no month filter on Favr post types), `Menu`, `Words`, `Terms`,
  `Chrome`, `Wayfinding`, `AdminBar` (Favr help menu, account menu, "New" in Favr's order, no
  Elementor dropdown). Opening a page in Elementor stamps it as an Elementor
  page immediately, so never "test" a locked page by loading `action=elementor` on a real site.
- `Editors\ListTables::rowActions()` never returns nothing: WordPress prints the "Show more
  details" toggle (the only way to open a row's folded columns on a phone) inside `row_actions()`,
  and only when there is an action. A draft an Editor may not edit (the Privacy Policy page: no
  Preview without `edit_post`, no Trash under `PageLock`) keeps the empty `favr-no-actions` entry.
- `Editors\AdminBar`: "New" is rebuilt from `NEW_ORDER` (the post types behind
  `Dashboard\Items::ACTION_ORDER`, then Page, Person, Sponsor, Media): every node WordPress put
  there is removed, then one is added per post type that exists and the user may `create_posts`
  in, titled by its `name_admin_bar` label through `Words::sentence()`. The "New" link itself
  opens the first entry. A new Favr post type needs adding to `NEW_ORDER`.
- `Editors\Words` (wp-admin, Editors, English locales only): WordPress's Title Case in sentence
  case. `MAP` (`PHRASES` + `SENTENCES`) is one dictionary, phrase in WordPress's code => what an
  Editor reads, used by both `gettext` and `gettext_with_context` (the same words in every
  context) for the `default` domain only. Each filter is one comparison and one `isset`; keep it that way, it runs for every phrase
  on every request. Hooked at the very start of `init` (`PHP_INT_MIN`): post type labels, the
  media window's strings (`wp_enqueue_media()`) and the visual editor's (`wp_mce_translation`)
  are all translated in PHP after that. `SCRIPTS` holds the few phrases `post.js`, `common.js`
  and the colour picker translate in the browser; they are handed over as script translations
  (`pre_load_script_translations`), which only works because an English site has no JSON files.
  Rules a test enforces: every entry changes capitals only unless it is listed in `REWORDED`,
  "Trash" keeps its capital, nothing an Editor reads has a capital after the first word except
  names and abbreviations. Before adding a phrase check nothing compares it (WordPress 7.1 only
  tests that `publish`/`save` were submitted, never their label). To find what is still Title
  Case, read the screens as an Editor; the block editor's own interface is out of scope.
- `Editors\Terms` (every taxonomy's `edit-tags.php` and `term.php`): no Slug column, the Slug
  field hidden (`css()`, inline on the `favr-sites-skin` handle; Quick Edit needs `:has()`), and
  WordPress's helper paragraphs replaced (`help()`). The label and column filters are named after
  the taxonomy, so they are added from `register_taxonomy_args` as each one is registered; the
  column filter must also run in `admin-ajax.php`, where Quick Edit and the add form get their
  rows. The field is hidden, not removed: WordPress still receives the old slug on an edit and
  makes one from the name on a new term. The category screen prints its own sentence under
  "Parent category" instead of the label; that one is in `Words::MAP`.
- `Editors\Menu`: allow-list in `TOP` + `GROUPS` (website, organization, account). `arrange()`
  (pure) returns the kept slugs with a `separator-favr-{group}` before each group that has an
  entry. The separators are put into `$menu` inside the `menu_order` filter (`withSeparators()`),
  not on `admin_menu`: only then has WordPress dropped the entries the user may not open, so an
  empty group gets no heading. The headings' words are inline CSS on the `favr-sites-skin` handle
  (`headingCss()`, escaped by `cssString()`); `assets/skin/base.css` draws them. Also the
  sentence-case words: submenu labels (`relabel()`) and post type and taxonomy labels for News,
  Pages, Media, News categories and tags (`LABELS`; these filters run on the front end too, for
  the toolbar). Words that are not labels (the Media screens' title, button and search) are in
  `Editors\Words`.
- `Editors\Chrome`: no Screen Options, no Help tabs, and `BOXES` removed from classic edit
  screens (never `postexcerpt`: Favr Events' summary). Two consequences it handles: saved Screen
  Options choices stop applying (`hidden_meta_boxes`, `hidden_columns`, `screen_layout_*`), and it
  prints a hidden `post_name` field, because WordPress keeps the field the permalink editor saves
  through inside the Slug box.
- `Editors\Wayfinding`: the "← All events" link (`.favr-back`) on `all_admin_notices` at the last
  priority, so it lands right above `.wrap`, on classic `post.php`/`post-new.php` and `term.php`.
- `ElementorEditor` also drops Elementor's `editor-widget-creation` package and the Angie auto-guide for
  Editors (PHP filters), hides widget groups (`HIDE_CATEGORIES`, CSS from `panelCss()`; `editor.js` sets
  `show_in_panel` so search agrees) and colours the top bar (`assets/elementor/editor.css`).
- `ElementorEditor` + `assets/elementor/editor.js`: re-registers Elementor
  app-bar items by id with `overwrite: true` (ids/groups from Elementor's editor-app-bar package) and
  dequeues `e-editor-notifications`. Check the ids when Elementor updates.
- `Profile\ProfilePage` + `templates/profile.php`: Editors' profile.php takeover (GET and the
  `action=update` POST, which it saves via `personal_options_update` + `edit_user()`); core's
  `newuseremail`/`dismiss` GET links are left to core. No colour scheme picker; `save()` posts the
  stored `admin_color` back (`keptScheme()`), because `edit_user()` writes "fresh" when the field
  is missing. Elementor AI is off for Editors through `get_user_option_elementor_enable_ai`
  (Elementor reads that user option once, in its AI module's constructor on `init`, and treats
  "never set" as on); nothing is written, so it holds for an Editor who never saved the page.
- `Menus\*`: `Tree` (pure: nav items → depth-first rows with levels; submitted rows → a plan of
  updates/creates/deletes with the one-dropdown-level rule, foreign ids ignored, `cleanUrl`),
  `Slots` (Header/Footer → menu term ids; Settings → Favr; fresh-site defaults), `Usage`
  ("Shown in"), `Screen` (`admin.php?page=favr-menus`, cap `edit_pages`; saves via
  `admin-post.php?action=favr_sites_save_menu`, re-passing each existing item's full data because
  `wp_update_nav_menu_item()` resets anything omitted). `assets/menus/menus.js` (jQuery UI
  Sortable) mirrors `Tree`'s rules; keep `cleanUrl` in sync with `Tree::cleanUrl`.
- `Site\*` (0.8, site foundations): `Foundations` (pure: snapshot → per-role decisions: adopt id or
  create, publish/restore, reset conditions; `settled()` means no writes), `Starter` (pure starter
  Elementor elements; Kit globals only), `Setup` (snapshot + apply on `admin_init` priority 20,
  after `Slots::ensureDefaults`; Elementor document API + Pro's conditions manager), `Protect`
  (Editors: no delete, always published, no `_elementor_conditions` writes). Keep `settled()`
  exact: a settled site must never be written to on each Administrator page load.
- `Banner\*` (0.9): `Banner` (pure value: `clean()` a submitted form, `state()` off/pending/live,
  `window()`, `version()`), `Colors`, `Store` (option `favr_sites_banner`, autoloaded), `Screen`
  (`admin.php?page=favr-banner`, cap `edit_pages`), `Display` (`wp_body_open`, footer fallback,
  inline `assets/banner/banner.js`). The stored times are local strings; keep `banner.js`'s
  window check in step with `Banner::state()`.
- `Site\Credit` (0.11): "Website powered by Favr" after the site footer
  (`elementor/theme/after_do_footer`, else `wp_footer` priority 1), once per page; inline
  `assets/credit/credit.css` and `credit.js` (matches the footer's bottom-row background, picks
  readable text). Option `favr_sites_credit` (`on` | `off`; missing = on). The switch is a card on
  Header & footer (`Menus\Screen`), saved through `admin_post_favr_sites_save_credit`, cap
  `edit_pages`; `menus.js` saves it the moment it changes (`favr_js=1` → JSON), so it never meets
  an unsaved menu edit. Not printed in Elementor's editor. Only "Favr" is linked, a plain followed
  link (no rel, no tracking): David's call. `credit.js` skips hidden, zero-size and fixed/absolute
  rows, never reads a widget's or button's colour, and tones the text towards the background only
  as far as 4.5:1; `node tests/browser/credit.mjs` (Chrome) checks it against footer shapes.
- `Comments\Off`: comments off for everyone; no data touched.
- `Brand\ColorScheme`: the "Favr" admin colour scheme. `forUser()` returns it for every Editor
  whatever is stored (the skin is drawn for it); everybody else keeps their choice.
  `assets/admin-colors/favr/toolbar.css` holds the scheme's `#wpadminbar` rules for the public site,
  where WordPress loads no scheme; it is copied from `colors.css` (a test fails if they drift).
  Edit `assets/admin-colors/favr/colors.scss`, then rebuild `colors.css` with
  `npx --yes sass@1 --no-source-map --style=compressed assets/admin-colors/favr/colors.scss assets/admin-colors/favr/colors.css`
  and commit both (no build step on client sites).
- `Skin\Skin` (Editors only): body class `favr-skin`, `tokens.css` and the sheets in
  `assets/skin/` (the block editor only gets `base` and `media`). Design and the rules every sheet
  follows: `docs/superpowers/specs/2026-10-11-editor-skin-design.md`.
- Styles: brand tokens only in `assets/dashboard/tokens.css`. The four Favr screens (dashboard,
  Header & footer, Alert banner, Profile) carry `favr-dash-screen` and have their own sheets
  (`dashboard.css`, `menus/menus.css`, `banner/screen.css`, `profile/profile.css`), scoped under `.favr-dash-screen` / `.favr-dash`.
  Every other Editor screen is styled by `assets/skin/*.css`, one surface per sheet: `base`
  (shell, menu groups, shared components), `lists`, `edit`, `fields`, `screens`, `media`. Shell
  rules start `body.favr-skin`; content rules start
  `body.favr-skin:where(:not(.favr-dash-screen))`. Text Editors read is US English, sentence case.

## Conventions

- Match the siblings: WPCS formatting, camelCase methods, PSR-4 classes in `src/`.
- Keep it simple: JS only for the Elementor editor tweak, the Menus screen and the banner's close button, no caching layer, no new tables. Add unit tests for pure logic.
