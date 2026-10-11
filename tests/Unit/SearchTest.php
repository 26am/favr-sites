<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\Search;

final class SearchTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Audience::flush();
	}

	/** Log a user in for Audience::current(). */
	private function user( int $id, array $roles, array $caps ): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( $id, $roles ) );
		Functions\when( 'is_user_logged_in' )->justReturn( $id > 0 );
		Functions\when( 'user_can' )->alias( static fn( $user, string $cap ): bool => in_array( $cap, $caps, true ) );
	}

	// ---------- The search term ----------

	public function test_the_term_is_cleaned_before_it_reaches_the_database(): void {
		$this->assertSame( 'Dubois', Search::term( "  Dubois \n" ) );
		$this->assertSame( 'Camille Dubois', Search::term( "Camille \t  Dubois" ) );
		$this->assertSame( 'bold', Search::term( '<b>bold</b>' ) );
		$this->assertSame( 'elena.marquez@example.com', Search::term( 'elena.marquez@example.com' ) );
	}

	public function test_searches_shorter_than_two_letters_are_ignored(): void {
		$this->assertSame( '', Search::term( '' ) );
		$this->assertSame( '', Search::term( ' a ' ) );
		$this->assertSame( '', Search::term( '<i></i>' ) );
		$this->assertSame( 'Li', Search::term( 'Li' ) );
		$this->assertSame( 'Çé', Search::term( 'Çé' ), 'Two letters, whatever their bytes.' );
	}

	public function test_the_term_is_no_longer_than_a_name_can_be(): void {
		$this->assertSame( Search::MAX_LENGTH, mb_strlen( Search::term( str_repeat( 'é', 300 ) ) ) );
	}

	public function test_anything_but_text_is_no_search(): void {
		$this->assertSame( '', Search::term( array( 'Dubois' ) ) );
		$this->assertSame( '', Search::term( null ) );
	}

	// ---------- The queries ----------

	public function test_every_record_an_editor_keeps_is_searched_in_the_menus_order(): void {
		$this->assertSame(
			array( 'favr_member', 'favr_business', 'favr_event', 'favr_document', 'favr_person', 'favr_sponsor', 'page', 'post' ),
			Search::TYPES
		);
	}

	public function test_a_type_is_searched_by_name_only_and_never_in_the_trash(): void {
		$query = Search::titleQuery( 'favr_event', 'Coffee' );
		$this->assertSame( 'favr_event', $query['post_type'] );
		$this->assertSame( 'Coffee', $query['s'] );
		$this->assertSame( array( 'post_title' ), $query['search_columns'] );
		$this->assertSame( array( 'publish', 'future', 'draft', 'pending', 'private' ), $query['post_status'] );
		$this->assertNotContains( 'trash', $query['post_status'] );
		$this->assertNotContains( 'auto-draft', $query['post_status'] );
	}

	public function test_a_query_is_cheap(): void {
		foreach ( array( Search::titleQuery( 'favr_member', 'Du' ), Search::fieldQuery( 'favr_member', 'Du', array(), 8 ) ) as $query ) {
			$this->assertSame( Search::PER_TYPE, $query['posts_per_page'] );
			$this->assertSame( 'ids', $query['fields'] );
			$this->assertTrue( $query['no_found_rows'] );
			$this->assertFalse( $query['update_post_meta_cache'] );
			$this->assertFalse( $query['update_post_term_cache'] );
		}
		$this->assertSame( 8, Search::PER_TYPE );
		$this->assertSame( 20, Search::TOTAL );
	}

	public function test_members_and_listings_are_also_found_by_company_or_email(): void {
		$query = Search::fieldQuery( 'favr_member', 'marquez@', array( 19, 21 ), 6 );
		$this->assertSame( 'favr_member', $query['post_type'] );
		$this->assertArrayNotHasKey( 's', $query );
		$this->assertSame( array( 19, 21 ), $query['post__not_in'], 'Members already found by name are not fetched twice.' );
		$this->assertSame( 6, $query['posts_per_page'], 'Only what is still missing is fetched.' );
		$this->assertSame( 'OR', $query['meta_query']['relation'] );
		$this->assertSame(
			array(
				array(
					'key'     => 'favr_member_organization',
					'value'   => 'marquez@',
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'favr_member_email',
					'value'   => 'marquez@',
					'compare' => 'LIKE',
				),
			),
			array( $query['meta_query'][0], $query['meta_query'][1] )
		);
		$listing = Search::fieldQuery( 'favr_business', 'Tax', array(), 8 );
		$this->assertSame( array( 'favr_organization', 'favr_email' ), array_column( array_filter( $listing['meta_query'], 'is_array' ), 'key' ) );
	}

	public function test_other_types_have_no_second_query(): void {
		foreach ( array( 'favr_event', 'favr_document', 'favr_person', 'favr_sponsor', 'page', 'post' ) as $type ) {
			$this->assertSame( array(), Search::fieldQuery( $type, 'Coffee', array(), 8 ), $type );
		}
		$this->assertSame( array(), Search::fieldQuery( 'favr_member', 'Du', array(), 0 ), 'Nothing is missing, nothing is fetched.' );
	}

	// ---------- Shaping the results ----------

	public function test_a_result_carries_a_title_a_type_and_a_link_and_nothing_else(): void {
		$this->assertSame(
			array(
				'title' => 'Camille Dubois',
				'type'  => 'Member',
				'url'   => 'http://example.test/wp-admin/post.php?post=63&action=edit',
			),
			Search::result( 'Camille Dubois', 'Member', 'http://example.test/wp-admin/post.php?post=63&action=edit' )
		);
	}

	public function test_a_title_is_plain_text(): void {
		$this->assertSame( "Marquez & Lee's", Search::result( 'Marquez &amp; <em>Lee</em>&#039;s', 'Member', 'u' )['title'] );
		$this->assertSame( '(no title)', Search::result( '  ', 'Page', 'u' )['title'] );
	}

	public function test_at_most_eight_of_a_type(): void {
		$groups = array( 'favr_member' => range( 1, 12 ) );
		$this->assertSame( array( 'favr_member' => range( 1, 8 ) ), Search::cap( $groups ) );
	}

	public function test_at_most_twenty_in_all_shared_fairly_between_the_types(): void {
		$capped = Search::cap(
			array(
				'favr_member'   => range( 1, 12 ),
				'favr_business' => range( 101, 112 ),
				'favr_event'    => range( 201, 208 ),
				'page'          => array( 301, 302 ),
			)
		);
		$this->assertSame( Search::TOTAL, array_sum( array_map( 'count', $capped ) ) );
		$this->assertSame( array( 'favr_member', 'favr_business', 'favr_event', 'page' ), array_keys( $capped ), 'The order of the types is kept.' );
		$this->assertSame( array( 301, 302 ), $capped['page'], 'A type with few matches is not crowded out.' );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6 ), $capped['favr_member'] );
		$this->assertSame( array( 101, 102, 103, 104, 105, 106 ), $capped['favr_business'] );
		$this->assertSame( array( 201, 202, 203, 204, 205, 206 ), $capped['favr_event'] );
	}

	public function test_types_without_matches_are_left_out(): void {
		$this->assertSame( array( 'page' => array( 4 ) ), Search::cap( array( 'favr_member' => array(), 'page' => array( 4 ) ) ) );
		$this->assertSame( array(), Search::cap( array() ) );
	}

	// ---------- The permission gate ----------

	public function test_only_a_logged_in_editor_may_search(): void {
		$this->user( 456, array( 'editor' ), array( 'read', 'edit_pages' ) );
		$this->assertTrue( Search::allowed() );
	}

	public function test_a_visitor_may_not_search(): void {
		$this->user( 0, array(), array() );
		$this->assertFalse( Search::allowed() );
	}

	public function test_other_roles_may_not_search(): void {
		$this->user( 9, array( 'subscriber' ), array( 'read' ) );
		$this->assertFalse( Search::allowed() );
		Audience::flush();
		$this->user( 1, array( 'administrator' ), array( 'read', 'manage_options' ) );
		$this->assertFalse( Search::allowed(), 'Administrators keep WordPress as it is.' );
	}

	public function test_only_records_this_editor_may_edit_are_listed(): void {
		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id = 0 ): bool => 'edit_post' === $cap && 64 !== $id );
		Functions\when( 'get_edit_post_link' )->alias( static fn( int $id ): string => 65 === $id ? '' : 'http://example.test/wp-admin/post.php?post=' . $id . '&action=edit' );
		Functions\when( 'get_post_field' )->alias( static fn( string $field, int $id ): string => 'Member ' . $id );

		$rows = Search::rows( array( 63, 64, 65, 66 ), 'Member' );

		$this->assertSame( array( 'Member 63', 'Member 66' ), array_column( $rows, 'title' ), '64 may not be edited, 65 has no edit screen.' );
		$this->assertSame( 'http://example.test/wp-admin/post.php?post=63&action=edit', $rows[0]['url'] );
		$this->assertSame( array( 'title', 'type', 'url' ), array_keys( $rows[0] ) );
	}

	// ---------- Screens an Editor has no use for ----------

	public function test_patterns_and_the_site_editor_are_closed(): void {
		$this->assertTrue( Search::closed( 'edit.php', 'wp_block' ) );
		$this->assertTrue( Search::closed( 'post-new.php', 'wp_block' ) );
		$this->assertTrue( Search::closed( 'site-editor.php', '' ) );
	}

	public function test_every_other_screen_stays_open(): void {
		$this->assertFalse( Search::closed( 'edit.php', 'page' ) );
		$this->assertFalse( Search::closed( 'edit.php', '' ) );
		$this->assertFalse( Search::closed( 'post.php', 'wp_block' ), 'A synced pattern can still be edited from inside a news post.' );
		$this->assertFalse( Search::closed( 'index.php', '' ) );
	}

	// ---------- What the script is told ----------

	public function test_the_palette_loses_the_commands_an_editor_cannot_use(): void {
		$this->assertContains( 'core/edit-site/basic-navigation', Search::LOADERS_OFF, 'Go to: Patterns.' );
		$this->assertContains( 'core/edit-site/navigate-pages', Search::LOADERS_OFF, 'Pages come from the Favr search.' );
		$this->assertContains( 'core/edit-site/navigate-posts', Search::LOADERS_OFF, 'News posts come from the Favr search.' );
	}

	public function test_the_script_gets_the_route_the_words_and_the_limits(): void {
		$settings = Search::settings();
		$this->assertSame( '/favr-sites/v1/search', $settings['path'] );
		$this->assertSame( 2, $settings['min'] );
		$this->assertSame( 'Find a member, event or page', $settings['placeholder'] );
		$this->assertSame( Search::LOADERS_OFF, $settings['off'] );
	}
}
