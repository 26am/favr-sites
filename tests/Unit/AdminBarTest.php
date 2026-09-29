<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\AdminBar;

final class AdminBarTest extends TestCase {

	public function test_help_items_come_from_the_favr_links_and_skip_empty_ones(): void {
		Functions\when( 'esc_html' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->returnArg();
		$items = AdminBar::helpItems(
			array(
				'help_url'      => '',
				'support_email' => 'care@favr.site',
				'support_phone' => '407-889-9987',
				'booking_url'   => 'https://favr.site/book',
			)
		);
		$this->assertSame( array( 'favr-email', 'favr-phone', 'favr-book' ), array_column( $items, 'id' ) );
		$this->assertSame( 'mailto:care@favr.site', $items[0]['href'] );
		$this->assertSame( 'tel:4078899987', $items[1]['href'] );
		$this->assertStringContainsString( '407-889-9987', $items[1]['title'] );
	}

	public function test_everything_under_the_wordpress_logo_goes(): void {
		$parents = array(
			'wp-logo'          => '',
			'wp-logo-default'  => 'wp-logo',
			'about'            => 'wp-logo-default',
			'contribute'       => 'wp-logo-default',
			'wp-logo-external' => 'wp-logo',
			'wporg'            => 'wp-logo-external',
			'feedback'         => 'wp-logo-external',
			'site-name'        => '',
			'view-site'        => 'site-name-default',
		);
		$this->assertSame( array( 'about', 'contribute', 'wp-logo-external', 'wporg', 'feedback' ), AdminBar::logoNodesToRemove( $parents ) );
	}

	public function test_new_menu_keeps_news_page_and_media(): void {
		$parents = array(
			'new-content'           => '',
			'new-content-default'   => 'new-content',
			'new-post'              => 'new-content-default',
			'new-media'             => 'new-content-default',
			'new-page'              => 'new-content-default',
			'new-elementor_library' => 'new-content-default',
			'new-favr_business'     => 'new-content-default',
			'new-user'              => 'new-content-default',
		);
		$this->assertSame( array( 'new-elementor_library', 'new-favr_business', 'new-user' ), AdminBar::newNodesToRemove( $parents ) );
	}

	public function test_name_prefers_first_name(): void {
		$this->assertSame( 'Anna', AdminBar::name( 'Anna', 'Anna Lee' ) );
		$this->assertSame( 'editor mcdondal', AdminBar::name( '', 'editor mcdondal' ) );
	}
}
