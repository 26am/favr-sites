<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Site\Starter;

final class StarterTest extends TestCase {

	private static function widgets( array $elements ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( 'widget' === $element['elType'] ) {
				$out[] = $element;
			}
			$out = array_merge( $out, self::widgets( $element['elements'] ) );
		}
		return $out;
	}

	public function test_header_is_brand_plus_the_header_menu(): void {
		$with_logo = self::widgets( Starter::header( true, 'header' ) );
		$this->assertSame( array( 'theme-site-logo', 'nav-menu' ), array_column( $with_logo, 'widgetType' ) );
		$this->assertSame( 'header', $with_logo[1]['settings']['menu'] );
		$without = self::widgets( Starter::header( false, '' ) );
		$this->assertSame( 'theme-site-title', $without[0]['widgetType'] );
		$this->assertArrayNotHasKey( 'menu', $without[1]['settings'] );
	}

	public function test_footer_is_the_footer_menu_and_a_copyright_line(): void {
		$widgets = self::widgets( Starter::footer( 'footer', 'GOAABA', '[elementor-tag id="x" name="current-date-time" settings="%7B%7D"]', 2026 ) );
		$this->assertSame( array( 'nav-menu', 'heading' ), array_column( $widgets, 'widgetType' ) );
		$this->assertSame( 'footer', $widgets[0]['settings']['menu'] );
		$this->assertStringContainsString( 'current-date-time', $widgets[1]['settings']['__dynamic__']['title'] );
		$static = self::widgets( Starter::footer( 'footer', 'GOAABA', '', 2026 ) );
		$this->assertSame( '© 2026 GOAABA', $static[1]['settings']['title'] );
	}

	public function test_home_says_the_site_name_and_ids_are_unique(): void {
		$home    = Starter::home( 'GOAABA', 'Greater Orlando Asian American Bar Association' );
		$widgets = self::widgets( $home );
		$this->assertSame( 'GOAABA', $widgets[0]['settings']['title'] );
		$this->assertSame( 'h1', $widgets[0]['settings']['header_size'] );
		$ids = array_merge( array_column( $home, 'id' ), array_column( $widgets, 'id' ) );
		$this->assertSame( $ids, array_unique( $ids ) );
		$this->assertCount( 1, self::widgets( Starter::home( 'GOAABA', '' ) ) ); // No empty tagline.
	}
}
