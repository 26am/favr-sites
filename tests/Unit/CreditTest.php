<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Site\Credit;

/**
 * "Website powered by Favr" at the bottom of every Favr site, unless staff turn it off.
 */
final class CreditTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'esc_url'     => static fn( $v ) => (string) $v,
				'esc_html__'  => static fn( $v ) => $v,
				'esc_attr'    => static fn( $v ) => (string) $v,
				'is_admin'    => false,
				'is_embed'    => false,
			)
		);
	}

	public function test_the_credit_shows_unless_someone_turned_it_off(): void {
		$this->assertTrue( Credit::shown( false ), 'a site that never chose (no option yet)' );
		$this->assertTrue( Credit::shown( 'on' ) );
		$this->assertTrue( Credit::shown( 'nonsense' ) );
		$this->assertFalse( Credit::shown( 'off' ) );
	}

	public function test_the_checkbox_is_stored_as_on_or_off(): void {
		$this->assertSame( 'on', Credit::stored( true ) );
		$this->assertSame( 'off', Credit::stored( false ) );
	}

	public function test_the_line_links_only_the_name_with_a_plain_followed_link(): void {
		$html = Credit::markup();
		$this->assertSame( 'Website powered by Favr', trim( strip_tags( $html ) ) );
		$this->assertMatchesRegularExpression( '#<a href="https://favr\.site/">Favr</a>#', $html, 'no rel, no target, no tracking: David asked for a followed link' );
		$this->assertStringNotContainsString( 'nofollow', $html );
		$this->assertStringNotContainsString( 'utm_', $html );
		$this->assertStringContainsString( 'data-favr-credit', $html, 'the page script finds it by this' );
	}

	public function test_it_is_printed_once_on_a_page_after_the_footer(): void {
		Functions\when( 'get_option' )->justReturn( false );
		$credit = new Credit();
		ob_start();
		$credit->afterFooter();
		$credit->fallback();
		$printed = (string) ob_get_clean();
		$this->assertSame( 1, substr_count( $printed, 'data-favr-credit' ) );
	}

	public function test_a_site_without_an_elementor_footer_gets_it_from_the_page_footer(): void {
		Functions\when( 'get_option' )->justReturn( 'on' );
		ob_start();
		( new Credit() )->fallback();
		$this->assertStringContainsString( 'data-favr-credit', (string) ob_get_clean() );
	}

	public function test_nothing_is_printed_once_it_is_turned_off(): void {
		Functions\when( 'get_option' )->justReturn( 'off' );
		$credit = new Credit();
		ob_start();
		$credit->afterFooter();
		$credit->fallback();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_the_header_and_footer_screen_has_the_switch(): void {
		$template = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/menus.php' );
		$this->assertStringContainsString( 'Credit::FIELD', $template );
		$this->assertStringContainsString( 'Credit::ACTION', $template );
		$this->assertStringContainsString( 'id="favr-credit"', $template );
	}

	public function test_nothing_is_printed_in_the_elementor_editor(): void {
		// Staff editing their footer would see a strip they can't select (the banner skips it too).
		Functions\when( 'get_option' )->justReturn( 'on' );
		$_GET['elementor-preview'] = '55';
		ob_start();
		( new Credit() )->afterFooter();
		$printed = (string) ob_get_clean();
		unset( $_GET['elementor-preview'] );
		$this->assertSame( '', $printed );
	}

	public function test_the_switch_saves_at_once_from_the_page_and_says_so_in_json(): void {
		$this->assertTrue( Credit::wantsJson( array( 'favr_js' => '1' ) ), 'the screen script saves without reloading, so unsaved menu edits stay' );
		$this->assertFalse( Credit::wantsJson( array() ), 'without scripts the form posts and comes back' );
		$template = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/menus.php' );
		$this->assertStringContainsString( 'data-favr-credit-form', $template );
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/menus/menus.js' );
		$this->assertStringContainsString( '[data-favr-credit-form]', $script );
	}
}
