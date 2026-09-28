<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Activity;

final class ActivityTest extends TestCase {

	public function test_only_editable_types_are_listed(): void {
		$editable = static fn( string $type ): bool => in_array( $type, array( 'page', 'post', 'favr_event' ), true );
		$this->assertSame( array( 'page', 'post', 'favr_event' ), Activity::types( array( 'page', 'post', 'favr_member', 'favr_event', 42 ), $editable ) );
	}

	public function test_verb_says_added_when_never_edited_after_creation(): void {
		$this->assertSame( 'added', Activity::verb( '2026-09-28 10:00:00', '2026-09-28 10:00:40' ) );
		$this->assertSame( 'updated', Activity::verb( '2026-09-28 10:00:00', '2026-09-28 12:00:00' ) );
	}

	public function test_unpublished_draft_with_zero_date_reads_as_added(): void {
		$this->assertSame( 'added', Activity::verb( '0000-00-00 00:00:00', '2026-09-28 12:00:00' ) );
	}

	public function test_title_strips_markup_and_falls_back(): void {
		$this->assertSame( 'About Us', Activity::title( '<em>About</em> Us' ) );
		$this->assertSame( '(no title)', Activity::title( '   ' ) );
	}
}
