<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Skin\Skin;

final class SkinTest extends TestCase {

	public function test_an_ordinary_screen_gets_every_sheet_in_order_base_first(): void {
		$this->assertSame( array( 'base', 'lists', 'edit', 'fields', 'screens', 'media' ), Skin::sheetsFor( false ) );
	}

	public function test_the_block_editor_only_gets_the_shell_and_the_media_window(): void {
		$this->assertSame( array( 'base', 'media' ), Skin::sheetsFor( true ) );
	}

	public function test_every_sheet_exists_on_disk(): void {
		foreach ( Skin::sheetsFor( false ) as $sheet ) {
			$this->assertFileExists( dirname( __DIR__, 2 ) . '/' . Skin::file( $sheet ) );
		}
	}

	public function test_the_body_class_is_added_once(): void {
		$this->assertSame( 'wp-core-ui favr-skin', Skin::bodyClass( 'wp-core-ui' ) );
		$this->assertSame( 'wp-core-ui favr-skin', Skin::bodyClass( 'wp-core-ui favr-skin' ) );
		$this->assertSame( 'favr-skin', Skin::bodyClass( '' ) );
	}

	public function test_the_brand_tokens_reach_the_skin_and_the_favr_screens(): void {
		$tokens = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/dashboard/tokens.css' );
		$this->assertMatchesRegularExpression( '/\.favr-dash-screen,\s*\.favr-skin\s*\{/', $tokens );
	}
}
