<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\ErrorPage;

final class ErrorPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_html' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_kses_post' )->alias( static fn( $v ) => strip_tags( (string) $v, '<p><a><strong><em><br><ul><li><code>' ) );
		Audience::flush();
	}

	protected function tearDown(): void {
		Audience::flush();
		parent::tearDown();
	}

	private function seenBy( string $role, bool $in_admin = true ): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 'editor' === $role ? 456 : 1, array( $role ) ) );
		Functions\when( 'user_can' )->justReturn( 'editor' !== $role );
		Functions\when( 'is_admin' )->justReturn( $in_admin );
		Functions\when( 'did_action' )->justReturn( 0 );
	}

	/** @return array<string, mixed> */
	private function page( array $over = array() ): array {
		return $over + array(
			'title'      => 'We couldn’t open that',
			'site'       => 'Tampa Bay CPAs',
			'home'       => 'https://example.org/wp-admin/',
			'stylesheet' => 'https://example.org/wp-content/plugins/favr-sites/assets/dashboard/tokens.css?ver=1',
			'errors'     => array(),
			'link_url'   => '',
			'link_text'  => '',
			'back'       => false,
			'rtl'        => false,
			'charset'    => 'utf-8',
		);
	}

	public function test_the_card_carries_the_message_and_a_way_back_to_the_dashboard(): void {
		$html = ErrorPage::page( 'You attempted to edit an item that does not exist. Perhaps it was deleted?', $this->page() );
		$this->assertStringStartsWith( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<p>You attempted to edit an item that does not exist. Perhaps it was deleted?</p>', $html );
		$this->assertStringContainsString( '<a class="favr-error__home" href="https://example.org/wp-admin/">', $html );
		$this->assertStringContainsString( 'Dashboard</a>', $html );
		$this->assertStringContainsString( 'tokens.css?ver=1', $html, 'The colours and the type are the brand tokens.' );
		$this->assertStringContainsString( '<body class="favr-skin favr-error">', $html );
		$this->assertStringContainsString( '<title>We couldn’t open that ‹ Tampa Bay CPAs</title>', $html );
		$this->assertStringNotContainsString( 'WordPress', $html );
	}

	public function test_a_message_with_its_own_paragraphs_and_links_keeps_them(): void {
		$html = ErrorPage::page( '<p>The link you followed has expired.</p><p><a href="/wp-admin/edit.php">Please try again.</a></p><script>alert(1)</script>', $this->page() );
		$this->assertStringContainsString( '<p>The link you followed has expired.</p><p><a href="/wp-admin/edit.php">Please try again.</a></p>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<p><p>', $html );
	}

	public function test_several_errors_are_listed_and_wordpress_own_links_stay(): void {
		$html = ErrorPage::page(
			'First.',
			$this->page(
				array(
					'errors'    => array( 'Second.', 'Third <b>one</b>.' ),
					'link_url'  => 'https://example.org/wp-admin/upload.php',
					'link_text' => 'Back to the library',
					'back'      => true,
				)
			)
		);
		$this->assertStringContainsString( '<li>Second.</li>', $html );
		$this->assertStringContainsString( '<li>Third one.</li>', $html );
		$this->assertStringContainsString( '<a href="https://example.org/wp-admin/upload.php">Back to the library</a>', $html );
		$this->assertStringContainsString( 'href="javascript:history.back()"', $html );
	}

	public function test_only_an_editor_inside_wp_admin_before_the_page_has_started_gets_the_card(): void {
		$page = new ErrorPage();
		$this->seenBy( 'editor' );
		$this->assertSame( array( $page, 'render' ), $page->handler( '_default_wp_die_handler' ) );

		Audience::flush();
		$this->seenBy( 'administrator' );
		$this->assertSame( '_default_wp_die_handler', $page->handler( '_default_wp_die_handler' ) );

		Audience::flush();
		$this->seenBy( 'editor', false );
		$this->assertSame( '_default_wp_die_handler', $page->handler( '_default_wp_die_handler' ), 'The public site keeps its own error page.' );

		Audience::flush();
		$this->seenBy( 'editor' );
		Functions\when( 'did_action' )->justReturn( 1 );
		$this->assertSame( '_default_wp_die_handler', $page->handler( '_default_wp_die_handler' ), 'Inside a page that already has its frame, WordPress prints the message in place.' );
	}

	public function test_the_browser_tab_does_not_end_in_wordpress(): void {
		$this->assertSame( 'Dashboard &lsaquo; Favr Demo', ErrorPage::tabTitle( 'Dashboard &lsaquo; Favr Demo &#8212; WordPress' ) );
		$this->assertSame( 'Pages ‹ Favr Demo', ErrorPage::tabTitle( 'Pages ‹ Favr Demo — WordPress' ) );
		$this->assertSame( 'Why WordPress &lsaquo; Favr Demo', ErrorPage::tabTitle( 'Why WordPress &lsaquo; Favr Demo &#8212; WordPress' ), 'Only the ending goes.' );
		$this->assertSame( 'Dashboard', ErrorPage::tabTitle( 'Dashboard' ) );
	}

	public function test_an_administrator_keeps_wordpress_tab_title(): void {
		$this->seenBy( 'administrator' );
		$this->assertSame( 'Dashboard &lsaquo; Favr Demo &#8212; WordPress', ( new ErrorPage() )->title( 'Dashboard &lsaquo; Favr Demo &#8212; WordPress' ) );
		Audience::flush();
		$this->seenBy( 'editor' );
		$this->assertSame( 'Dashboard &lsaquo; Favr Demo', ( new ErrorPage() )->title( 'Dashboard &lsaquo; Favr Demo &#8212; WordPress' ) );
	}

	public function test_a_heading_inside_wordpress_message_does_not_compete_with_the_cards(): void {
		Functions\when( 'wp_kses_post' )->alias( static fn( $v ) => strip_tags( (string) $v, '<p><a><strong><em><br><ul><li><code><h1><h2>' ) );
		$html = ErrorPage::page( '<h1>You need a higher level of permission.</h1><p>Sorry, you are not allowed to manage terms in this taxonomy.</p>', $this->page() );
		$this->assertSame( 1, substr_count( $html, '<h1>' ) );
		$this->assertStringContainsString( '<p><strong>You need a higher level of permission.</strong></p><p>Sorry, you are not allowed to manage terms in this taxonomy.</p>', $html );
	}
}
