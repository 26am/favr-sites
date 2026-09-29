# Favr Sites: editor experience (v0.2)

Status: approved in conversation 2026-09-29, pending spec review.

## Why

WordPress's admin gives a client Editor too many ways to get lost: hover links under every row
(Quick Edit, Trash, Preview, plugin extras), a menu full of plugin entries, comments that only
attract spam, and, worst of all, buttons that switch a page between Elementor and the block editor
and scramble it. Favr sites are built in Elementor. Editors should get one editor per content type
and a tidy admin.

Success: an Editor can't end up in the wrong editor or on a locked page, and every list and menu
reads like a Favr product, not a WordPress control panel.

## Decisions

| Question | Decision |
|---|---|
| Who | Users the existing `Dashboard\Audience` includes (the Editor role). Administrators keep full WordPress. Exception: comments are off for everyone. |
| Pages | Elementor only. New pages are created in Elementor. Existing pages not built with Elementor are locked for Editors ("Managed by Favr"). |
| Posts ("News") | Block editor only, with a short block list. Elementor off for posts. |
| Enforcement | Permission-level (routing + `map_meta_cap`), not CSS. |
| Lists | Keep title link and View; Trash as a small icon; drop Quick Edit, Preview, "Edit with Elementor" and plugin extras; hide Comments and Yoast columns. |
| Menu | Dashboard, Pages, News, Media, Approvals, Directory, Events, Members, Profile, Tools. Everything else hidden for Editors (allow-list). "Posts" renamed "News". |
| Comments | Off site-wide for all users; data kept. |

## Units (`src/`)

All Editor-only units check `Dashboard\Audience::includes( wp_get_current_user() )` before hooking
behaviour (cheap; memoized per request in a small static on `Audience`).

- `Editors\PageLock` (pure core): `isLocked( string $post_type, string $status, bool $built_with_elementor, bool $elementor_active ): bool`
  → true only for `page`, status not `auto-draft`, not built with Elementor, and Elementor active.
  WordPress glue: `map_meta_cap` on `edit_post`, `delete_post`, `publish_post` for a locked page →
  `do_not_allow` for the audience; `display_post_states` adds "Managed by Favr" for the audience.
  "Built with Elementor" = post meta `_elementor_edit_mode` = `builder`. "Elementor active" =
  `did_action( 'elementor/loaded' )`.
- `Editors\Routing` (Elementor active + audience only):
  - `get_edit_post_link` for pages → `post.php?post={id}&action=elementor`.
  - `load-post.php` with `action=edit` on a page → redirect to the Elementor URL.
  - `load-post-new.php` for `post_type=page` → redirect to
    `\Elementor\Core\Documents_Manager::get_create_new_post_url( 'page' )`.
  - `elementor/document/urls/exit_to_dashboard` → `edit.php?post_type=page` for pages.
  - `init` (late) → `remove_post_type_support( 'post', 'elementor' )`, so Elementor offers nothing
    on posts (no row action, no switch button, no editor access).
- `Editors\BlockList`: for the audience on post type `post`:
  - `allowed_block_types_all` → `core/paragraph, core/heading, core/list, core/list-item,
    core/quote, core/image, core/gallery, core/embed, core/buttons, core/button, core/separator,
    core/table, core/file`.
  - `block_editor_settings_all` → `codeEditingEnabled` false, `enableOpenverseMediaCategory`
    false, `__experimentalBlockPatterns` [], `__experimentalBlockPatternCategories` [].
  - `should_load_remote_block_patterns` → false.
- `Editors\ListTables` (pure core + glue):
  - `rowActions( array $actions ): array` keeps only `view` and `trash`; everything else is
    dropped, including `edit` (the title is the edit link, and routes pages to Elementor). Trash's link text becomes an
    inline bin icon with `aria-label` "Move to Trash"; class `favr-trash`.
  - Hooks `post_row_actions` and `page_row_actions` at `PHP_INT_MAX`.
  - `columns( array $columns ): array` drops `comments` and any key starting with `wpseo-`.
    Hooked on `manage_posts_columns` and `manage_pages_columns` at `PHP_INT_MAX`.
  - Small inline CSS on `edit.php` for `.favr-trash` (muted until hover). No JS.
- `Editors\Menu` (pure core + glue):
  - `ORDER = [ 'index.php', 'edit.php?post_type=page', 'edit.php', 'upload.php', 'favr-approvals',
    'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event',
    'edit.php?post_type=favr_member', 'profile.php', 'tools.php' ]`.
  - `arrange( array $slugs ): array{keep: list<string>, remove: list<string>}` (pure): keep what's
    in ORDER (in ORDER's sequence), remove the rest, including separators.
  - Glue: `admin_menu` at `PHP_INT_MAX` → `remove_menu_page()` for the removals; `custom_menu_order`
    → true and `menu_order` → the kept order.
  - "News": `post_type_labels_post` rewrites the post type labels (menu name, name, singular,
    add new, add new item, edit item, new item, view, search, not found, all items) for the audience;
    admin-bar "New → Post" follows the labels.
- `Comments\Off` (everyone):
  - Front: `comments_open` and `pings_open` → false; `comments_array` → []; `get_comments_number`
    → 0; remove `comments` and `trackbacks` support from every post type on `init`.
  - Writes refused: `rest_endpoints` removes `/wp/v2/comments` routes; `xmlrpc_methods` removes
    `pingback.ping`; `pre_comment_approved` → `WP_Error`.
  - Admin (everyone): remove `edit-comments.php` menu and `options-discussion.php` submenu;
    admin-bar `comments` node removed; `load-edit-comments.php` redirects to the dashboard; the
    `comments` column is removed by this unit's own `manage_posts_columns` /
    `manage_pages_columns` filter.
  - No data is modified.

## Edge cases

- Elementor inactive: `PageLock::isLocked` false for everything; `Routing` doesn't hook. Pages use
  the block editor normally.
- An Editor has an existing non-Elementor draft page: locked (Administrators can convert it).
- Elementor changes its exit behaviour: worst case the page edit screen forwards back to Elementor
  (`load-post.php` redirect), so the Editor is never in the block editor on a page.
- Directory/Members/Events edit screens: untouched. Their list tables get the same row-action and
  column cleanup.
- Members' front-end forms and Elementor templates: unaffected.

## Testing

Unit (Brain Monkey): `PageLock::isLocked` truth table; `ListTables::rowActions` (keeps view/trash,
icon markup escaped, drops inline/elementor/plugin keys) and `columns`; `Menu::arrange` (order,
unknown plugin slugs removed, missing entries skipped); `BlockList::blocks()` for post vs page and
the settings overrides; `Comments\Off` pure helpers (`columns` strip).

Real check on `sermonator-test.local` as the Editor (user 19): pages open in Elementor (title,
Edit, admin bar, direct `action=edit` URL); Add New Page opens Elementor; Members pages show
"Managed by Favr" with no edit/trash; News opens the block editor with only the listed blocks and
no Elementor button; lists show only title/View + bin icon; menu order matches; no comments
anywhere. As Administrator: stock behaviour except comments. Screenshots of Pages list and menu.

## Out of scope

Login screen, admin bar redesign, wider wp-admin branding, AI controls, deleting existing comments.
