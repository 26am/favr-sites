<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Display;

final class BannerDisplayTest extends TestCase {

	public function test_urgent_is_always_red_with_white_text(): void {
		$this->assertSame( array( 'bg' => '#B42318', 'fg' => '#ffffff' ), Display::colors( 'urgent', '#6EC1E4' ) );
	}

	public function test_standard_uses_the_kit_colour_with_readable_text(): void {
		$this->assertSame( array( 'bg' => '#6EC1E4', 'fg' => '#1a1a1a' ), Display::colors( 'standard', '#6EC1E4' ) );
		$this->assertSame( array( 'bg' => '#123456', 'fg' => '#ffffff' ), Display::colors( 'standard', '#123456' ) );
	}

	public function test_anything_but_a_plain_hex_kit_colour_falls_back(): void {
		foreach ( array( '', 'rgba(1,2,3,.5)', '#6EC1E4CC', 'var(--x)' ) as $kit ) {
			$this->assertSame( array( 'bg' => '#2E2230', 'fg' => '#ffffff' ), Display::colors( 'standard', $kit ) );
		}
	}
}
