# Favr Sites

The Favr experience on every Favr client site. Version 0.1 replaces the WordPress dashboard for
site editors (users with the Editor role) with a Favr home screen:

- **Header** with the client's logo and site name, a greeting, and "View site".
- **Needs your attention**: pending approvals from Favr Directory, Members and Events, only when
  something is waiting.
- **Quick actions**: Add news post, Edit pages, plus whatever the installed Favr plugins add.
- **Cards** from each Favr plugin (Directory, Members, Events).
- **Recent activity** across content the user can edit, and **Help** links.

Everyone else, Administrators included, keeps the stock WordPress dashboard. No stock or third-party dashboard widgets and no
plugin notices appear on the Favr screen.

## Editor experience (0.2)

For the Editor role (Administrators keep stock WordPress):

- **Pages open only in Elementor.** Titles, Edit links and direct `post.php?action=edit` URLs go to
  Elementor; "Add Page" creates an Elementor page; Elementor's Exit returns to the Pages list.
- **Anything that doesn't fit is locked** ("Managed by Favr"): pages not built with Elementor
  (e.g. Favr Members' login and account pages, so a shortcode page can't be broken) and News posts
  built with Elementor. Enforced through WordPress capabilities.
- **News (posts) open only in the block editor**, with a short block list (paragraph, heading,
  list, quote, image, gallery, embed, button, separator, table, file), no pattern library, Openverse or
  code editor. Elementor is off for posts.
- **Lists:** only View and a small bin icon under titles; no Comments or Yoast columns.
- **Menu:** Dashboard, Pages, News, Header & Footer, Alert banner, Media, Approvals, Directory, Events, Members,
  Documents, People, Sponsors, Profile (the last three come from Favr Content).
  Everything else is hidden (allow-list). "Posts" is called "News".
- **Admin bar** (wp-admin and the public site): the WordPress logo menu becomes a Favr help menu
  (help centre, email and phone from the help links); the account menu shows the first name,
  "Site editor", "Your profile" and "Log out"; "New" lists only News post, Page and Media; Elementor's
  "Edit with Elementor" dropdown is hidden (WordPress's Edit link already opens pages in Elementor).
- **Elementor editor** (0.5): the main menu keeps User Preferences and Keyboard Shortcuts; Site
  Settings, Theme Builder and "Connect my account" are hidden; Help becomes "Favr help" (or "Email
  Favr"); Exit becomes "Back to Pages". Angie (Elementor's AI assistant), "Send feedback" and the
  "What's new" megaphone are removed. Done through Elementor's own menu registry
  (`assets/elementor/editor.js`), so a renamed Elementor item falls back to its default.

- **Your profile** (0.6): Editors get a short Favr profile page: name, email (WordPress's confirm-by-email
  step still applies), password, photo via Gravatar, and a one-row colour-scheme picker (50px swatches).
  Nickname and display name follow First/Last name. Pinned for Editors: toolbar always on, Media
  infinite scrolling on, application passwords off, Elementor AI off. Saving uses WordPress's own
  profile update.

- **Header & Footer** (0.7 as Menus; renamed in 0.8): a Favr screen for the site's **Header** and **Footer** menus instead of
  Appearance → Menus. Drag or use the arrows to reorder, rename in place, make an item a dropdown
  item (one level), remove (its dropdown items move up), add a page or a link (web address, email,
  phone or a `/path`). "Pages not in a menu yet" suggests published pages in neither menu. Each
  menu shows where it appears ("Shown in: …", from Elementor Nav Menu widgets and theme menu
  locations). Saving edits the real WordPress menu, keeping each item's own settings (new tab,
  CSS classes, description), and clears Elementor's cache. Items that were already nested deeper
  than one level are kept and flagged. Editors can't create, rename or delete menus.

- **Alert banner** (0.9): one message across the top of every page, from a Favr screen. Plain text
  with an optional link, a Standard (site colour) or Urgent (red) style, optional show and hide
  times in the site's time zone, and a close button visitors' browsers remember until the banner
  is saved again. Printed on `wp_body_open` (in the footer, then moved to the top, on a theme that
  never fires it); the browser re-checks the times and the dismissal, so cached pages stay
  correct. Not shown in wp-admin, feeds or the Elementor editor. Saving fires
  `litespeed_purge_all` and `favr_sites_banner_saved` (hook other page caches there).

For everyone: **comments are off** (no forms, REST route, pingbacks, menu or admin-bar bubble).
Existing comments are kept, so deactivating Favr Sites brings them back.

If Elementor isn't active, the page rules switch off and pages use the block editor.

## Favr look (0.4)

- **Favr admin colour scheme** (Profile → Admin Color Scheme), from the Favr brand palette:
  aubergine menus and admin bar, rust highlights, links and buttons, gold notification bubbles and
  a cream page. It's the default for Editors who have never picked a scheme; everyone can choose it.
  Source: `assets/admin-colors/favr/colors.scss` (built on WordPress's own scheme sources);
  rebuild with `npx --yes sass@1 --no-source-map --style=compressed assets/admin-colors/favr/colors.scss assets/admin-colors/favr/colors.css`.
- **Favr screens** (dashboard, admin-bar logo) use the brand tokens in `assets/dashboard/tokens.css`
  and the brand fonts Fraunces and Source Sans 3 (self-hosted, OFL).

## Help links

Settings → Favr (Administrators). Values resolve as: `wp-config.php` constant → site setting →
fleet default (care@favr.site, 407-889-9987). Empty links are hidden.

```php
define( 'FAVR_SITES_HELP_URL', 'https://help.example.com/' );
define( 'FAVR_SITES_SUPPORT_EMAIL', 'support@example.com' );
define( 'FAVR_SITES_SUPPORT_PHONE', '407 555 0100' );
define( 'FAVR_SITES_BOOKING_URL', 'https://example.com/book' );
```

## Site foundations (0.8)

Every Favr site always has four pieces, kept in place when an Administrator opens wp-admin:

- **Home**, the static front page, and **News**, the posts page (Settings → Reading). The current
  front/posts page is adopted; otherwise a published page called "Home"/"News"; otherwise Favr
  creates one (Home as a full-width Elementor page with the site name and tagline).
- **Header** and **Footer**: Elementor Pro Theme Builder templates shown on the Entire Site. The
  existing Entire Site header/footer is adopted; otherwise Favr creates a starter (site logo or
  title + the Header menu; the Footer menu + "© year Site name"), styled by the Site Kit. Needs
  Elementor Pro; without it only Home and News apply.

Favr adopts first and only creates what's missing; a site that's already in order (e.g. GOAABA) is
left untouched. It also repairs: a Header/Footer that was unpublished or trashed is republished and
its display conditions go back to Entire Site; a Home/News page set as draft while the site shows a
static front page is republished. It never makes a private or scheduled page public, ignores stale
Reading ids on a "latest posts" site, and won't turn a designed Elementor "News" page into the
posts index (it creates a plain News page instead). A trashed Home/News is replaced by a new one
(WordPress clears Reading when the page is trashed). The template ids are stored in the
`favr_sites_foundations` option; if anything goes wrong while repairing, it's logged and wp-admin
keeps working.

Editors can edit Home, Header and Footer (the Header & Footer screen's "Edit header design" /
"Edit footer design", or the dashboard's "Edit header" / "Edit footer"), but can't delete or
unpublish any of the four, or change any Elementor template's display conditions. The News page
is "Managed by Favr" (WordPress shows posts there, not the page's own content). In Elementor,
Editors don't get "Display Conditions" (Save Draft stays: on a published page Elementor only saves
a draft revision), and Exit returns to Header & Footer.
Administrators are never locked; they can add more specific headers (e.g. for Events).

## Menus

Settings → Favr connects the Header and Footer slots to real menus (option `favr_sites_menus`).
A slot left unset uses the menu whose slug is `header` / `footer`. On a site with no menus at all,
Favr Sites creates "Header" and "Footer" the first time an Administrator opens wp-admin. Existing
sites are connected by hand (for example GOAABA: Primary Navigation → Header, Footer Explore →
Footer).

## Contributing to the dashboard (for Favr plugins)

Plain data only; Favr Sites renders it. Both filters are inert when Favr Sites isn't installed.

```php
add_filter( 'favr_sites_quick_actions', function ( array $actions ): array {
	$actions[] = array(
		'id'         => 'add-event',
		'label'      => 'Add event',
		'url'        => admin_url( 'post-new.php?post_type=favr_event' ),
		'capability' => 'edit_favr_events',
		'icon'       => 'calendar', // news, page, calendar, users, store, plus, mail, phone, help
		'priority'   => 30,         // lower first; default 50
	);
	return $actions;
} );

add_filter( 'favr_sites_dashboard_cards', function ( array $cards ): array {
	// An array, or a closure / [object, method] that returns one (built lazily, errors isolated).
	$cards[] = fn() => array(
		'id'         => 'events',
		'title'      => 'Events',
		'capability' => 'edit_favr_events',
		'priority'   => 30,
		'stats'      => array( array( 'label' => 'in the next 30 days', 'value' => 7, 'url' => '…' ) ), // max 3
		'items'      => array( array( 'title' => 'Fall Happy Hour', 'meta' => 'Thu 15 Oct', 'url' => '…' ) ), // max 5
		'link'       => array( 'label' => 'All events', 'url' => '…' ),
		'empty'      => array( 'text' => 'No upcoming events.', 'label' => 'Add an event', 'url' => '…' ),
	);
	return $cards;
} );
```

Other filters: `favr_sites_dashboard_enabled` (bool, WP_User) and `favr_sites_activity_post_types`.

## Development

```bash
composer install
composer test
composer lint
```

Fonts: Fraunces and Source Sans 3, SIL Open Font License (see `assets/fonts/`).
