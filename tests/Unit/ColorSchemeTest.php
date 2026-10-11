<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Brand\ColorScheme;

final class ColorSchemeTest extends TestCase {

	public function test_editors_who_never_chose_a_scheme_get_favr(): void {
		$this->assertSame( 'favr', ColorScheme::forUser( false, true ) );
		$this->assertSame( 'favr', ColorScheme::forUser( '', true ) );
	}

	public function test_editors_always_get_favr_whatever_is_saved(): void {
		// WordPress saves its own default scheme for every new user, so "never chose" is rare.
		$this->assertSame( 'favr', ColorScheme::forUser( 'modern', true ) );
		$this->assertSame( 'favr', ColorScheme::forUser( 'fresh', true ) );
	}

	public function test_everyone_else_is_left_alone(): void {
		$this->assertFalse( ColorScheme::forUser( false, false ) );
		$this->assertSame( 'midnight', ColorScheme::forUser( 'midnight', false ) );
	}

	public function test_the_toolbar_keeps_the_favr_colours_on_the_public_site(): void {
		$this->assertTrue( ColorScheme::brandsToolbar( 'favr', false, true ) );
	}

	public function test_the_public_toolbar_is_left_alone_for_other_schemes_and_when_it_is_not_showing(): void {
		$this->assertFalse( ColorScheme::brandsToolbar( 'modern', false, true ) );
		$this->assertFalse( ColorScheme::brandsToolbar( 'favr', false, false ) );
		// In wp-admin the whole scheme is loaded already.
		$this->assertFalse( ColorScheme::brandsToolbar( 'favr', true, true ) );
	}

	public function test_the_public_toolbar_styles_are_the_schemes_own_toolbar_rules(): void {
		$root    = dirname( __DIR__, 2 ) . '/assets/admin-colors/favr/';
		$scheme  = (string) file_get_contents( $root . 'colors.css' );
		$toolbar = trim( (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . 'toolbar.css' ) ) );
		$rules   = array_filter( array_map( 'trim', explode( '}', $toolbar ) ) );
		$this->assertGreaterThan( 15, count( $rules ) );
		foreach ( $rules as $rule ) {
			$this->assertStringContainsString( '#wpadminbar', $rule );
			$this->assertStringContainsString( $rule . '}', $scheme, 'Rebuild toolbar.css after changing the scheme: ' . substr( $rule, 0, 60 ) );
		}
	}
}
