# Favr Sites: Site foundations (v0.8)

Status: approved in conversation 2026-09-29, pending spec review.

## Why

Every Favr site needs four pieces that Editors build on but must never be able to break: a Home
page (static front page), a News page (posts page), and a site-wide Elementor Header and Footer.
Without them an Editor-facing site has no front page, no news listing, or no header/footer, and an
Editor can't fix that (Settings → Reading and the Theme Builder are Administrator territory).

Success: a brand-new Favr site gets all four without setup; an existing site (GOAABA) is adopted
with nothing created or changed; an Editor can edit Home, Header and Footer content but can't
delete, unpublish or retarget any of the four; if anything goes missing anyway, the next
Administrator visit puts it back.

## Decisions

| Question | Decision |
|---|---|
| Approach | Self-healing: checked on `admin_init` for `manage_options` users (not during AJAX) and on plugin activation. Adopt what exists, repair what's half-broken, create only what's missing. No change when everything is in order. |
| Home | Source of truth is Settings → Reading. `show_on_front` must be `page` and `page_on_front` a published page. Adopt the current front page; else a published page titled "Home" or with slug `home`; else create an Elementor page "Home". |
| News | `page_for_posts` must be a published page other than Home. Adopt the current posts page; else a published page titled "News" or slug `news` (not Home); else create an empty page "News". |
| Header / Footer | Only when Elementor Pro's Theme Builder is active. Adopt the recorded template if it's still a header/footer template; else the newest published header (footer) template whose conditions include `include/general`; else create the starter. Repair: status `publish` (untrash if trashed) and conditions exactly `['include/general']` (then regenerate Pro's conditions cache). |
| Stored | Option `favr_sites_foundations` = `{header: int, footer: int}`. Home and News aren't stored (Reading settings say which pages they are). |
| Admin freedom | Administrators aren't locked. They may add more specific headers/footers (e.g. an Events header); Elementor's priority applies. Favr's main Header/Footer always stay Entire Site. An Administrator who points the front page at another page makes that page the protected Home. |
| Editor locks | See the table below. Enforced through capabilities and save filters, not just hidden UI. |
| Starter content | Header: site logo (or site title linked Home when no custom logo) + Nav Menu (Header menu). Footer: Nav Menu (Footer menu, horizontal) + "© {current year} {site name}" (year via Elementor's current-date tag). Home: site name (H1) + tagline, full-width template. News: empty page. Styling from the Site Kit's global colours/fonts only. Header/Footer menus (Menus feature) are ensured first. |
| Editor entry | The Menus screen is renamed **Header & Footer** (same slug `favr-menus`, same place in the menu). Each card: title, "Shown on every page", **Edit header design** / **Edit footer design** (opens Elementor), then the menu links editor. Dashboard quick actions "Edit header" / "Edit footer". Without Pro: no design buttons or quick actions; cards stay menu-only. |
| Inside Elementor | For Editors: "Save as draft" (`document-save-draft`) and "Display Conditions" (`document-display-conditions`) removed from the Publish menu; on the Header/Footer templates Exit reads "Back to Header & Footer" and returns to that screen. |

### Editor locks

| | Home | News | Header / Footer |
|---|---|---|---|
| Edit content in Elementor | yes | no ("Managed by Favr") | yes |
| Rename | yes | no | yes |
| Trash / delete | no | no | no |
| Unpublish / draft / private | no | no | no |
| Change display conditions | — | — | no (no template's conditions, site-wide) |

- Capabilities: `delete_post`/`delete_page` (and `edit_*` for News) denied to Editors on the four ids.
- Status: a save by an Editor that would move one of the four off `publish` keeps `publish`
  (`wp_insert_post_data`), covering Elementor, Quick Edit and REST.
- Conditions: Editors can't add, update or delete `_elementor_conditions` meta on any post, so
  copies ("Save as template", duplicators) of the Header/Footer never display.
- Routing: an Editor opening a Header/Footer template through `post.php?action=edit` goes to
  Elementor (as pages do). Other templates stay out of Editors' reach as today.

## Units

- `Site\Foundations` (pure, unit-tested): `plan( array $state ): list<array>` — from a snapshot
  (Reading options; front/posts page status and type; "Home"/"News" candidates; recorded and
  candidate header/footer templates with status, type and conditions; Pro active) to a list of
  actions: `set_option`, `create_page` (home|news), `create_template` (header|footer), `publish`,
  `untrash`, `set_conditions`, `record`. Empty list when all is well.
- `Site\Starter` (pure, unit-tested): Elementor element arrays for the Header, Footer and Home
  from `{has_logo, header_menu, footer_menu, site_name}`.
- `Site\Setup`: gathers the snapshot, runs `Menus\Slots::ensureDefaults()` first, applies the plan
  with WordPress/Elementor APIs (`documents->create()` + `save()`, Pro's conditions manager
  `save_conditions()`), hooked on `admin_init` and activation.
- `Site\Protect`: the Editor locks above (`map_meta_cap`, `wp_insert_post_data`,
  `add|update|delete_post_metadata` for `_elementor_conditions`); `Protect::ids()` returns the four
  ids for other units. News joins `PageLock`'s locked set.
- `Editors\ElementorEditor` + `assets/elementor/editor.js`: hide the two Publish-menu items for
  Editors; Header/Footer exit label and URL.
- `Editors\Routing`: Header/Footer templates → Elementor for Editors.
- `Menus\Screen` + `templates/menus.php`: "Header & Footer" title and menu label, design buttons.
- `Dashboard\Screen::coreActions()`: "Edit header" / "Edit footer" when the templates exist.

## Edge cases

- Front page and posts page are the same page → News is replaced (candidate or new page); Home stays.
- `page_on_front` points to a trashed/deleted page → treated as unset (candidate, else create).
- Elementor (free) inactive → Home is created as a plain page; templates skipped.
- Elementor Pro deactivated later → recorded ids kept; template repair and locks idle until it's back.
- Several Entire Site headers → the newest published one is adopted; the others are left alone.
- A recorded template permanently deleted → adopt another Entire Site one, else create the starter.
- An adopted Home that isn't built with Elementor stays locked for Editors by `PageLock` (as today).
- Slug `home`/`news` taken by a trashed page → WordPress picks a unique slug for the new page.

## Testing

Unit: `Foundations::plan` (GOAABA-like state → no actions; fresh site → create all; front page
trashed; same page for both; News candidate by title; recorded template trashed/drafted/retargeted;
no Pro → no template actions; several Entire Site headers → newest), `Starter` (widget types, menu
slugs, logo vs. title), `Protect` decisions (which caps/statuses/meta are refused for whom).

Real check on sermonator-test with Elementor Pro 4.3.0 installed from `~/Downloads` (local only):
first Administrator visit creates Home, News, Header, Footer and sets Reading; front end shows the
starter Header/Footer with the Header/Footer menus; as the Editor: trash Home/News/Header (refused),
"Save as draft" hidden and a REST draft attempt stays published, conditions change refused, Header &
Footer screen buttons open Elementor, Exit returns; an Administrator drafts the Header → next visit
republishes it. Screenshots. GOAABA: read-only check that the plan for its state is empty.

## Out of scope

News post / News archive / 404 templates, the Site Kit (brand settings), Editors creating templates,
Settings screens for choosing the four by hand.
