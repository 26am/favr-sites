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

	public function test_the_time_of_a_change_is_its_last_modified_time(): void {
		$this->assertSame(
			strtotime( '2026-09-28 12:00:00 UTC' ),
			Activity::changed( array( '2026-09-28 12:00:00', '2026-09-20 08:00:00' ), 1 )
		);
	}

	public function test_a_draft_created_by_one_insert_falls_back_to_a_real_date(): void {
		// WordPress leaves post_modified_gmt at 0000-00-00 for a draft that was never saved again,
		// which read as "2028 years ago".
		$this->assertSame(
			strtotime( '2026-10-10 15:30:00 UTC' ),
			Activity::changed( array( '0000-00-00 00:00:00', '2026-10-10 15:30:00', '0000-00-00 00:00:00', '2026-10-09 09:00:00' ), 1 )
		);
	}

	public function test_an_item_without_any_date_counts_as_changed_now(): void {
		$now = strtotime( '2026-10-11 02:00:00 UTC' );
		$this->assertSame( $now, Activity::changed( array( '0000-00-00 00:00:00', '', 'not a date' ), $now ) );
		$this->assertSame( $now, Activity::changed( array(), $now ) );
	}

	public function test_a_date_before_wordpress_existed_is_not_a_real_date(): void {
		$now = strtotime( '2026-10-11 02:00:00 UTC' );
		$this->assertSame( $now, Activity::changed( array( '-0001-11-30 00:00:00', '1970-01-01 00:00:00' ), $now ) );
	}

	public function test_a_draft_with_no_gmt_dates_at_all_reads_as_added(): void {
		$this->assertSame( 'added', Activity::verb( '0000-00-00 00:00:00', '0000-00-00 00:00:00' ) );
	}

	public function test_title_strips_markup_and_falls_back(): void {
		$this->assertSame( 'About Us', Activity::title( '<em>About</em> Us' ) );
		$this->assertSame( '(no title)', Activity::title( '   ' ) );
	}
}
