<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use FavrSites\Site\SourceNote;

/**
 * The note at the top of every page's source: who made the site, for anyone who views it.
 */
final class SourceNoteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'is_admin' => false,
				'is_feed'  => false,
			)
		);
	}

	public function test_the_note_says_who_made_the_site_and_names_the_generator(): void {
		$this->assertSame(
			"<!--\n\tThis site is powered by Favr.\n\tWebsites for Associations, Clubs and Chambers: https://favr.site/\n-->\n<meta name=\"generator\" content=\"Favr\">\n",
			SourceNote::markup()
		);
	}

	public function test_it_is_the_first_thing_added_to_the_head(): void {
		Actions\expectAdded( 'wp_head' )->once()->with( \Mockery::type( 'array' ), PHP_INT_MIN );
		( new SourceNote() )->hook();
		$this->addToAssertionCount( 1 );
	}

	public function test_it_is_printed_once_on_a_page(): void {
		$note = new SourceNote();
		ob_start();
		$note->print();
		$note->print();
		$this->assertSame( 1, substr_count( (string) ob_get_clean(), 'powered by Favr' ) );
	}

	public function test_nothing_in_the_elementor_editor(): void {
		$_GET['elementor-preview'] = '12';
		ob_start();
		( new SourceNote() )->print();
		$printed = (string) ob_get_clean();
		unset( $_GET['elementor-preview'] );
		$this->assertSame( '', $printed );
	}
}
