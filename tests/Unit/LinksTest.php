<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Help\Links;

final class LinksTest extends TestCase {

	public function test_option_values_override_defaults_and_blanks_fall_back(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'help_url'      => 'https://help.example.com/',
				'support_email' => '',
				'support_phone' => '407 555 0100',
			)
		);
		$links = Links::all();
		$this->assertSame( 'https://help.example.com/', $links['help_url'] );
		$this->assertSame( Links::DEFAULTS['support_email'], $links['support_email'] );
		$this->assertSame( '407 555 0100', $links['support_phone'] );
	}

	public function test_fleet_defaults_are_favr_care(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$links = Links::all();
		$this->assertSame( 'care@favr.site', $links['support_email'] );
		$this->assertSame( '407-889-9987', $links['support_phone'] );
	}

	public function test_garbage_is_cleaned(): void {
		$this->assertSame( '', Links::clean( 'help_url', 'javascript:alert(1)' ) );
		$this->assertSame( '', Links::clean( 'support_email', 'not an email' ) );
		$this->assertSame( 'Call us', Links::clean( 'support_phone', '<b>Call us</b>' ) );
	}

	public function test_non_array_option_is_ignored(): void {
		Functions\when( 'get_option' )->justReturn( 'nonsense' );
		$this->assertSame( Links::DEFAULTS['help_url'], Links::all()['help_url'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constant_wins_over_option(): void {
		define( 'FAVR_SITES_HELP_URL', 'https://fleet.example.com/help' );
		Functions\when( 'get_option' )->justReturn( array( 'help_url' => 'https://site.example.com/' ) );
		$this->assertSame( 'https://fleet.example.com/help', Links::all()['help_url'] );
	}
}
