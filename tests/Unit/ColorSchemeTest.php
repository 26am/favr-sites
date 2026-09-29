<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Brand\ColorScheme;

final class ColorSchemeTest extends TestCase {

	public function test_editors_who_never_chose_a_scheme_get_favr(): void {
		$this->assertSame( 'favr', ColorScheme::forUser( false, true ) );
		$this->assertSame( 'favr', ColorScheme::forUser( '', true ) );
	}

	public function test_a_chosen_scheme_is_kept(): void {
		$this->assertSame( 'light', ColorScheme::forUser( 'light', true ) );
		$this->assertSame( 'fresh', ColorScheme::forUser( 'fresh', true ) );
	}

	public function test_everyone_else_is_left_alone(): void {
		$this->assertFalse( ColorScheme::forUser( false, false ) );
		$this->assertSame( 'midnight', ColorScheme::forUser( 'midnight', false ) );
	}
}
