<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Icons;

final class IconsTest extends TestCase {

	public function test_pages_header_and_footer_each_have_their_own_icon(): void {
		// "Add document", "Edit pages", "Edit header" and "Edit footer" all wore the page outline.
		$drawn = array();
		foreach ( array( 'page', 'pages', 'header', 'footer' ) as $name ) {
			$svg = Icons::svg( $name );
			$this->assertStringStartsWith( '<svg ', $svg, $name . ' is drawn.' );
			$drawn[ $name ] = $svg;
		}
		$this->assertCount( 4, array_unique( $drawn ) );
	}

	public function test_an_unknown_name_draws_nothing(): void {
		$this->assertSame( '', Icons::svg( 'no-such-icon' ) );
	}
}
