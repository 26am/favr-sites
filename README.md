# Favr Sites

The Favr experience on every Favr client site. Version 0.1 replaces the WordPress dashboard for
site editors (Editor role and below) with a Favr home screen:

- **Header** with the client's logo and site name, a greeting, and "View site".
- **Needs your attention**: pending approvals from Favr Directory, Members and Events, only when
  something is waiting.
- **Quick actions**: Add news post, Edit pages, plus whatever the installed Favr plugins add.
- **Cards** from each Favr plugin (Directory, Members, Events).
- **Recent activity** across content the user can edit, and **Help** links.

Administrators keep the stock WordPress dashboard. No stock or third-party dashboard widgets and no
plugin notices appear on the Favr screen.

## Help links

Settings → Favr (Administrators). Values resolve as: `wp-config.php` constant → site setting →
fleet default. Empty links are hidden.

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
