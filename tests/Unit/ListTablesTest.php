<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\ListTables;

final class ListTablesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
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

	public function test_columns_drop_comments_and_yoast(): void {
		$cols = array(
			'cb'          => '',
			'title'       => 'Title',
			'author'      => 'Author',
			'comments'    => 'C',
			'wpseo-score' => 'SEO',
			'wpseo-links' => 'L',
			'date'        => 'Date',
		);
		$this->assertSame( array( 'cb', 'title', 'author', 'date' ), array_keys( ListTables::columns( $cols ) ) );
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
}
