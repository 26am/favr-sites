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
			Activity::changed( array( '2026-09-28 12:00:00', '2026-09-20 08:00:00' ), strtotime( '2026-10-11 02:00:00 UTC' ) )
		);
	}

	public function test_a_draft_created_by_one_insert_falls_back_to_a_real_date(): void {
		// WordPress leaves post_modified_gmt at 0000-00-00 for a draft that was never saved again,
		// which read as "2028 years ago".
		$this->assertSame(
			strtotime( '2026-10-10 15:30:00 UTC' ),
			Activity::changed( array( '0000-00-00 00:00:00', '2026-10-10 15:30:00', '0000-00-00 00:00:00', '2026-10-09 09:00:00' ), strtotime( '2026-10-11 02:00:00 UTC' ) )
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

	public function test_a_date_in_the_future_is_not_when_something_changed(): void {
		// An item inserted already scheduled (import, WP-CLI) gets its publish date as its
		// modified date, which read as "3 months ago" for a post going out in three months.
		$now = strtotime( '2026-10-11 02:00:00 UTC' );
		$this->assertSame(
			strtotime( '2026-10-10 15:30:00 UTC' ),
			Activity::changed( array( '2027-01-05 09:00:00', '2026-10-10 15:30:00' ), $now )
		);
		$this->assertSame( $now, Activity::changed( array( '2027-01-05 09:00:00', '2027-01-05 09:00:00' ), $now ) );
	}

	public function test_a_scheduled_item_says_when_it_goes_out(): void {
		$now  = strtotime( '2026-10-11 02:00:00 UTC' );
		$date = static fn( string $format, int $stamp ): string => gmdate( $format, $stamp );
		$diff = static fn( int $from, int $to ): string => (string) round( ( $to - $from ) / 3600 ) . ' hours';
		$when = static fn( string $status, string $publish ): string => Activity::when( $status, (int) strtotime( $publish . ' UTC' ), $now - 7200, $now, $diff, $date );

		$this->assertSame( 'scheduled for Jan 5', $when( 'future', '2027-01-05 09:00:00' ) );
		$this->assertSame( 'scheduled for Oct 20', $when( 'future', '2026-10-20 09:00:00' ) );
		$this->assertSame( 'scheduled for Jan 5, 2028', $when( 'future', '2028-01-05 09:00:00' ), 'A year or more ahead needs its year.' );
		$this->assertSame( '2 hours ago', $when( 'publish', '2026-10-01 09:00:00' ) );
		$this->assertSame( '2 hours ago', $when( 'draft', '2027-01-05 09:00:00' ), 'Only a scheduled item is scheduled.' );
		$this->assertSame( '2 hours ago', $when( 'future', '2026-10-11 01:59:00' ), 'Its time has come; it reads like any other.' );
	}

	public function test_the_list_asks_only_for_changes_that_have_happened(): void {
		$now   = strtotime( '2026-10-11 02:00:00 UTC' );
		$query = Activity::query( array( 'page', 'post' ), 10, $now );
		$this->assertSame( array( 'page', 'post' ), $query['post_type'] );
		$this->assertSame( 10, $query['posts_per_page'] );
		$this->assertSame( 'modified', $query['orderby'] );
		$this->assertContains( 'future', $query['post_status'], 'A post scheduled in the editor still shows.' );
		// A modified date later than now is a publish date, not a change: it would pin the item to the top.
		$this->assertSame(
			array(
				array(
					'column' => 'post_modified_gmt',
					'before' => '2026-10-11 02:01:00',
				),
			),
			$query['date_query']
		);
	}

	public function test_a_draft_with_no_gmt_dates_at_all_reads_as_added(): void {
		$this->assertSame( 'added', Activity::verb( '0000-00-00 00:00:00', '0000-00-00 00:00:00' ) );
	}

	public function test_title_strips_markup_and_falls_back(): void {
		$this->assertSame( 'About Us', Activity::title( '<em>About</em> Us' ) );
		$this->assertSame( '(no title)', Activity::title( '   ' ) );
	}
}
