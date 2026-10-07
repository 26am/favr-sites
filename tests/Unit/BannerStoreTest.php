<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Banner\Banner;
use FavrSites\Banner\Store;

final class BannerStoreTest extends TestCase {

	public function test_a_corrupted_option_means_no_banner(): void {
		foreach ( array( 'nonsense', false, 42, array( 'enabled' => array( 'x' ), 'message' => array( 'y' ) ) ) as $stored ) {
			Functions\when( 'get_option' )->justReturn( $stored );
			$this->assertSame( 'off', Store::get()->state( new \DateTimeImmutable( 'now' ), new \DateTimeZone( 'UTC' ) ) );
		}
	}

	public function test_save_writes_an_autoloaded_option_and_tells_caches(): void {
		$saved = null;
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value, $autoload ) use ( &$saved ) {
				$saved = array( $name, $value, $autoload );
				return true;
			}
		);
		$banner = Banner::fromArray( array( 'enabled' => true, 'message' => 'Hi' ) );
		Store::save( $banner );
		$this->assertSame( array( 'favr_sites_banner', $banner->toArray(), true ), $saved );
		$this->assertSame( 1, did_action( 'litespeed_purge_all' ) );
		$this->assertSame( 1, did_action( 'favr_sites_banner_saved' ) );
	}
}
