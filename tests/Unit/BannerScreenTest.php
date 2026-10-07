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
