<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\ListTables;

final class ListTablesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		Functions\when( 'date_i18n' )->alias( static fn( string $format, int $stamp ): string => gmdate( $format, $stamp ) );
		Audience::flush();
	}

	protected function tearDown(): void {
		Audience::flush();
		parent::tearDown();
	}

	public function test_only_view_and_trash_survive_and_trash_becomes_an_icon(): void {
		$actions = array(
			'edit'                 => '<a href="post.php?post=1&amp;action=edit">Edit</a>',
			'inline hide-if-no-js' => '<button>Quick&nbsp;Edit</button>',
			'trash'                => '<a href="post.php?post=1&amp;action=trash&amp;_wpnonce=abc" class="submitdelete" aria-label="Move “About” to the Trash">Trash</a>',
			'view'                 => '<a href="/about/">View</a>',
			'edit_with_elementor'  => '<a href="post.php?post=1&amp;action=elementor">Edit with Elementor</a>',
			'duplicate'            => '<a href="#">Duplicate</a>',
		);
		$out     = ListTables::rowActions( $actions );
		$this->assertSame( array( 'view', 'trash' ), array_keys( $out ) );
		$this->assertStringContainsString( 'href="post.php?post=1&amp;action=trash&amp;_wpnonce=abc"', $out['trash'] );
		$this->assertStringContainsString( 'class="favr-trash submitdelete"', $out['trash'] );
		$this->assertStringContainsString( '<svg', $out['trash'] );
		$this->assertStringNotContainsString( '>Trash<', $out['trash'] );
	}

	public function test_trash_view_keeps_restore_and_delete(): void {
		$actions = array(
			'untrash' => '<a href="#r">Restore</a>',
			'delete'  => '<a href="#d" class="submitdelete">Delete Permanently</a>',
		);
		$this->assertSame( $actions, ListTables::rowActions( $actions ) );
	}

	public function test_a_row_without_actions_keeps_the_toggle_a_phone_needs(): void {
		// WordPress prints "Show more details" with the row's actions, and only when there is one:
		// a draft page an Editor may not edit (the Privacy Policy page) has neither View nor Trash.
		$none = ListTables::rowActions( array() );
		$this->assertSame( array( ListTables::NO_ACTIONS ), array_keys( $none ) );
		$this->assertSame( '', $none[ ListTables::NO_ACTIONS ], 'Nothing to read or click.' );
		$this->assertMatchesRegularExpression( '/^[a-z-]+$/', ListTables::NO_ACTIONS, 'WordPress prints the key as a class name.' );
	}

	public function test_a_row_left_with_nothing_after_the_tidy_up_keeps_its_toggle_too(): void {
		$actions = array(
			'edit'                 => '<a href="post.php?post=1&amp;action=edit">Edit</a>',
			'inline hide-if-no-js' => '<button>Quick&nbsp;Edit</button>',
		);
		$this->assertSame( array( ListTables::NO_ACTIONS => '' ), ListTables::rowActions( $actions ) );
	}

	public function test_a_row_with_an_action_gets_no_placeholder(): void {
		$this->assertSame( array( 'view' => '<a href="/about/">View</a>' ), ListTables::rowActions( array( 'view' => '<a href="/about/">View</a>' ) ) );
	}

	public function test_columns_drop_author_comments_and_yoast(): void {
		$cols = array(
			'cb'          => '',
			'title'       => 'Title',
			'author'      => 'Author',
			'categories'  => 'Categories',
			'comments'    => 'C',
			'wpseo-score' => 'SEO',
			'wpseo-links' => 'L',
			'date'        => 'Date',
		);
		$this->assertSame( array( 'cb', 'title', 'categories', 'date' ), array_keys( ListTables::columns( $cols ) ) );
	}

	public function test_a_plugins_own_column_about_people_stays(): void {
		$cols = array(
			'cb'        => '',
			'title'     => 'Title',
			'favr_host' => 'Host',
			'author'    => 'Author',
		);
		$this->assertSame( array( 'cb', 'title', 'favr_host' ), array_keys( ListTables::columns( $cols ) ) );
	}

	public function test_columns_drop_rank_math_the_same_way(): void {
		$cols = array(
			'cb'                    => '',
			'title'                 => 'Title',
			'rank_math_seo_details' => 'SEO Details',
			'rank_math_title'       => 'SEO Title',
			'date'                  => 'Date',
		);
		$this->assertSame( array( 'cb', 'title', 'date' ), array_keys( ListTables::columns( $cols ) ) );
	}

	public function test_a_published_item_shows_only_its_date(): void {
		$this->assertSame( '', ListTables::dateStatus( 'Published', 'publish' ) );
	}

	public function test_anything_else_keeps_its_status_word(): void {
		$this->assertSame( 'Scheduled', ListTables::dateStatus( 'Scheduled', 'future' ) );
		$this->assertSame( '<strong class="error-message">Missed schedule</strong>', ListTables::dateStatus( '<strong class="error-message">Missed schedule</strong>', 'future' ) );
		$this->assertSame( 'Last modified', ListTables::dateStatus( 'Last Modified', 'draft' ) );
		$this->assertSame( 'Last modified', ListTables::dateStatus( 'Last Modified', 'pending' ) );
		$this->assertSame( 'Expired', ListTables::dateStatus( 'Expired', 'favr_expired' ), 'A word another plugin chose is left alone.' );
	}

	public function test_dates_read_like_oct_8_2026(): void {
		Functions\when( 'date_i18n' )->alias( static fn( string $format, int $stamp ): string => gmdate( $format, $stamp ) );
		$this->assertSame( 'Oct 8, 2026', ListTables::dateText( '2026/10/08 at 11:02 pm', '2026-10-08 23:02:00' ) );
		$this->assertSame( 'Jan 1, 2027', ListTables::dateText( '2027/01/01 at 12:00 am', '2027-01-01 00:00:00' ) );
	}

	public function test_an_item_without_a_date_keeps_wordpress_text(): void {
		Functions\when( 'date_i18n' )->alias( static fn( string $format, int $stamp ): string => gmdate( $format, $stamp ) );
		$this->assertSame( 'Unpublished', ListTables::dateText( 'Unpublished', '0000-00-00 00:00:00' ) );
		$this->assertSame( 'Unpublished', ListTables::dateText( 'Unpublished', '' ) );
		$this->assertSame( 'whenever', ListTables::dateText( 'whenever', 'not a date' ) );
	}

	public function test_the_month_filter_goes_on_favr_post_types_and_stays_for_news(): void {
		foreach ( array( 'favr_business', 'favr_event', 'favr_member', 'favr_document', 'favr_person', 'favr_sponsor' ) as $type ) {
			$this->assertTrue( ListTables::hidesMonths( false, $type ), $type );
		}
		$this->assertFalse( ListTables::hidesMonths( false, 'post' ) );
		$this->assertFalse( ListTables::hidesMonths( false, 'page' ) );
		$this->assertFalse( ListTables::hidesMonths( false, 'attachment' ) );
		$this->assertTrue( ListTables::hidesMonths( true, 'post' ), 'Somebody else already turned it off.' );
	}

	public function test_an_action_a_favr_plugin_added_stays_between_view_and_the_bin(): void {
		$actions = array(
			'edit'          => '<a href="post.php?post=1&amp;action=edit">Edit</a>',
			'trash'         => '<a href="post.php?post=1&amp;action=trash" class="submitdelete">Trash</a>',
			'favr-rename'   => '<a href="admin.php?page=favr-rename&amp;post=1">Rename</a>',
			'view'          => '<a href="/about/">View</a>',
			'favr-download' => '<a href="/file.pdf">Download</a>',
			'favrite'       => '<a href="#">Not ours</a>',
		);
		$out     = ListTables::rowActions( $actions );
		$this->assertSame( array( 'view', 'favr-rename', 'favr-download', 'trash' ), array_keys( $out ) );
		$this->assertSame( $actions['favr-rename'], $out['favr-rename'] );
	}

	public function test_the_bin_keeps_what_wordpress_put_on_its_link(): void {
		$core = '<a href="post.php?action=delete&amp;post=7&amp;_wpnonce=abc" class="submitdelete aria-button-if-js" onclick="return showNotice.warn();" aria-label="Delete &#8220;Logo&#8221; permanently">Delete Permanently</a>';
		$bin  = ListTables::binIcon( $core, 'Delete permanently' );
		$this->assertStringContainsString( 'href="post.php?action=delete&amp;post=7&amp;_wpnonce=abc"', $bin );
		$this->assertStringContainsString( 'class="favr-trash submitdelete aria-button-if-js"', $bin );
		$this->assertStringContainsString( 'onclick="return showNotice.warn();"', $bin, 'WordPress asks before deleting for good.' );
		$this->assertStringContainsString( 'aria-label="Delete &#8220;Logo&#8221; permanently"', $bin, 'The name of the item stays for a screen reader.' );
		$this->assertStringContainsString( 'title="Delete permanently"', $bin );
		$this->assertStringContainsString( '<svg', $bin );
		$this->assertStringNotContainsString( 'Delete Permanently<', $bin );
	}

	public function test_something_that_is_not_a_link_is_left_as_it_was(): void {
		$this->assertSame( '<button>Delete</button>', ListTables::binIcon( '<button>Delete</button>', 'Delete' ) );
	}

	public function test_a_category_or_level_row_has_view_and_the_bin_like_every_other_list(): void {
		$actions = array(
			'edit'                 => '<a href="term.php?taxonomy=favr_event_cat&amp;tag_ID=5">Edit</a>',
			'inline hide-if-no-js' => '<button type="button" class="button-link editinline">Quick&nbsp;Edit</button>',
			'delete'               => '<a href="edit-tags.php?action=delete&amp;taxonomy=favr_event_cat&amp;tag_ID=5&amp;_wpnonce=abc" class="delete-tag aria-button-if-js" aria-label="Delete &#8220;Mixers&#8221;">Delete</a>',
			'view'                 => '<a href="/event-category/mixers/">View</a>',
		);
		$out     = ListTables::termActions( $actions );
		$this->assertSame( array( 'view', 'delete' ), array_keys( $out ) );
		$this->assertStringContainsString( 'class="favr-trash delete-tag aria-button-if-js"', $out['delete'], 'WordPress\'s script finds the link by its class.' );
		$this->assertStringContainsString( 'tag_ID=5&amp;_wpnonce=abc"', $out['delete'] );
		$this->assertStringContainsString( 'title="Delete"', $out['delete'] );
		$this->assertStringContainsString( '<svg', $out['delete'] );
	}

	public function test_a_category_that_cannot_be_deleted_or_viewed_keeps_the_phone_toggle(): void {
		$this->assertSame( array( ListTables::NO_ACTIONS => '' ), ListTables::termActions( array( 'edit' => '<a href="#">Edit</a>' ) ) );
	}

	public function test_a_media_row_has_view_copy_url_and_the_bin(): void {
		$actions = array(
			'edit'     => '<a href="post.php?post=7&amp;action=edit">Edit</a>',
			'delete'   => '<a href="post.php?action=delete&amp;post=7" class="submitdelete aria-button-if-js" onclick="return showNotice.warn();">Delete Permanently</a>',
			'view'     => '<a href="/logo/">View</a>',
			'copy'     => '<span class="copy-to-clipboard-container"><button type="button" class="button-link copy-attachment-url media-library">Copy URL</button></span>',
			'download' => '<a href="/logo.png" download>Download file</a>',
			'attach'   => '<a href="#the-list">Attach</a>',
		);
		$out     = ListTables::mediaActions( $actions );
		$this->assertSame( array( 'view', 'copy', 'delete' ), array_keys( $out ) );
		$this->assertSame( $actions['copy'], $out['copy'] );
		$this->assertStringContainsString( 'class="favr-trash submitdelete aria-button-if-js"', $out['delete'] );
		$this->assertStringContainsString( 'onclick="return showNotice.warn();"', $out['delete'] );
		$this->assertStringContainsString( 'title="Delete permanently"', $out['delete'] );
	}

	public function test_media_in_the_trash_keeps_restore_and_delete_in_words(): void {
		$actions = array(
			'untrash' => '<a href="#r">Restore</a>',
			'delete'  => '<a href="#d" class="submitdelete">Delete Permanently</a>',
		);
		$this->assertSame( $actions, ListTables::mediaActions( $actions ) );
	}

	public function test_a_scheduled_item_keeps_its_time(): void {
		$this->assertSame( 'Oct 20, 2026, 9:00 am', ListTables::dateText( '2026/10/20 at 9:00 am', '2026-10-20 09:00:00', 'future' ) );
		$this->assertSame( 'Oct 20, 2026', ListTables::dateText( '2026/10/20 at 9:00 am', '2026-10-20 09:00:00', 'publish' ) );
		$this->assertSame( 'Oct 20, 2026', ListTables::dateText( '2026/10/20 at 9:00 am', '2026-10-20 09:00:00', 'draft' ) );
	}

	public function test_lists_show_fifty_rows_whatever_was_chosen_before(): void {
		$this->assertSame( 50, ListTables::perPage( 20 ) );
		$this->assertSame( 50, ListTables::perPage( 999 ) );
		$this->assertSame( 50, ListTables::perPage() );
	}

	public function test_the_mine_tab_goes_and_published_goes_when_it_says_the_same_as_all(): void {
		$views = array(
			'all'     => '<a href="edit.php?post_type=favr_sponsor" class="current" aria-current="page">All <span class="count">(8)</span></a>',
			'mine'    => '<a href="edit.php?post_type=favr_sponsor&#038;author=456">Mine <span class="count">(2)</span></a>',
			'publish' => '<a href="edit.php?post_status=publish&#038;post_type=favr_sponsor">Published <span class="count">(8)</span></a>',
		);
		$this->assertSame( array( 'all' ), array_keys( ListTables::views( $views, 'favr_sponsor' ) ) );
	}

	public function test_published_stays_when_it_tells_the_editor_something(): void {
		$views = array(
			'all'     => '<a href="edit.php" class="current">All <span class="count">(1,315)</span></a>',
			'publish' => '<a href="edit.php?post_status=publish">Published <span class="count">(1,314)</span></a>',
			'draft'   => '<a href="edit.php?post_status=draft">Draft <span class="count">(1)</span></a>',
			'trash'   => '<a href="edit.php?post_status=trash">Trash <span class="count">(3)</span></a>',
		);
		$this->assertSame( array( 'all', 'publish', 'draft', 'trash' ), array_keys( ListTables::views( $views, 'post' ) ) );
	}

	public function test_a_member_is_never_published_so_that_tab_always_goes(): void {
		$views = array(
			'all'         => '<a href="edit.php?post_type=favr_member" class="current">All <span class="count">(42)</span></a>',
			'publish'     => '<a href="edit.php?post_status=publish&#038;post_type=favr_member">Published <span class="count">(40)</span></a>',
			'draft'       => '<a href="edit.php?post_status=draft&#038;post_type=favr_member">Draft <span class="count">(2)</span></a>',
			'favr_active' => '<a href="edit.php?post_type=favr_member&#038;favr_status=active">Active <span class="count">(38)</span></a>',
		);
		$this->assertSame( array( 'all', 'draft', 'favr_active' ), array_keys( ListTables::views( $views, 'favr_member' ) ) );
	}

	public function test_the_tab_the_editor_is_on_is_never_taken_away(): void {
		$views = array(
			'all'     => '<a href="edit.php?post_type=favr_sponsor">All <span class="count">(8)</span></a>',
			'publish' => '<a href="edit.php?post_status=publish&#038;post_type=favr_sponsor" class="current" aria-current="page">Published <span class="count">(8)</span></a>',
		);
		$this->assertSame( array( 'all', 'publish' ), array_keys( ListTables::views( $views, 'favr_sponsor' ) ) );
		$this->assertSame( 'broken', ListTables::views( 'broken', 'post' ) );
	}

	public function test_a_logo_is_fitted_whole_into_the_list_thumbnail(): void {
		$this->assertSame( array( 60, 18 ), ListTables::thumbSize( 301, 90 ) );
		$this->assertSame( array( 30, 60 ), ListTables::thumbSize( 400, 800 ) );
		$this->assertSame( array( 40, 20 ), ListTables::thumbSize( 40, 20 ), 'A small picture is not blown up.' );
		$this->assertSame( array( 60, 1 ), ListTables::thumbSize( 6000, 10 ), 'Never no height at all.' );
		$this->assertSame( array( 60, 60 ), ListTables::thumbSize( 0, 0 ) );
	}

	public function test_members_open_a_to_z_and_the_directory_by_its_sort_as_value(): void {
		$this->assertSame(
			array(
				'orderby' => 'title',
				'order'   => 'ASC',
			),
			ListTables::defaultOrder( 'favr_member', '' )
		);
		$directory = ListTables::defaultOrder( 'favr_business', '' );
		$this->assertSame(
			array(
				'favr_sort' => 'ASC',
				'title'     => 'ASC',
			),
			$directory['orderby']
		);
		$this->assertSame( '_favr_sort', $directory['meta']['favr_sort']['key'] );
		$this->assertSame( 'OR', $directory['meta']['relation'], 'A listing without the value is still listed.' );
		$this->assertSame( 'NOT EXISTS', $directory['meta'][0]['compare'] );
	}

	public function test_an_order_the_editor_chose_and_every_other_list_are_left_alone(): void {
		$this->assertNull( ListTables::defaultOrder( 'favr_member', 'favr_renewal' ) );
		$this->assertNull( ListTables::defaultOrder( 'favr_business', 'date' ) );
		$this->assertNull( ListTables::defaultOrder( 'post', '' ) );
		$this->assertNull( ListTables::defaultOrder( 'favr_event', '' ) );
	}

	public function test_the_sort_arrow_moves_from_the_date_to_the_name(): void {
		$columns = array(
			'title'        => array( 'title', false, 'Title', 'Table ordered by Title.' ),
			'date'         => array( 'date', true, 'Date', 'Table ordered by Date.', 'desc' ),
			'favr_renewal' => 'favr_renewal',
		);
		$out     = ListTables::sortable( $columns );
		$this->assertSame( array( 'title', false, 'Title', 'Table ordered by Title.', 'asc' ), $out['title'] );
		$this->assertSame( array( 'date', true, 'Date', 'Table ordered by Date.' ), $out['date'] );
		$this->assertSame( 'favr_renewal', $out['favr_renewal'] );
		$this->assertSame( 'broken', ListTables::sortable( 'broken' ) );
	}

	public function test_nothing_is_hooked_for_somebody_who_is_not_an_editor(): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 1, array( 'administrator' ) ) );
		Functions\when( 'user_can' )->justReturn( true );
		Filters\expectAdded( 'post_row_actions' )->never();
		Filters\expectAdded( 'media_row_actions' )->never();
		Filters\expectAdded( 'edit_posts_per_page' )->never();
		Filters\expectAdded( 'manage_media_columns' )->never();
		$tables = new ListTables();
		foreach ( array( 'edit', 'upload', 'edit-tags' ) as $base ) {
			$tables->screen(
				(object) array(
					'base'      => $base,
					'id'        => 'edit-post',
					'post_type' => 'post',
					'taxonomy'  => 'category',
				)
			);
		}
		$actions = array( 'edit' => '<a href="#">Edit</a>' );
		$this->assertSame( $actions, $tables->termRow( $actions ), 'An Administrator keeps WordPress\'s links under a category.' );
	}

	public function test_an_editor_gets_the_calmer_list(): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 456, array( 'editor' ) ) );
		Functions\when( 'user_can' )->justReturn( false );
		Filters\expectAdded( 'post_row_actions' )->once();
		Filters\expectAdded( 'edit_posts_per_page' )->once();
		Filters\expectAdded( 'views_edit-post' )->once();
		$tables = new ListTables();
		$tables->screen(
			(object) array(
				'base'      => 'edit',
				'id'        => 'edit-post',
				'post_type' => 'post',
				'taxonomy'  => '',
			)
		);
		$this->assertSame( array( ListTables::NO_ACTIONS => '' ), $tables->termRow( array( 'edit' => '<a href="#">Edit</a>' ) ) );
	}

	public function test_the_sort_clause_never_widens_a_filter_somebody_else_set(): void {
		$mine  = array( 'relation' => 'OR' );
		$this->assertSame( array( $mine ), ListTables::withClause( array(), $mine ) );
		$this->assertSame( array( $mine ), ListTables::withClause( '', $mine ) );
		$theirs = array(
			'relation' => 'OR',
			array( 'key' => '_favr_dir_city', 'value' => 'Tampa', 'compare' => 'LIKE' ),
			array( 'key' => '_favr_dir_phone', 'value' => 'Tampa', 'compare' => 'LIKE' ),
		);
		$this->assertSame(
			array(
				'relation' => 'AND',
				$theirs,
				$mine,
			),
			ListTables::withClause( $theirs, $mine ),
			'Their either-or stays a group of its own.'
		);
	}

	public function test_after_the_trash_is_emptied_the_editor_lands_on_the_whole_list_with_the_same_message(): void {
		$this->assertSame(
			array(
				'post_type' => 'favr_event',
				'deleted'   => 1,
			),
			ListTables::leaveEmptyTrash( 'trash', 1, 0, 'favr_event' )
		);
		$this->assertSame( array( 'deleted' => 3 ), ListTables::leaveEmptyTrash( 'trash', 3, 0, 'post' ), 'News is edit.php without a type.' );
	}

	public function test_the_trash_is_only_left_when_it_has_just_been_emptied(): void {
		$this->assertNull( ListTables::leaveEmptyTrash( 'trash', 1, 2, 'favr_event' ), 'Something is still in it.' );
		$this->assertNull( ListTables::leaveEmptyTrash( 'trash', 0, 0, 'favr_event' ), 'Nothing was just deleted.' );
		$this->assertNull( ListTables::leaveEmptyTrash( 'draft', 1, 0, 'favr_event' ) );
		$this->assertNull( ListTables::leaveEmptyTrash( '', 1, 0, 'favr_event' ) );
	}

	// ---------- Media tiles ----------

	/**
	 * A small logo (301x90) has no "medium" file, so the grid and the media window fell back to
	 * WordPress's cropped thumbnail and showed "Sunris".
	 */
	public function test_a_small_picture_is_shown_whole_in_the_media_grid(): void {
		$full = array(
			'url'    => 'http://example.test/logo.png',
			'width'  => 301,
			'height' => 90,
		);
		$out  = ListTables::wholeTile(
			array(
				'type'  => 'image',
				'sizes' => array(
					'thumbnail' => array(
						'url'    => 'http://example.test/logo-150x90.png',
						'width'  => 150,
						'height' => 90,
					),
					'full'      => $full,
				),
			)
		);

		$this->assertSame( $full, $out['sizes']['medium'] );
		$this->assertSame( 'http://example.test/logo-150x90.png', $out['sizes']['thumbnail']['url'], 'The other sizes are left alone.' );
	}

	public function test_a_picture_with_its_own_medium_file_and_a_document_are_left_alone(): void {
		$picture = array(
			'type'  => 'image',
			'sizes' => array(
				'medium' => array( 'url' => 'http://example.test/photo-300x200.jpg' ),
				'full'   => array( 'url' => 'http://example.test/photo.jpg' ),
			),
		);
		$pdf     = array(
			'type' => 'application',
			'url'  => 'http://example.test/rules.pdf',
		);

		$this->assertSame( $picture, ListTables::wholeTile( $picture ) );
		$this->assertSame( $pdf, ListTables::wholeTile( $pdf ) );
		$this->assertSame( 'not a file', ListTables::wholeTile( 'not a file' ) );
	}

	public function test_an_administrators_media_tiles_are_wordpresss_own(): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 1, array( 'administrator' ) ) );
		Functions\when( 'user_can' )->justReturn( true );
		$file = array(
			'type'  => 'image',
			'sizes' => array( 'full' => array( 'url' => 'http://example.test/logo.png' ) ),
		);

		$this->assertSame( $file, ( new ListTables() )->tile( $file ) );
	}
}
