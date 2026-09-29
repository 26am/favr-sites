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
- **Pages not built with Elementor are locked** ("Managed by Favr"), e.g. Favr Members' login and
  account pages, so a shortcode page can't be broken. Enforced through WordPress capabilities.
- **News (posts) open only in the block editor**, with a short block list (paragraph, heading,
  list, quote, image, gallery, embed, button, separator, table, file), no patterns, Openverse or
  code editor. Elementor is off for posts.
- **Lists:** only View and a small bin icon under titles; no Comments or Yoast columns.
- **Menu:** Dashboard, Pages, News, Media, Approvals, Directory, Events, Members, Profile.
  Everything else is hidden (allow-list). "Posts" is called "News".

For everyone: **comments are off** (no forms, REST route, pingbacks, menu or admin-bar bubble).
Existing comments are kept, so deactivating Favr Sites brings them back.

If Elementor isn't active, the page rules switch off and pages use the block editor.

## Help links

Settings → Favr (Administrators). Values resolve as: `wp-config.php` constant → site setting →
fleet default (care@favr.site, 407-889-9987). Empty links are hidden.

```php
define( 'FAVR_SITES_HELP_URL', 'https://help.example.com/' );
define( 'FAVR_SITES_SUPPORT_EMAIL', 'support@example.com' );
define( 'FAVR_SITES_SUPPORT_PHONE', '407 555 0100' );
define( 'FAVR_SITES_BOOKING_URL', 'https://example.com/book' );
```

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

Fonts: Newsreader and Instrument Sans, SIL Open Font License (see `assets/fonts/`).
