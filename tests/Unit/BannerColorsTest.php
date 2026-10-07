<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Banner\Colors;

final class BannerColorsTest extends TestCase {

	public function test_only_plain_hex_colours_are_valid(): void {
		$this->assertTrue( Colors::valid( '#B42318' ) );
		$this->assertTrue( Colors::valid( '#fff' ) );
		$this->assertFalse( Colors::valid( 'B42318' ) );
		$this->assertFalse( Colors::valid( '#B42318CC' ) );
		$this->assertFalse( Colors::valid( 'rgba(0,0,0,.5)' ) );
		$this->assertFalse( Colors::valid( '' ) );
	}

	public function test_text_colour_follows_contrast(): void {
		$this->assertSame( '#ffffff', Colors::textOn( '#2E2230' ) );
		$this->assertSame( '#ffffff', Colors::textOn( '#B42318' ) );
		$this->assertSame( '#1a1a1a', Colors::textOn( '#6EC1E4' ) );
		$this->assertSame( '#1a1a1a', Colors::textOn( '#fff' ) );
		$this->assertSame( '#ffffff', Colors::textOn( 'nonsense' ) );
	}
}
