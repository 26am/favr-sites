<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Site\Foundations;

final class FoundationsTest extends TestCase {

	private static function goaaba(): array {
		return array(
			'show_on_front'  => 'page',
			'page_on_front'  => 111,
			'page_for_posts' => 182,
			'pages'          => array(
				111 => array( 'status' => 'publish', 'title' => 'Home', 'slug' => 'home' ),
				182 => array( 'status' => 'publish', 'title' => 'News', 'slug' => 'news' ),
			),
			'pro'            => true,
			'recorded'       => array(),
			'templates'      => array(
				53  => array( 'type' => 'header', 'status' => 'publish', 'conditions' => array( 'include/general' ), 'date' => '2026-09-24 03:15:50' ),
				55  => array( 'type' => 'footer', 'status' => 'publish', 'conditions' => array( 'include/general' ), 'date' => '2026-09-24 18:48:32' ),
				400 => array( 'type' => 'header', 'status' => 'publish', 'conditions' => array( 'include/singular/page' ), 'date' => '2026-09-25 10:00:00' ),
			),
		);
	}

	public function test_a_settled_site_is_adopted_as_is(): void {
		$state = self::goaaba();
		$plan  = Foundations::plan( $state );
		$this->assertSame( array( 'id' => 111, 'publish' => false ), $plan['home'] );
		$this->assertSame( array( 'id' => 182, 'publish' => false ), $plan['news'] );
		$this->assertSame( array( 'id' => 53, 'restore' => false, 'conditions' => false ), $plan['header'] );
		$this->assertSame( array( 'id' => 55, 'restore' => false, 'conditions' => false ), $plan['footer'] );
		$this->assertFalse( Foundations::settled( $plan, $state ) ); // Not recorded yet.
		$state['recorded'] = array( 'header' => 53, 'footer' => 55 );
		$this->assertTrue( Foundations::settled( Foundations::plan( $state ), $state ) );
	}

	public function test_a_fresh_site_gets_everything_created(): void {
		$plan = Foundations::plan( array( 'show_on_front' => 'posts', 'page_on_front' => 0, 'page_for_posts' => 0, 'pages' => array(), 'pro' => true, 'recorded' => array(), 'templates' => array() ) );
		$this->assertSame( 0, $plan['home']['id'] );
		$this->assertSame( 0, $plan['news']['id'] );
		$this->assertSame( 0, $plan['header']['id'] );
		$this->assertSame( 0, $plan['footer']['id'] );
	}

	public function test_pages_are_adopted_by_name_and_trashed_or_shared_ones_are_replaced(): void {
		$plan = Foundations::plan(
			array(
				'show_on_front'  => 'page',
				'page_on_front'  => 5,
				'page_for_posts' => 5,
				'pages'          => array(
					5  => array( 'status' => 'draft', 'title' => 'Welcome', 'slug' => 'welcome' ),
					8  => array( 'status' => 'publish', 'title' => 'news', 'slug' => 'latest' ),
					9  => array( 'status' => 'trash', 'title' => 'News', 'slug' => 'news' ),
				),
				'pro'            => false,
				'recorded'       => array(),
				'templates'      => array(),
			)
		);
		$this->assertSame( array( 'id' => 5, 'publish' => true ), $plan['home'] );  // Adopted, republished.
		$this->assertSame( array( 'id' => 8, 'publish' => false ), $plan['news'] ); // Not the Home page; by title.
		$this->assertNull( $plan['header'] );                                         // No Pro.
		$trashed = Foundations::plan( array( 'show_on_front' => 'page', 'page_on_front' => 9, 'page_for_posts' => 0, 'pages' => array( 9 => array( 'status' => 'trash', 'title' => 'Home', 'slug' => 'home' ) ), 'pro' => false, 'recorded' => array(), 'templates' => array() ) );
		$this->assertSame( 0, $trashed['home']['id'] );
	}

	public function test_recorded_templates_are_repaired_and_the_newest_entire_site_one_is_adopted(): void {
		$state              = self::goaaba();
		$state['recorded']  = array( 'header' => 53, 'footer' => 99 ); // 99 was deleted.
		$state['templates'][53]['status']     = 'trash';
		$state['templates'][53]['conditions'] = array( 'include/singular' );
		$state['templates'][60]               = array( 'type' => 'footer', 'status' => 'publish', 'conditions' => array( 'include/general', 'exclude/singular/page/7' ), 'date' => '2026-09-26 09:00:00' );
		$plan               = Foundations::plan( $state );
		$this->assertSame( array( 'id' => 53, 'restore' => true, 'conditions' => true ), $plan['header'] );
		$this->assertSame( array( 'id' => 60, 'restore' => false, 'conditions' => true ), $plan['footer'] ); // Newest Entire Site footer.
	}
}
