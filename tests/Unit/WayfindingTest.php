<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\Wayfinding;

require_once __DIR__ . '/stubs/wp-screen.php';

final class WayfindingTest extends TestCase {

	private const ADMIN = 'http://x.test/wp-admin/';

	protected function setUp(): void {
		parent::setUp();
		Audience::flush();
		Functions\when( 'esc_html' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn( $v ) => str_replace( '&', '&#038;', (string) $v ) );
	}

	public function test_an_edit_screen_goes_back_to_its_list(): void {
		$this->assertSame(
			array( 'label' => 'All events', 'path' => 'edit.php?post_type=favr_event' ),
			Wayfinding::forPostType( 'favr_event', 'All events', 'Events', true )
		);
	}

	public function test_news_and_media_have_their_own_list_addresses(): void {
		$this->assertSame( 'edit.php', Wayfinding::forPostType( 'post', 'All news', 'News', true )['path'] );
		$this->assertSame( 'upload.php', Wayfinding::forPostType( 'attachment', 'Media library', 'Media', true )['path'] );
		$this->assertSame( 'edit.php?post_type=page', Wayfinding::forPostType( 'page', 'All pages', 'Pages', true )['path'] );
	}

	public function test_a_post_type_without_an_all_items_label_uses_its_name(): void {
		$this->assertSame( 'Things', Wayfinding::forPostType( 'thing', '', 'Things', true )['label'] );
		$this->assertSame( 'Things', Wayfinding::forPostType( 'thing', '   ', 'Things', true )['label'] );
	}

	public function test_no_link_when_the_user_cannot_open_the_list(): void {
		$this->assertNull( Wayfinding::forPostType( 'favr_event', 'All events', 'Events', false ) );
		$this->assertNull( Wayfinding::forTaxonomy( 'favr_event_cat', 'favr_event', 'All categories', 'Categories', false ) );
	}

	public function test_no_link_without_a_place_or_a_name(): void {
		$this->assertNull( Wayfinding::forPostType( '', 'All events', 'Events', true ) );
		$this->assertNull( Wayfinding::forPostType( 'favr_event', '', '', true ) );
		$this->assertNull( Wayfinding::forTaxonomy( '', 'favr_event', 'All categories', 'Categories', true ) );
	}

	public function test_a_term_screen_goes_back_to_its_terms(): void {
		$this->assertSame(
			array( 'label' => 'All categories', 'path' => 'edit-tags.php?taxonomy=favr_event_cat&post_type=favr_event' ),
			Wayfinding::forTaxonomy( 'favr_event_cat', 'favr_event', 'All categories', 'Categories', true )
		);
	}

	public function test_news_categories_need_no_post_type_in_the_address(): void {
		$this->assertSame( 'edit-tags.php?taxonomy=category', Wayfinding::forTaxonomy( 'category', 'post', 'All categories', 'Categories', true )['path'] );
		$this->assertSame( 'edit-tags.php?taxonomy=category', Wayfinding::forTaxonomy( 'category', '', 'All categories', 'Categories', true )['path'] );
	}

	public function test_a_taxonomy_without_an_all_items_label_uses_its_name(): void {
		$this->assertSame( 'Membership levels', Wayfinding::forTaxonomy( 'favr_member_level', 'favr_member', '', 'Membership levels', true )['label'] );
	}

	public function test_the_link_is_one_escaped_paragraph(): void {
		$this->assertSame(
			'<p class="favr-back"><a href="http://x.test/wp-admin/edit-tags.php?taxonomy=favr_event_cat&#038;post_type=favr_event"><span aria-hidden="true">←</span> Tom &amp; Jerry &lt;b&gt;</a></p>',
			Wayfinding::html( 'Tom & Jerry <b>', 'http://x.test/wp-admin/edit-tags.php?taxonomy=favr_event_cat&post_type=favr_event' )
		);
	}

	public function test_the_way_back_remembers_the_search_view_page_and_order(): void {
		$this->assertSame(
			'edit.php?post_type=favr_member&s=Dubois&paged=2&orderby=title&order=asc',
			Wayfinding::listReturn( self::ADMIN . 'edit.php?post_type=favr_member&s=Dubois&paged=2&orderby=title&order=asc', 'favr_member', self::ADMIN )
		);
		$this->assertSame(
			'edit.php?post_type=favr_event&post_status=draft&favr_when=past',
			Wayfinding::listReturn( self::ADMIN . 'edit.php?post_type=favr_event&post_status=draft&favr_when=past', 'favr_event', self::ADMIN )
		);
	}

	public function test_what_just_happened_on_the_list_is_not_part_of_the_way_back(): void {
		$this->assertSame(
			'edit.php?post_type=favr_event&paged=3',
			Wayfinding::listReturn( self::ADMIN . 'edit.php?post_type=favr_event&trashed=1&ids=12&_wpnonce=abc&action=-1&action2=-1&filter_action=Filter&post[]=4&post[]=5&paged=3&locked=1&updated=2&deleted=1&untrashed=1', 'favr_event', self::ADMIN )
		);
	}

	public function test_a_search_with_spaces_and_signs_survives(): void {
		$this->assertSame(
			'edit.php?post_type=favr_member&s=Tom%20%26%20Jerry%20%3Cb%3E',
			Wayfinding::listReturn( self::ADMIN . 'edit.php?post_type=favr_member&s=Tom+%26+Jerry+%3Cb%3E', 'favr_member', self::ADMIN )
		);
	}

	public function test_news_and_media_lists_are_remembered_too(): void {
		$this->assertSame( 'edit.php?s=gala&paged=2', Wayfinding::listReturn( self::ADMIN . 'edit.php?s=gala&paged=2', 'post', self::ADMIN ) );
		$this->assertSame( 'edit.php?post_type=post&s=gala', Wayfinding::listReturn( self::ADMIN . 'edit.php?post_type=post&s=gala', 'post', self::ADMIN ) );
		$this->assertSame( 'edit.php', Wayfinding::listReturn( self::ADMIN . 'edit.php', 'post', self::ADMIN ) );
		$this->assertSame( 'upload.php?mode=list&s=logo', Wayfinding::listReturn( self::ADMIN . 'upload.php?mode=list&s=logo', 'attachment', self::ADMIN ) );
	}

	public function test_the_remembered_way_back_can_be_carried_as_a_path(): void {
		// The form carries it through a save without the site's address.
		$this->assertSame(
			'edit.php?post_type=favr_member&s=Dubois',
			Wayfinding::listReturn( 'edit.php?post_type=favr_member&s=Dubois', 'favr_member', self::ADMIN )
		);
	}

	public function test_only_the_record_types_own_list_counts(): void {
		$this->assertNull( Wayfinding::listReturn( self::ADMIN . 'edit.php?post_type=favr_event', 'favr_member', self::ADMIN ), 'another type' );
		$this->assertNull( Wayfinding::listReturn( self::ADMIN . 'edit.php', 'favr_member', self::ADMIN ), 'the News list' );
		$this->assertNull( Wayfinding::listReturn( self::ADMIN . 'edit.php?post_type=favr_member&page=favr-pay-invoices', 'favr_member', self::ADMIN ), 'Invoices lives under Members' );
		$this->assertNull( Wayfinding::listReturn( self::ADMIN . 'post.php?post=19&action=edit', 'favr_member', self::ADMIN ), 'an edit screen' );
		$this->assertNull( Wayfinding::listReturn( self::ADMIN . 'index.php', 'favr_member', self::ADMIN ), 'the dashboard' );
		$this->assertNull( Wayfinding::listReturn( self::ADMIN . 'upload.php', 'favr_member', self::ADMIN ), 'the media library' );
		$this->assertNull( Wayfinding::listReturn( '', 'favr_member', self::ADMIN ) );
	}

	public function test_an_address_outside_this_sites_admin_is_never_a_way_back(): void {
		$this->assertNull( Wayfinding::listReturn( 'http://evil.test/wp-admin/edit.php?post_type=favr_member', 'favr_member', self::ADMIN ) );
		$this->assertNull( Wayfinding::listReturn( '//evil.test/edit.php?post_type=favr_member', 'favr_member', self::ADMIN ) );
		$this->assertNull( Wayfinding::listReturn( '/wp-admin/edit.php?post_type=favr_member', 'favr_member', self::ADMIN ) );
		$this->assertNull( Wayfinding::listReturn( 'javascript:alert(1)//edit.php?post_type=favr_member', 'favr_member', self::ADMIN ) );
		$this->assertNull( Wayfinding::listReturn( '../edit.php?post_type=favr_member', 'favr_member', self::ADMIN ) );
	}

	public function test_approvals_is_recognised_as_where_the_editor_came_from(): void {
		$this->assertTrue( Wayfinding::isApprovals( self::ADMIN . 'admin.php?page=favr-approvals', self::ADMIN ) );
		$this->assertTrue( Wayfinding::isApprovals( 'admin.php?page=favr-approvals', self::ADMIN ) );
		$this->assertTrue( Wayfinding::isApprovals( self::ADMIN . 'admin.php?page=favr-approvals&done=1', self::ADMIN ) );
		$this->assertFalse( Wayfinding::isApprovals( self::ADMIN . 'admin.php?page=favr-menus', self::ADMIN ) );
		$this->assertFalse( Wayfinding::isApprovals( 'http://evil.test/wp-admin/admin.php?page=favr-approvals', self::ADMIN ) );
	}

	public function test_the_first_place_that_counts_is_where_the_editor_came_from(): void {
		$carried = 'edit.php?post_type=favr_member&s=Dubois';
		$referer = self::ADMIN . 'post.php?post=19&action=edit';
		$this->assertSame( $carried, Wayfinding::origin( array( '', $carried, $referer ), 'favr_member', self::ADMIN ) );
		$this->assertSame( 'admin.php?page=favr-approvals', Wayfinding::origin( array( self::ADMIN . 'admin.php?page=favr-approvals' ), 'favr_event', self::ADMIN ) );
		$this->assertNull( Wayfinding::origin( array( '', $referer, false, null ), 'favr_member', self::ADMIN ) );
	}

	public function test_the_link_goes_back_to_the_remembered_list(): void {
		$back = array( 'label' => 'All members', 'path' => 'edit.php?post_type=favr_member' );
		$this->assertSame(
			array( 'label' => 'All members', 'path' => 'edit.php?post_type=favr_member&s=Dubois' ),
			Wayfinding::resolve( $back, 'edit.php?post_type=favr_member&s=Dubois', 'publish', true, 'Approvals' )
		);
		$this->assertSame( $back, Wayfinding::resolve( $back, null, 'publish', true, 'Approvals' ) );
	}

	public function test_a_record_opened_from_approvals_goes_back_to_approvals(): void {
		$back = array( 'label' => 'All members', 'path' => 'edit.php?post_type=favr_member' );
		$this->assertSame(
			array( 'label' => 'Approvals', 'path' => 'admin.php?page=favr-approvals' ),
			Wayfinding::resolve( $back, 'admin.php?page=favr-approvals', 'publish', true, 'Approvals' )
		);
	}

	public function test_a_record_waiting_for_approval_goes_back_to_approvals(): void {
		$back = array( 'label' => 'All events', 'path' => 'edit.php?post_type=favr_event' );
		$this->assertSame(
			array( 'label' => 'Approvals', 'path' => 'admin.php?page=favr-approvals' ),
			Wayfinding::resolve( $back, null, 'pending', true, 'Approvals' )
		);
		$this->assertSame(
			array( 'label' => 'Approvals', 'path' => 'admin.php?page=favr-approvals' ),
			Wayfinding::resolve( $back, 'edit.php?post_type=favr_event&post_status=pending', 'pending', true, 'Approvals' )
		);
	}

	public function test_no_approvals_link_for_somebody_who_cannot_open_approvals(): void {
		$back = array( 'label' => 'All events', 'path' => 'edit.php?post_type=favr_event' );
		$this->assertSame( $back, Wayfinding::resolve( $back, null, 'pending', false, 'Approvals' ) );
		$this->assertSame( $back, Wayfinding::resolve( $back, 'admin.php?page=favr-approvals', 'publish', false, 'Approvals' ) );
	}

	public function test_the_waiting_notice_names_who_submitted_it(): void {
		$html = Wayfinding::waitingHtml( 'Ben <Carver>', 'http://x.test/wp-admin/admin.php?page=favr-approvals', 'Back to Approvals' );
		$this->assertStringContainsString( 'Waiting for approval · submitted by Ben &lt;Carver&gt;.', $html );
		$this->assertStringContainsString( '<a href="http://x.test/wp-admin/admin.php?page=favr-approvals">Back to Approvals</a>', $html );
		$this->assertStringStartsWith( '<div class="notice notice-warning favr-waiting">', $html );
	}

	public function test_the_waiting_notice_without_a_name_or_a_way_to_approvals(): void {
		$html = Wayfinding::waitingHtml( '', '', 'Back to Approvals' );
		$this->assertStringContainsString( '<p>Waiting for approval.</p>', $html );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	public function test_nothing_is_printed_or_carried_for_an_administrator(): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 1, array( 'administrator' ) ) );
		Functions\when( 'user_can' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn(
			new \WP_Screen(
				array(
					'id'        => 'favr_event',
					'base'      => 'post',
					'post_type' => 'favr_event',
				)
			)
		);
		Functions\expect( 'get_post_type_object' )->never();
		Functions\expect( 'wp_get_referer' )->never();
		Functions\expect( 'add_query_arg' )->never();

		$wayfinding = new Wayfinding();
		$this->expectOutputString( '' );
		$wayfinding->render();
		$wayfinding->field( new \WP_Post( array( 'post_type' => 'favr_event' ) ) );
		$this->assertSame( 'http://x.test/wp-admin/post.php?post=5&action=edit&message=1', $wayfinding->carry( 'http://x.test/wp-admin/post.php?post=5&action=edit&message=1', 5 ) );
	}
}
