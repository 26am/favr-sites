<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Banner;
use FavrSites\Banner\Screen;

final class BannerScreenTest extends TestCase {

	private static function status( array $stored, string $now ): string {
		$zone   = new \DateTimeZone( 'UTC' );
		$format = static fn( int $timestamp ): string => gmdate( 'j M H:i', $timestamp );
		return Screen::status( Banner::fromArray( $stored ), new \DateTimeImmutable( $now, $zone ), $zone, $format );
	}

	public function test_status_line_for_every_state(): void {
		$on = array( 'enabled' => true, 'message' => 'Hi' );
		$this->assertSame( 'The banner is off.', self::status( array( 'message' => 'Hi' ), '2026-10-07 12:00' ) );
		$this->assertSame( 'Add a message to show the banner.', self::status( array( 'enabled' => true ), '2026-10-07 12:00' ) );
		$this->assertSame( 'The banner is showing now.', self::status( $on, '2026-10-07 12:00' ) );
		$this->assertSame( 'The banner is showing now. It will hide on 7 Oct 17:00.', self::status( $on + array( 'ends' => '2026-10-07 17:00' ), '2026-10-07 12:00' ) );
		$this->assertSame( 'The banner will show from 7 Oct 09:00.', self::status( $on + array( 'starts' => '2026-10-07 09:00' ), '2026-10-07 08:00' ) );
		$this->assertSame( 'The banner stopped showing on 7 Oct 17:00.', self::status( $on + array( 'ends' => '2026-10-07 17:00' ), '2026-10-07 18:00' ) );
	}
}
