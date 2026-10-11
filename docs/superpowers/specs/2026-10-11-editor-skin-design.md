# One Favr look and one way of working on every Editor screen

**Date:** 2026-10-11 · **Status:** built overnight at David's request in three rounds (build, join-up,
independent review and fixes). Every decision below is Claude's and awaits his confirmation.

## The request

David, 2026-10-11: "I want you to review the UX/UI of the whole experience for Editors. It's
disjointed, not efficient. Fix it please. Make your own best decisions. The dashboard and Menus
Editor seems the nicest, then everything else in the UI kinda sucks. It's not consistent."

## What was wrong (audit of all 28 Editor screens, 2026-10-11)

1. **Two products in one.** Dashboard, Header & Footer, Alert banner and Profile use the Favr look
   (cream paper, Fraunces headings, Source Sans text, rust accent, rounded cards, pill buttons).
   Every other screen (all lists, all edit screens, categories, Media, Approvals, Invoices) was
   stock WordPress: grey page, system font, blue links and buttons, square boxes.
2. **Real client staff never got the Favr colours.** WordPress saves its own default colour scheme
   for every new user, so "Favr by default for Editors who never chose" never applied.
3. **A flat 14-item menu** with website tools and membership records mixed together.
4. **Labels disagreed.** "All Businesses / Add New Business" (on a directory of people whose list
   was titled "Members", the same word as the Members menu), "All Events / Add New Event" against
   "All documents / Add document".
5. **Edit screens differed per type** and carried WordPress leftovers: Post Attributes/Template,
   Author, Excerpt, Screen Options, up/down arrows on every box. On Events the description editor
   sat below the field panel; on Directory it is inside it.
6. **Noise:** "Published 2026/10/08 at 11:02 pm" in every row, five empty "Nothing waiting."
   sections on Approvals, wrapped e-mail addresses in the Directory list.

## Decisions

| # | Decision | Why |
|---|----------|-----|
| D1 | One shared **skin** in Favr Sites restyles every wp-admin screen for Editors. Administrators keep stock WordPress. | Favr Sites owns the Editor experience; one place to keep consistent. favr/core cannot change without a tagged release. |
| D2 | Editors **always** get the Favr colour scheme; the scheme picker leaves their Profile screen. | Fixes audit item 2; the skin is drawn for this scheme. |
| D3 | The menu is **grouped**: Dashboard, Approvals · **Website**: Pages, News, Header & footer, Alert banner, Media · **Organization**: Members, Directory, Events, Documents, People, Sponsors · Profile. | Two jobs, two groups; the inbox stays at the top. |
| D4 | **Sentence case, "All x / Add x / Edit x"** everywhere. The Directory's admin words are "listing/listings" whatever the directory lists. | Audit item 4. |
| D5 | Edit screens lose Author, Slug, Post Attributes, Custom Fields, Discussion, Revisions boxes, Screen Options and Help for Editors. The web address stays editable from the permalink line under the name. Saved Screen Options choices (hidden boxes and columns, layout) no longer apply to Editors. | Editors cannot use them well; fewer things to read. |
| D6 | A **"← All events" back link** above the title on every edit screen. | One click back to the list, the same place on every screen. |
| D7 | List dates read "Oct 8, 2026"; the word "Published" only shows when the item is *not* published. | Noise. |
| D8 | Dashboard cards follow the menu's order. Quick actions and the toolbar's New menu are ordered by how often they are used: Add event, Add news post, Add member, Add directory listing, Add document, then the editing actions. | One mental map for places; speed for the daily jobs. (Changed in round 2: the first version put everything in menu order.) |
| D9 | US spelling in text Editors read ("color"). | Clients are in the US; the text mixed both. |
| D10 | Not changed: the layout of the block editor (News) and of the Elementor editor, the public site, wp-login.php, Administrator screens, Quick Edit, Bulk actions. Round 3 does give both editors the Favr accent and removes WordPress's welcome tour (see below). | Out of scope or not clearly better. |
| D11 | WordPress's own Title Case buttons and messages are reworded in sentence case for Editors (`Editors\Words`, about a hundred phrases, English only). | A product that says "Add Media" in one place and "Add event" in the next reads as two products. |
| D12 | Category and level screens lose the Slug field, the Slug column and WordPress's help paragraphs for Editors (`Editors\Terms`). | Same reason as D5. |
| D13 | The toolbar's New menu lists every type an Editor can add, in D8's order. | It offered three of nine. |
| D14 | Elementor's AI buttons are off for every Editor. | They were on for anyone who had never saved their profile. |

### Added in round 3 (after eight independent reviewers; also Claude's decisions)

| # | Decision | Why |
|---|----------|-----|
| D15 | An Editor is asked before leaving an edit screen with unsaved changes. | The new back link (D6) made it easy to lose a change typed into the field panel; WordPress only guards the title and the big editor. |
| D16 | Update stays in view on desktop (sticky Publish box and tabs); box titles do not fold or drag. | Long records meant scrolling back up for every save; a folded Publish box hid Update. |
| D17 | One page width: 1180px, 1400px for the Members, Directory and Invoices lists, 880px for form screens. | On a wide monitor lists ran edge to edge while the dashboard stopped at 1180px. |
| D18 | The toolbar search finds records (members, listings, events, documents, people, sponsors, pages, news, invoices); "Patterns" and the site editor are closed to Editors. | The only search in the toolbar found menu screens but no person. |
| D19 | "Add page" asks for a name first; pages get a "Rename" action. | Editors got a page called "Elementor #4413" they could not rename. |
| D20 | The News editor and the media window inside Elementor wear the Favr accent; no welcome tour; one press to publish; no Slug, Author or Template rows (D5 applies there too). | The one blue screen in the product. Their layouts stay WordPress's and Elementor's (D10). |
| D21 | Lists: 50 rows, Members and Directory A to Z, no "Mine" tab, no "Published" tab that repeats "All", no Count on Membership levels (it added members and listings together). | Finding a person by eye; fewer numbers that say nothing. |
| D22 | Header & footer saves both menus on one press. Approvals: Enter never approves, Reject asks once. | Saving one menu threw away the other's changes; Enter in a rejection note approved the item. |
| D23 | A renamed category keeps its web address; the permalink line stays on records. | Changing an address silently breaks links; Editors do sometimes need to change a record's address. |
| D24 | An event with a date and no start time is an all-day event (Favr Events). | "12:00 am" is never what the person meant. |

Left for David: membership details kept on both the listing and the member record; whether the
Directory Manager and Membership Manager roles should get the skin; darker field outlines for the
3:1 contrast guideline (it changes the reference screens too); the phone menu needing two taps;
one place for "View on site" on every list.

## Architecture

- `FavrSites\Skin\Skin` (Editors only, `Dashboard\Audience::current()`): body class `favr-skin`,
  loads `assets/dashboard/tokens.css` and `assets/skin/*.css`.
- **Tokens only** from `assets/dashboard/tokens.css` (`--favr-ink`, `--favr-muted`, `--favr-paper`,
  `--favr-surface`, `--favr-sand`, `--favr-line`, `--favr-accent`, `--favr-accent-strong`,
  `--favr-accent-soft`, `--favr-gold`, `--favr-aubergine`, `--favr-attention-soft`, `--favr-ok`,
  `--favr-ok-soft`, `--favr-danger`, `--favr-danger-soft`, `--favr-field`, `--favr-field-line`,
  `--favr-radius` 14px, `--favr-radius-sm` 10px, `--favr-shadow`, `--favr-font-display`,
  `--favr-font-sans`). No new hex colours in the sheets except neutral greys already in base.css;
  a missing colour is a new token.
- **Sheets, one owner each:**

  | Sheet | Surface |
  |-------|---------|
  | `base.css` | Shell (menu, toolbar font, page background) and the shared components: page title, title action, back link, headings, buttons, fields, notices, cards, plain `.widefat` tables. |
  | `lists.css` | Every list table (`edit.php`, `upload.php?mode=list`, plugin list tables): views, search, filters, pagination, row titles and row actions, density, empty state. Category/level screens (`edit-tags.php`): the add form and the table. |
  | `edit.css` | Classic edit screens (`post.php`, `post-new.php`): title field, permalink line, the WordPress editor box, all side boxes (Publish, categories, featured image), box headers. Term edit (`term.php`). Attachment edit. |
  | `fields.css` | favr/core's field panel (tabs, every field type, toggles, image and gallery pickers, hours, repeaters, counters, help text) and the plugins' own boxes: Listing health, Membership level, Listing managers, Login access, Directory listing, Billing, the Documents file box. |
  | `screens.css` | Whole plugin screens: Approvals, Invoices (list, one invoice, record a payment, export), and the plugins' list columns (letter tiles, profile bars, stars, status pills, level chips). |
  | `media.css` | Media Library grid and its toolbar, the uploader, attachment details, and the media window that opens from edit screens. |

- **Outside the six sheets** (each loaded by its own class, for Editors only):
  `assets/skin/edit-screen.js` (`Editors\Chrome`: the unsaved-changes question, calm box titles,
  tab dots), `assets/skin/search.js` and `search.css` (`Editors\Search`, loaded where WordPress
  loads its command palette), `assets/skin/approvals.js` (`Editors\Approvals`),
  `assets/editor/editor.css` and `editor.js` (`Editors\BlockEditor`, the News editor),
  `assets/editor/pages.css` (`Editors\Pages`, the "Add page" and "Rename" screens). Inside the
  Elementor editor `Editors\ElementorEditor` loads tokens.css and media.css and adds `favr-skin`
  to the body, so only the media window is restyled there.
- **Sticky edit screens:** on post.php / post-new.php from 851px wide and 600px high
  `#post-body.columns-2` is a grid, `#submitdiv` is sticky at `top: 48px` (z-index 2, capped at
  `calc(100vh - 64px)`), and `.favr-tabs` is sticky at the same offset above 1240px. Anything
  else that sticks on an edit screen uses the same 48px.
- **Selector rules** (see the header of `base.css`): shell rules start `body.favr-skin`; content
  rules start `body.favr-skin:where(:not(.favr-dash-screen))` so the four Favr screens are never
  touched. Wrap ids in `:where(#id)` so a rule never out-ranks a component by accident. No
  `!important` except to beat an inline style or another `!important`.
- **Reuse the components.** A button is `.button` / `.button-primary`; a card has the card border,
  radius 14px, surface background and `--favr-shadow`; a field has the field border and radius
  10px. Do not create a second button or card style. If a plugin's markup has its own button
  class, style it to *be* the shared button.

## The look, in rules

- Page: paper background; text ink 14.5px Source Sans ("Favr Sans"); muted text `--favr-muted`.
- Page title: Fraunces 500, 34px. Card and box titles: Fraunces 500, 16–19px. Field labels: sans
  600, 14px. Table headers: 12px uppercase, letter-spaced, muted.
- One filled (rust) button per screen region: the thing the screen is for. Everything else is the
  quiet button (surface, hairline border) or a text link. Destructive actions are `--favr-danger`
  text, never a filled red button.
- Cards: 1px `--favr-line`, radius 14px, `--favr-surface`, `--favr-shadow`. Rows inside cards are
  separated by 1px `--favr-line`; no zebra stripes; hover is `--favr-paper`.
- Status is a pill: 12px, 600, radius 999px, soft background + strong text (`--favr-ok(-soft)`,
  `--favr-attention-soft` + `--favr-accent`, `--favr-danger(-soft)`, `--favr-sand` + muted).
- Focus is always visible: 2px `--favr-accent` outline (links, buttons) or accent border + soft
  outline (fields).
- Works at 1440, 1024 and 390 px wide. Nothing overlaps, nothing scrolls sideways except a table
  that WordPress itself lets scroll.
- Reference screens for "does this look like Favr": the dashboard (`index.php`) and Header &
  footer (`admin.php?page=favr-menus`). Before/after screenshots: `ux/before/` in the scratchpad.

## Recipes (copy these values; do not invent variants)

Several sheets need the same small patterns. Use exactly these declarations so tabs, pills and
wells are identical wherever they appear.

- **Pill tab** (list views, category tabs in a box, the media window's tabs, any "All | Most used"
  switch): `display:inline-flex; align-items:center; min-height:30px; padding:0 12px;
  border:0; border-radius:999px; background:transparent; color:var(--favr-muted);
  font-size:13.5px; font-weight:600; text-decoration:none;` Current tab:
  `background:var(--favr-sand); color:var(--favr-ink);` Hover: `color:var(--favr-ink);`
  Tabs sit in a flex row with `gap:4px`; no "|" separators, no underlines, no borders.
- **Status pill:** `display:inline-flex; align-items:center; min-height:22px; padding:0 9px;
  border-radius:999px; font-size:12px; font-weight:600; line-height:1; white-space:nowrap;`
  good `background:var(--favr-ok-soft); color:var(--favr-ok)` · waiting
  `background:var(--favr-attention-soft); color:var(--favr-accent-strong)` · bad
  `background:var(--favr-danger-soft); color:var(--favr-danger)` · neutral
  `background:var(--favr-sand); color:var(--favr-muted)`.
- **Well** (a bordered area inside a card: a checklist, a details table, a preview):
  `border:1px solid var(--favr-line); border-radius:var(--favr-radius-sm);
  background:var(--favr-paper);`
- **Small caps label** (table headers, group labels): `color:var(--favr-muted); font-size:12px;
  font-weight:600; letter-spacing:0.06em; text-transform:uppercase;`
- **Dropzone** (image pickers, upload areas): `border:1.5px dashed var(--favr-field-line);
  border-radius:var(--favr-radius-sm); background:var(--favr-paper); color:var(--favr-muted);`
  Hover/focus: `border-color:var(--favr-accent); background:var(--favr-accent-soft);
  color:var(--favr-ink);`
- **Card padding:** side boxes `14px 16px`; wide cards `20px 22px`. Card title to content gap 12px.
- **Field, button, notice, card, table:** the values in `base.css`. Outside `#wpbody-content`
  (the media window) copy base.css's declarations into your own selectors.
- **Icons:** muted (`--favr-muted`) at rest, `--favr-accent` when their row/tab is current. Never
  WordPress blue (`#2271b1`, `#3858e9`, `#135e96`, `#0073aa`) and never pure grey boxes
  (`#f0f0f1`, `#f6f7f7`, `#dcdcde`, `#c3c4c7`): replace with tokens.

## Structure changes (Favr Sites, PHP)

- `Editors\Menu`: grouped order (D3) with separator entries carrying
  `favr-menu-group favr-menu-group--{website|organization|account}`; a heading only appears when
  its group has an entry; labels are printed as inline CSS on the `favr-sites-skin` handle
  (`#adminmenu li.favr-menu-group--website::before{content:"Website"}`), the account group has no
  label (base.css draws a hairline). Sentence-case relabels for Pages and Media; "Header & footer".
- `Brand\ColorScheme::forUser()` returns Favr for every Editor (done in the foundation commit);
  the picker is removed from `templates/profile.php`.
- New `Editors\Chrome`: no Screen Options, no Help tabs, the D5 boxes removed for Editors.
- New `Editors\Wayfinding`: the back link (`<p class="favr-back"><a>← All events</a></p>`,
  printed above `.wrap` on `post.php`, `post-new.php` and `term.php`).
- `Editors\ListTables`: D7 dates; the month filter is dropped on Favr post types.
  Round 3: one rule for row actions on every list (posts, categories, Media), 50 rows, no "Mine"
  or redundant "Published" tab, Members and Directory A to Z, whole pictures in the Media list
  and grid (`wholeTile()`), locked pages marked in the Pages list.
- `Editors\Words` (D11), `Editors\Terms` (D12, plus confirmations built from each taxonomy's own
  word and no Parent field or Count on Membership levels), `Editors\ErrorPage` (a Favr card for
  `wp_die` inside wp-admin; no "— WordPress" in the tab title).
- `Editors\Search`: REST route `favr-sites/v1/search` (logged-in Editors, `edit_post` per result,
  8 per type, 20 in all) and a command loader for WordPress's palette.
- `Editors\Pages`: the name-first "Add page" screen and "Rename" (nonce, `edit_post`, never a
  locked page). `Editors\BlockEditor`: the News editor's accent, preferences and labels.
- `Editors\Approvals`: the small script that keeps Enter from approving (favr/core prints the
  form and could not change).
- `Menus\Screen`: one press saves every menu with unsaved changes; the notice is printed inside
  the saved card.
- `Dashboard\Items`: D8 ordering by a known-id list, unknown ids keep their own order after.

## Sibling plugins

Labels (D4) and US spelling (D9), plus:

- **Events:** the description editor is a "Description" tab inside the field panel with a
  "Short summary" field (the old Excerpt box); the public description is wrapped paragraph by
  paragraph.
- **Directory and Members:** the level column is a chip with the level's color dot
  (`a.favr-level-chip > span.favr-level__swatch`), the same words for levels in both, and role
  capabilities are only ever added on a version change (never re-created).
- **Content:** the file type is a pill (`span.favr-doc-type`), members-only documents carry a
  lock, sponsor logos are shown whole.
- **Payments:** short dates on staff screens (`Dates::short()`), ledger status classes
  (`favr-pay-ledger-status--{succeeded|pending|failed|cancelled}`), a back link on one invoice.
- Every plugin always loads `jquery-ui-sortable` with favr/core's field script (the script calls
  `.sortable()` without checking).

Each plugin gets a patch version bump.

## Verification

`composer test` green and `composer lint` at 0 in every changed repo; every Editor screen
screenshotted at 1440 and 390 wide and compared with the rules above; Administrator screens
unchanged; no new console errors; the block editor and the Elementor editor still open.
