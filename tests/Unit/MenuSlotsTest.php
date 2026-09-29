<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Menus\Slots;

final class MenuSlotsTest extends TestCase {

	public function test_saved_choice_then_slug_then_nothing(): void {
		$menus = array(
			3 => 'primary-navigation',
			4 => 'footer-explore',
			7 => 'footer',
		);
		$this->assertSame( array( 'header' => 3, 'footer' => 7 ), Slots::resolve( array( 'header' => 3 ), $menus ) );
		$this->assertSame( array( 'header' => null, 'footer' => 4 ), Slots::resolve( array( 'header' => 99, 'footer' => 4 ), $menus ) );
		$this->assertSame( array( 'header' => null, 'footer' => null ), Slots::resolve( 'garbage', array() ) );
	}

	public function test_sanitize_keeps_only_real_menus(): void {
		$menus = array( 3 => 'primary-navigation', 4 => 'footer-explore' );
		$this->assertSame( array( 'header' => 3, 'footer' => 0 ), Slots::sanitize( array( 'header' => '3', 'footer' => '99', 'extra' => 4 ), $menus ) );
	}
}
