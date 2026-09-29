# Favr Sites: Menus for Editors (v0.7)

Status: approved in conversation 2026-09-29, pending spec review.

## Why

Editors need to add, rename, reorder and remove menu items without WordPress's Appearance → Menus
screen (complex, and its capability `edit_theme_options` also unlocks Widgets/Customizer/Themes).
Fully automatic menus were rejected: not every page belongs in a menu, menus hold more than pages,
labels and order are editorial, and header/footer menus differ.

Success: an Editor can keep the site's menus current in a minute, can't make a menu worse than one
dropdown level, and nothing on the page designs changes.

## Decisions

| Question | Decision |
|---|---|
| Where | A Favr "Menus" screen (`admin.php?page=favr-menus`), capability `edit_pages`. Editor menu order becomes Dashboard, Pages, News, Menus, Media, Approvals, Directory, Events, Members, Profile. |
| What it edits | The site's real WordPress menus (the ones Elementor's Nav Menu widgets and theme locations display), so the site updates on save. |
| Which menus | All nav menus. Menus that are shown somewhere come first with "Shown in: {template/page title}" (Elementor Nav Menu widgets referencing the menu slug in published Elementor data, plus theme locations). Others say "Not shown on the site yet". |
| Menus themselves | Editors can't create, rename or delete menus (Favr sets them up). |
| Items | Reorder (drag, or Move up/down buttons), rename, remove, one dropdown level (Make dropdown item / Move out). Add a page (search over published pages) or a link (label + address). |
| Depth | Editors get top level + one dropdown level. Existing deeper items are kept, shown with "Too deep for the menu; move it out a level", and never changed silently. |
| New pages | "Pages not in a menu yet" tray: published pages in no menu, one-click "Add" to the current menu. Suggestion only. |
| Save | One "Save changes" per menu; unsaved-changes warning on leave. |

## Units

- `Menus\Tree` (pure, unit-tested):
  - `rows( list<object> $items ): list<Row>`: WordPress nav items (`ID`, `menu_item_parent`,
    `menu_order`, `title`, `url`, `type`, `object`, `object_id`) → ordered rows with `level` (0, 1,
    2+) computed from parents.
  - `plan( list<Row> $existing, list<array> $submitted ): array{update, create, delete, errors}`:
    submitted rows `{id|null, level, title, url, type: page|custom|other, object_id}` in display
    order → operations. Parent = nearest preceding row one level up. Rules: level clamped to
    `min(level, previous row level + 1)`; a submitted level > 1 is only accepted for an existing row
    whose saved level was already > 1 (deeper items preserved, never created or deepened); unknown
    ids ignored (can't touch other menus' items); titles `sanitize_text_field`, URLs
    `esc_url_raw` (http/https/mailto/tel, or relative starting with `/`); `other` rows (categories,
    archives, anything not page/custom) keep their type/object and only change title/order/level.
  - `unplacedPages( list<page> $pages, list<int> $page_ids_in_menus ): list<page>`.
- `Menus\Usage`: `where( WP_Term $menu ): list<string>` via one query for published
  `_elementor_data` containing `"menu":"{slug}"` (excluding revisions) + `get_nav_menu_locations()`.
- `Menus\Screen`: registers the page; renders `templates/menus.php` (server-rendered list so it
  works before JS loads); handles the save (`admin-post.php?action=favr_sites_save_menu`, nonce
  `favr_sites_save_menu_{menu_id}`, `current_user_can( 'edit_pages' )`), applying `Tree::plan()`
  with `wp_update_nav_menu_item()` / `wp_delete_post()` on the menu's items only, then redirecting
  back with "Menu saved." or the errors.
- `assets/menus/menus.js` + `menus.css`: drag (jQuery UI Sortable, bundled with WordPress), keyboard
  buttons (up/down/in/out), inline rename, bin icon, add-page search and add-link form, tray "Add",
  dirty-state warning. On submit, serializes rows as JSON into a hidden field.
- `Editors\Menu::ORDER` gains `favr-menus` after `edit.php`.

## Edge cases

- Menu has no items: empty state "This menu is empty. Add a page or a link."
- An item's page is deleted or trashed: WordPress drops it from menus on delete; trashed pages
  show "(page is in the trash)" and can be removed.
- Two Editors saving the same menu: last save wins (same as WordPress).
- JS failed to load: the list renders read-only with a note; no partial saves.
- Elementor absent: "Shown in" lists only theme locations.

## Testing

Unit: `Tree::rows` (levels, ordering, orphans), `Tree::plan` (reorder, rename, create page/link,
delete, depth clamp, deeper-item preservation, foreign ids ignored, URL/title sanitizing, `other`
rows preserved), `unplacedPages`.

Real check on sermonator-test as the Editor: create two test menus (one placed in an Elementor Nav
Menu widget on the demo page), reorder/rename/add/remove/nest, save, confirm the front end reflects
it; keyboard-only reorder; a pre-seeded 3-level item shows the warning and survives a save.
Screenshots.

## Out of scope

Creating/deleting menus, mega menus, menu item icons/classes/targets, Appearance → Menus access.
