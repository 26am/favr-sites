<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Banner\Banner;
use FavrSites\Banner\Screen;
use FavrSites\Dashboard\Audience;

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

	public function test_the_saved_message_says_what_visitors_see_now(): void {
		$zone  = new \DateTimeZone( 'UTC' );
		$now   = new \DateTimeImmutable( '2026-10-07 12:00', $zone );
		$saved = static fn( array $stored ): string => Screen::savedNotice( Banner::fromArray( $stored ), $now, $zone );
		$on    = array( 'enabled' => true, 'message' => 'Hi' );
		$this->assertSame( 'Banner saved. It is off the site now.', $saved( array( 'message' => 'Hi' ) ) );
		$this->assertSame( 'Banner saved. It is showing on the site now.', $saved( $on ) );
		$this->assertSame( 'Banner saved. It is not on the site yet.', $saved( $on + array( 'starts' => '2026-10-08 09:00' ) ) );
		$this->assertSame( 'Banner saved. Its hide time has passed, so it is off the site.', $saved( $on + array( 'ends' => '2026-10-07 09:00' ) ) );
		$this->assertSame( 'Banner saved. It needs a message before it can show.', $saved( array( 'enabled' => true ) ) );
	}

	public function test_the_time_zone_reads_as_a_plain_name(): void {
		$name = static fn( string $zone, string $now = '2026-10-07 12:00' ): string => Screen::zoneName( new \DateTimeZone( $zone ), new \DateTimeImmutable( $now, new \DateTimeZone( 'UTC' ) ) );
		$this->assertSame( 'Eastern Time', $name( 'America/New_York' ) );
		$this->assertSame( 'Eastern Time', $name( 'America/New_York', '2026-01-07 12:00' ), 'The same name in winter and in summer.' );
		$this->assertSame( 'Central Time', $name( 'America/Chicago' ) );
		$this->assertSame( 'Mountain Time', $name( 'America/Denver' ) );
		$this->assertSame( 'Mountain Time', $name( 'America/Phoenix' ) );
		$this->assertSame( 'Pacific Time', $name( 'America/Los_Angeles' ) );
		$this->assertSame( 'Alaska Time', $name( 'America/Anchorage' ) );
		$this->assertSame( 'Hawaii Time', $name( 'Pacific/Honolulu' ) );
		$this->assertSame( 'London time', $name( 'Europe/London' ) );
		$this->assertSame( 'Buenos Aires time', $name( 'America/Argentina/Buenos_Aires' ) );
		$this->assertSame( 'Shanghai time', $name( 'Asia/Shanghai' ), 'China Standard Time is not Central Time.' );
		$this->assertSame( 'UTC', $name( 'UTC' ) );
		$this->assertSame( 'UTC+05:30', $name( '+05:30' ) );
	}

	public function test_the_preview_is_labelled_when_the_banner_is_not_on_the_site(): void {
		$this->assertSame( '', Screen::previewLabel( 'live' ) );
		$this->assertSame( 'Preview (not on the site)', Screen::previewLabel( 'off' ) );
		$this->assertSame( 'Preview (not on the site yet)', Screen::previewLabel( 'pending' ) );
	}

	/**
	 * Load the screen as a user with these roles and capabilities; was notice stripping hooked?
	 *
	 * @param list<string> $roles Roles.
	 * @param list<string> $caps  Capabilities.
	 */
	private static function stripsNoticesFor( array $roles, array $caps ): bool {
		foreach ( array( 'FAVR_SITES_PATH' => dirname( __DIR__, 2 ) . '/', 'FAVR_SITES_URL' => 'https://example.test/wp-content/plugins/favr-sites/', 'FAVR_SITES_VERSION' => '0.0.0' ) as $name => $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 5, $roles ) );
		Functions\when( 'user_can' )->alias( static fn( $user, string $cap ): bool => in_array( $cap, $caps, true ) );
		Audience::flush();
		( new Screen() )->load();
		return (bool) has_action( 'in_admin_header' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_editors_get_a_screen_without_plugin_notices(): void {
		$this->assertTrue( self::stripsNoticesFor( array( 'editor' ), array( 'edit_pages' ) ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_administrators_keep_their_notices(): void {
		$this->assertFalse( self::stripsNoticesFor( array( 'administrator' ), array( 'edit_pages', 'manage_options' ) ) );
	}
}
