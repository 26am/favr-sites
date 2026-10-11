<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\Chrome;

final class ChromeTest extends TestCase {

	public function test_the_boxes_editors_lose_on_every_edit_screen(): void {
		$this->assertSame(
			array( 'authordiv', 'slugdiv', 'pageparentdiv', 'postcustom', 'commentstatusdiv', 'commentsdiv', 'trackbacksdiv', 'revisionsdiv' ),
			Chrome::BOXES
		);
	}

	public function test_the_summary_box_stays(): void {
		// Favr Events uses WordPress's excerpt box for an event's short summary.
		$this->assertNotContains( 'postexcerpt', Chrome::BOXES );
		$this->assertNotContains( 'postexcerpt', array_column( Chrome::removals(), 0 ) );
	}

	public function test_every_box_is_removed_wherever_it_was_put(): void {
		$removals = Chrome::removals();
		$this->assertCount( count( Chrome::BOXES ) * 3, $removals );
		foreach ( Chrome::BOXES as $box ) {
			foreach ( array( 'normal', 'advanced', 'side' ) as $context ) {
				$this->assertContains( array( $box, $context ), $removals );
			}
		}
	}

	public function test_editors_have_no_screen_options(): void {
		$this->assertFalse( Chrome::showsScreenOptions( true, true ) );
		$this->assertFalse( Chrome::showsScreenOptions( false, true ) );
	}

	public function test_everybody_else_keeps_screen_options(): void {
		$this->assertTrue( Chrome::showsScreenOptions( true, false ) );
		$this->assertFalse( Chrome::showsScreenOptions( false, false ) );
	}

	public function test_nothing_an_editor_once_hid_stays_hidden_without_a_way_back(): void {
		$this->assertSame( array(), Chrome::hidden( array( 'postexcerpt', 'favr_event_details' ), true ) );
		$this->assertSame( array(), Chrome::hidden( false, true ) );
	}

	public function test_everybody_else_keeps_what_they_hid(): void {
		$this->assertSame( array( 'author' ), Chrome::hidden( array( 'author' ), false ) );
		$this->assertSame( array(), Chrome::hidden( 'broken', false ) );
	}

	public function test_the_permalink_can_still_be_edited_without_the_slug_box(): void {
		// WordPress keeps the slug field inside the Slug box; without the box the field is printed on its own.
		$this->assertTrue( Chrome::keepsSlugField( 'publish', true ) );
		$this->assertTrue( Chrome::keepsSlugField( 'draft', false ) );
		$this->assertTrue( Chrome::keepsSlugField( 'pending', true ) );
	}

	public function test_a_pending_item_keeps_its_slug_when_its_author_cannot_publish(): void {
		// The same rule WordPress uses for showing the Slug box.
		$this->assertFalse( Chrome::keepsSlugField( 'pending', false ) );
	}

	public function test_the_slug_box_counts_as_present_only_while_it_is_registered(): void {
		$boxes = array(
			'normal' => array(
				'core' => array(
					'slugdiv'     => array( 'id' => 'slugdiv' ),
					'postexcerpt' => array( 'id' => 'postexcerpt' ),
				),
			),
		);
		$this->assertTrue( Chrome::hasBox( $boxes, 'slugdiv' ) );
		$boxes['normal']['core']['slugdiv'] = false; // What remove_meta_box() leaves behind.
		$this->assertFalse( Chrome::hasBox( $boxes, 'slugdiv' ) );
		$this->assertFalse( Chrome::hasBox( array(), 'slugdiv' ) );
		$this->assertFalse( Chrome::hasBox( null, 'slugdiv' ) );
	}
}
