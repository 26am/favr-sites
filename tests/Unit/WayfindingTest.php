<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\Wayfinding;

final class WayfindingTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_html' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
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
}
