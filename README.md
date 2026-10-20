# Favr Sites

The Favr experience on every Favr client site. Version 0.1 replaces the WordPress dashboard for
site editors (users with the Editor role) with a Favr home screen:

- **Header** with the client's logo and site name, a greeting, and "View site".
- **Needs your attention**: pending approvals from Favr Directory, Members and Events, only when
  something is waiting.
- **Quick actions**: Add news post, Edit pages, plus whatever the installed Favr plugins add.
- **Cards** from each Favr plugin (Directory, Members, Events).
- **Recent activity** across content the user can edit (a draft that was never saved a second
  time shows when it was added), and **Help** links.

Everyone else, Administrators included, keeps the stock WordPress dashboard. No stock or third-party dashboard widgets and no
plugin notices appear on the Favr screen.

## Editor experience (0.2)

For the Editor role (Administrators keep stock WordPress):

- **Pages open only in Elementor.** Titles, Edit links and direct `post.php?action=edit` URLs go to
  Elementor; "Add page" creates an Elementor page; Elementor's Exit returns to the Pages list.
- **Anything that doesn't fit is locked** ("Managed by Favr"): pages not built with Elementor
  (e.g. Favr Members' login and account pages, so a shortcode page can't be broken) and News posts
  built with Elementor. Enforced through WordPress capabilities.
- **News (posts) open only in the block editor**, with a short block list (paragraph, heading,
  list, quote, image, gallery, embed, button, separator, table, file), no pattern library, Openverse or
  code editor. Elementor is off for posts.
- **A calmer Elementor editor** (0.9.1), in Favr's colours with the Favr mark: Favr's own widgets
  come first in the panel; Elementor's "atomic" elements, AI widget builder (and its "install
  Angie" prompt), Components and Globals tabs, single-post, shop and other-theme widget groups
  are gone, from search too; so are the Design System, Page Settings and Finder buttons.
  Everyday widgets (Layout, Basic, General, Pro, Site) stay.
- **Lists:** only View and a small bin icon under titles; no Author, Comments, Yoast or Rank Math
  columns. Dates read "Oct 8, 2026", with a word above them only when the item is not published
  ("Scheduled", "Last modified"); the Media Library's list uses the same dates. Favr's own lists
  (Members, Directory, Events, Documents, People, Sponsors) have no month filter; News, Pages and
  Media keep theirs. On a phone every row can be opened to see its other columns, also a row with
  nothing to click under its title (a draft page managed by Favr).
- **No WordPress credit** ("Thank you for creating with WordPress", the version) under an
  Editor's screens.
- **Menu:** Dashboard and Approvals, then two groups with a small heading each. **Website**:
  Pages, News, Header & footer, Alert banner, Media. **Organization**: Members, Directory, Events,
  Documents, People, Sponsors (the last three come from Favr Content). Profile comes last.
  Everything else is hidden (allow-list). A group nobody has installed, or that the Editor may
  not open, has no heading; the collapsed menu shows a hairline between groups.
- **Words:** "Posts" is called "News", and labels are in sentence case: "All pages / Add page",
  "Library / Add media", "All categories / Add category", "Header & footer". The Media screens'
  own title, button and search say the same ("Media library", "Add media", "Search media").
- **WordPress's own words in sentence case** (wp-admin, English sites): "Save draft", "Preview
  changes", "Collapse menu", "Quick edit", "Delete permanently", "Front page", and in the media
  window "Media library", "Attachment details", "Alt text", "Image caption", "Link to", "Edit
  selection". About a hundred phrases Editors meet, kept in one dictionary (`Editors\Words`). "Trash"
  keeps its capital, and so do names. The block editor's own interface, the public site and
  Administrators' screens are not touched.
- **Categories and levels** (every taxonomy): no Slug column and no Slug field in the add form,
  in Quick Edit or on the edit screen. A new one still gets its slug from its name; renaming one
  keeps the slug, so links to it keep working. WordPress's explanations under the fields are
  gone; "Parent" and "Description" each keep one short line.
- **Edit screens** (classic ones; not the block editor or Elementor): a "← All events" link above
  the title goes back to the list ("← All categories" on a category). Editors have no Screen
  Options, no Help tab and no Author, Slug, Attributes, Custom Fields, Discussion, Comments,
  Trackbacks or Revisions box. Because nobody can bring back what Screen Options once hid,
  earlier choices (hidden boxes, hidden columns, one-column layout) stop applying. The permalink
  under the title can still be edited.
- **Admin bar** (wp-admin and the public site): the WordPress logo menu becomes a Favr help menu
  (help center, email and phone from the help links); the account menu shows the first name,
  "Site editor", "Your profile" and "Log out"; "New" lists everything the Editor can add, in the
  order of the dashboard's quick actions (Event, News post, Member, Directory listing, Document,
  then Page, Person, Sponsor, Media), and clicking "New" itself adds the first; Elementor's
  "Edit with Elementor" dropdown is hidden (WordPress's Edit link already opens pages in Elementor).
- **Elementor editor** (0.5): the main menu keeps User Preferences and Keyboard Shortcuts; Site
  Settings, Theme Builder and "Connect my account" are hidden; Help becomes "Favr help" (or "Email
  Favr"); Exit becomes "Back to Pages". Angie (Elementor's AI assistant), "Send feedback" and the
  "What's new" megaphone are removed. Done through Elementor's own menu registry
  (`assets/elementor/editor.js`), so a renamed Elementor item falls back to its default.

- **Your profile** (0.6): Editors get a short Favr profile page: name, email (WordPress's confirm-by-email
  step still applies), password and photo via Gravatar. There is no colour scheme to pick (Editors
  always get Favr's), and saving leaves the scheme stored for the user as it was.
  Nickname and display name follow First/Last name. Pinned for Editors: toolbar always on (except inside Elementor's editor preview, where it would sit on top of the page's header), Media
  infinite scrolling on, application passwords off, Elementor AI off (no "Generate with Elementor
  AI" or "Edit with Elementor AI" in the Media Library, on an attachment or in the media window;
  it holds for every Editor, also one who never saved this page). Saving uses WordPress's own
  profile update.

- **Header & footer** (0.7 as Menus; renamed in 0.8): a Favr screen for the site's **Header** and **Footer** menus instead of
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

### Added in 0.11.0

- **"Website powered by Favr"** at the bottom of every Favr site: one small line right after the
  site footer (after Elementor Pro's footer, or from `wp_footer` on any other theme). A small
  inline script gives it the background of the footer's bottom row and white or near-black text,
  whichever reads better; without scripts it sits on the page background. Only "Favr" is a link,
  a plain followed link to https://favr.site/ (David's choice; Google's spam policy counts links
  repeated in many sites' footers as a possible link scheme, so the link text stays the brand name).
  On by default; staff (`edit_pages`) turn it off under **Header & footer → Footer credit**
  (option `favr_sites_credit`, `on` | `off`); with scripts on, the box saves the moment it changes.
  Saving fires `litespeed_purge_all` and `favr_sites_credit_saved`. The text is toned towards the
  background but always at least 4.5:1. Not shown in Elementor's editor.

### Fixed in 0.10.1

- **A greyed-out check box still shows its check.** A checked box or chosen option that an
  Editor may look at but not change lost its mark under the skin (white mark on the sand fill of
  a disabled field), so a setting that was on read as off. It now keeps a dark fill
  (`assets/skin/base.css`). Seen first on Favr Payments' read-only settings.

### Added in 0.10.0

- **Nothing is lost by leaving.** On every classic edit screen an Editor who changed something
  (a panel field, a picture, hours, a repeater row, a category, the chosen file) is asked before
  leaving the screen. Update, Publish, Save draft, Preview and Move to Trash never ask
  (`assets/skin/edit-screen.js`).
- **Update stays in view.** From 851px wide the Publish box sticks under the toolbar and, from
  1241px, the field panel's tabs stay beside the fields; narrower screens get the action bar at
  the bottom. Box titles no longer fold or drag, and saved box states are ignored.
- **The back link remembers** the list as the Editor left it (search, view, page, order), also
  after saving. A record waiting for approval links back to Approvals and says who sent it.
- **Search.** The toolbar's magnifier (⌘K / Ctrl+K) finds members, directory listings, events,
  documents, people, sponsors, pages, news posts and invoices by name (members and listings also
  by company or e-mail) and opens the edit screen. REST route `favr-sites/v1/search`: Editors
  only, each result checked with `edit_post`. "Patterns" and the site editor are closed to Editors.
- **Pages by name.** "Add page" asks for the page's name before Elementor opens (no more
  "Elementor #4413"); "Rename" under each page an Editor may edit changes its name and address.
- **News editor:** Favr rust instead of WordPress blue, no welcome tour, one press to publish,
  "All news" on the way back, no Slug, Author or Template rows.
- **Inside Elementor** the image picker, the loading screen and the exit item carry the Favr look.
- **Lists:** 50 rows a page; Members and Directory open A to Z; no "Mine" tab and no "Published"
  tab that only repeats "All"; no Author column on Media; whole logos in the Media list and grid;
  the same actions under every row (View and the bin), on categories and Media too.
- **One width:** 1180px like the dashboard; 1400px for the Members, Directory and Invoices lists;
  880px for form screens.
- **Header & footer** saves both menus on one press and confirms inside the saved card.
  **Approvals:** Enter in a note never approves, and Reject asks once.
- **Words:** the Publish box on a 12-hour clock; "Category added." instead of "Item added.";
  a Favr card with a way back instead of WordPress's bare error page; no "— WordPress" in the
  browser tab.

## Favr look (0.4)

- **Favr admin colour scheme** (Profile → Admin Color Scheme), from the Favr brand palette:
  aubergine menus and admin bar, rust highlights, links and buttons, gold notification bubbles and
  a cream page. Every Editor gets it, whatever scheme is stored for them (WordPress stores its own
  default for each new user, so "Editors who never chose" was nobody); everyone else can choose it.
  Whoever uses it keeps those colours on the toolbar on the public site too (0.9.1).
  Source: `assets/admin-colors/favr/colors.scss` (built on WordPress's own scheme sources);
  rebuild with `npx --yes sass@1 --no-source-map --style=compressed assets/admin-colors/favr/colors.scss assets/admin-colors/favr/colors.css`.
- **Favr screens** (dashboard, admin-bar logo) use the brand tokens in `assets/dashboard/tokens.css`
  and the brand fonts Fraunces and Source Sans 3 (self-hosted, OFL).
- **The skin**: for Editors every other wp-admin screen (lists, edit screens, categories, Media,
  the Favr plugins' screens) is drawn with the same tokens by the sheets in `assets/skin/`
  (`base.css` documents the selector rules). Administrators keep stock WordPress. Design:
  `docs/superpowers/specs/2026-10-11-editor-skin-design.md`.

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

Editors can edit Home, Header and Footer (the Header & footer screen's "Edit header design" /
"Edit footer design", or the dashboard's "Edit header" / "Edit footer"), but can't delete or
unpublish any of the four, or change any Elementor template's display conditions. The News page
is "Managed by Favr" (WordPress shows posts there, not the page's own content). In Elementor,
Editors don't get "Display Conditions" (Save Draft stays: on a published page Elementor only saves
a draft revision), and Exit returns to Header & footer.
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

The dashboard follows the Editor's menu: the quick actions and cards Favr knows by `id` are
placed in a fixed order whatever their `priority` (actions: `add-event`, `add-post`, `add-member`,
`add-listing`, `add-document`, `edit-pages`, `edit-header`, `edit-footer`, `banner`; cards:
`members`, `dues`, `directory`, `events`, `content`). Anything else follows, by `priority` then
name. The lists are `Dashboard\Items::ACTION_ORDER` and `CARD_ORDER`.

Other filters: `favr_sites_dashboard_enabled` (bool, WP_User) and `favr_sites_activity_post_types`.

## Development

```bash
composer install
composer test
composer lint
```

Fonts: Fraunces and Source Sans 3, SIL Open Font License (see `assets/fonts/`).
