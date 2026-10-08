<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Banner;

final class BannerTest extends TestCase {

	private const NOW = 1791216000;

	private static function clean( array $input, int $now = self::NOW ): array {
		$url = static fn( string $value ): string => preg_match( '#^(https://|mailto:|tel:|/[^/])#', $value ) ? $value : '';
		return Banner::clean( $input, $url, $now );
	}

	private static function moment( string $local, string $zone = 'America/New_York' ): \DateTimeImmutable {
		return new \DateTimeImmutable( $local, new \DateTimeZone( $zone ) );
	}

	public function test_defaults_are_off_standard_and_closable(): void {
		foreach ( array( array(), 'nonsense', null ) as $stored ) {
			$banner = Banner::fromArray( $stored );
			$this->assertFalse( $banner->enabled() );
			$this->assertSame( '', $banner->message() );
			$this->assertSame( 'standard', $banner->style() );
			$this->assertTrue( $banner->dismissible() );
		}
	}

	public function test_non_scalar_stored_values_count_as_missing(): void {
		$banner = Banner::fromArray( array( 'enabled' => array( 'x' ), 'message' => array( 'y' ), 'style' => array(), 'starts' => 5, 'updated' => 'soon' ) );
		$this->assertFalse( $banner->enabled() );
		$this->assertSame( '', $banner->message() );
		$this->assertSame( 'standard', $banner->style() );
		$this->assertSame( '', $banner->starts() );
		$this->assertSame( 0, $banner->updated() );
	}

	public function test_message_is_plain_text_trimmed_and_limited(): void {
		$banner = self::clean( array( 'message' => "  <b>Office closed</b>\n today <script>alert(1)</script> " ) )['banner'];
		$this->assertSame( 'Office closed today alert(1)', $banner->message() );

		$long = self::clean( array( 'message' => str_repeat( 'é', 250 ) ) )['banner'];
		$this->assertSame( 200, mb_strlen( $long->message() ) );
	}

	public function test_link_needs_an_address_and_gets_a_default_label(): void {
		$both = self::clean( array( 'link_label' => str_repeat( 'a', 60 ), 'link_url' => 'https://example.com/x' ) )['banner'];
		$this->assertSame( 40, mb_strlen( $both->linkLabel() ) );
		$this->assertSame( 'https://example.com/x', $both->linkUrl() );

		$no_label = self::clean( array( 'link_url' => '/closures/' ) )['banner'];
		$this->assertSame( 'Learn more', $no_label->linkLabel() );

		$no_url = self::clean( array( 'link_label' => 'Details' ) );
		$this->assertSame( '', $no_url['banner']->linkLabel() );
		$this->assertSame( array(), $no_url['warnings'] );

		$bad = self::clean( array( 'link_label' => 'Details', 'link_url' => 'javascript:alert(1)' ) );
		$this->assertSame( '', $bad['banner']->linkUrl() );
		$this->assertSame( '', $bad['banner']->linkLabel() );
		$this->assertCount( 1, $bad['warnings'] );
	}

	public function test_style_is_whitelisted(): void {
		$this->assertSame( 'urgent', self::clean( array( 'style' => 'urgent' ) )['banner']->style() );
		$this->assertSame( 'standard', self::clean( array( 'style' => 'rainbow' ) )['banner']->style() );
	}

	public function test_times_come_from_datetime_local_inputs(): void {
		$this->assertSame( '2026-10-07 09:30', self::clean( array( 'starts' => '2026-10-07T09:30' ) )['banner']->starts() );
		$this->assertSame( '2026-10-07 09:30', self::clean( array( 'starts' => '2026-10-07T09:30:15' ) )['banner']->starts() );
		$this->assertSame( '', self::clean( array( 'starts' => '2026-13-45T99:99' ) )['banner']->starts() );
		$this->assertSame( '', self::clean( array( 'starts' => 'tomorrow' ) )['banner']->starts() );
		$this->assertSame( '', self::clean( array( 'starts' => array( 'x' ) ) )['banner']->starts() );
	}

	public function test_hide_time_must_be_after_show_time(): void {
		foreach ( array( '2026-10-07T09:00', '2026-10-07T08:00' ) as $ends ) {
			$out = self::clean( array( 'starts' => '2026-10-07T09:00', 'ends' => $ends ) );
			$this->assertSame( '2026-10-07 09:00', $out['banner']->starts() );
			$this->assertSame( '', $out['banner']->ends() );
			$this->assertCount( 1, $out['warnings'] );
		}
		$ok = self::clean( array( 'starts' => '2026-10-07T09:00', 'ends' => '2026-10-07T17:00' ) );
		$this->assertSame( '2026-10-07 17:00', $ok['banner']->ends() );
		$this->assertSame( array(), $ok['warnings'] );
	}

	public function test_absent_checkboxes_are_off(): void {
		$off = self::clean( array( 'message' => 'Hi' ) )['banner'];
		$this->assertFalse( $off->enabled() );
		$this->assertFalse( $off->dismissible() );
		$on = self::clean( array( 'message' => 'Hi', 'enabled' => '1', 'dismissible' => '1' ) )['banner'];
		$this->assertTrue( $on->enabled() );
		$this->assertTrue( $on->dismissible() );
	}

	public function test_state_follows_the_window_in_the_site_time_zone(): void {
		$zone   = new \DateTimeZone( 'America/New_York' );
		$banner = self::clean( array( 'enabled' => '1', 'message' => 'Hi', 'starts' => '2026-10-07T09:00', 'ends' => '2026-10-07T17:00' ) )['banner'];
		$this->assertSame( 'pending', $banner->state( self::moment( '2026-10-07 08:59' ), $zone ) );
		$this->assertSame( 'live', $banner->state( self::moment( '2026-10-07 09:00' ), $zone ) );
		$this->assertSame( 'live', $banner->state( self::moment( '2026-10-07 16:59' ), $zone ) );
		$this->assertSame( 'off', $banner->state( self::moment( '2026-10-07 17:00' ), $zone ) );
		// The same instant expressed in another zone gives the same answer.
		$this->assertSame( 'live', $banner->state( self::moment( '2026-10-07 13:00', 'UTC' ), $zone ) );

		$always = self::clean( array( 'enabled' => '1', 'message' => 'Hi' ) )['banner'];
		$this->assertSame( 'live', $always->state( self::moment( '2030-01-01 00:00' ), $zone ) );
		$this->assertSame( 'off', self::clean( array( 'message' => 'Hi' ) )['banner']->state( self::moment( '2026-10-07 12:00' ), $zone ) );
		$this->assertSame( 'off', self::clean( array( 'enabled' => '1', 'message' => '   ' ) )['banner']->state( self::moment( '2026-10-07 12:00' ), $zone ) );
	}

	public function test_window_gives_utc_timestamps(): void {
		$zone   = new \DateTimeZone( 'America/New_York' );
		$banner = self::clean( array( 'starts' => '2026-10-07T09:00' ) )['banner'];
		$window = $banner->window( $zone );
		$this->assertSame( ( new \DateTimeImmutable( '2026-10-07 13:00', new \DateTimeZone( 'UTC' ) ) )->getTimestamp(), $window['from'] );
		$this->assertNull( $window['until'] );
	}

	public function test_version_changes_with_every_save(): void {
		$first  = self::clean( array( 'message' => 'Hi' ), 100 )['banner'];
		$again  = self::clean( array( 'message' => 'Hi' ), 100 )['banner'];
		$second = self::clean( array( 'message' => 'Hi' ), 200 )['banner'];
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{10}$/', $first->version() );
		$this->assertSame( $first->version(), $again->version() );
		$this->assertNotSame( $first->version(), $second->version() );
	}

	public function test_stored_shape_round_trips(): void {
		$banner = self::clean( array( 'enabled' => '1', 'message' => 'Hi', 'link_url' => '/x', 'style' => 'urgent', 'starts' => '2026-10-07T09:00', 'dismissible' => '1' ) )['banner'];
		$this->assertSame( $banner->toArray(), Banner::fromArray( $banner->toArray() )->toArray() );
		$this->assertSame( self::NOW, $banner->updated() );
	}
}
