<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Screen;

final class ScreenTest extends TestCase {

	public function test_greeting_follows_the_clock(): void {
		$this->assertSame( 'Good morning', Screen::greeting( 6 ) );
		$this->assertSame( 'Good afternoon', Screen::greeting( 12 ) );
		$this->assertSame( 'Good evening', Screen::greeting( 18 ) );
		$this->assertSame( 'Good evening', Screen::greeting( 2 ) );
	}
}
