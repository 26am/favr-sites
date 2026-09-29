<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\Menu;

final class MenuTest extends TestCase {

	public function test_keeps_known_entries_in_favr_order_and_removes_the_rest(): void {
		$slugs = array( 'index.php', 'separator1', 'edit.php', 'upload.php', 'edit.php?post_type=page', 'favr-approvals', 'edit-comments.php', 'edit.php?post_type=elementor_library', 'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event', 'edit.php?post_type=favr_member', 'elementor', 'separator2', 'profile.php', 'tools.php', 'wpseo_workouts' );
		$out   = Menu::arrange( $slugs );
		$this->assertSame( array( 'index.php', 'edit.php?post_type=page', 'edit.php', 'upload.php', 'favr-approvals', 'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event', 'edit.php?post_type=favr_member', 'profile.php' ), $out['keep'] );
		$this->assertSame( array( 'separator1', 'edit-comments.php', 'edit.php?post_type=elementor_library', 'elementor', 'separator2', 'tools.php', 'wpseo_workouts' ), $out['remove'] );
	}

	public function test_missing_plugins_are_skipped(): void {
		$this->assertSame( array( 'index.php', 'edit.php', 'profile.php' ), Menu::arrange( array( 'profile.php', 'edit.php', 'index.php' ) )['keep'] );
	}

	public function test_posts_become_news_in_the_menu(): void {
		$menu    = array(
			5  => array( 'Posts', 'edit_posts', 'edit.php' ),
			10 => array( 'Media', 'upload_files', 'upload.php' ),
		);
		$submenu = array(
			'edit.php' => array(
				5  => array( 'All Posts', 'edit_posts', 'edit.php' ),
				10 => array( 'Add New Post', 'edit_posts', 'post-new.php' ),
				15 => array( 'Categories', 'manage_categories', 'edit-tags.php?taxonomy=category' ),
			),
		);
		list( $menu, $submenu ) = Menu::relabel( $menu, $submenu );
		$this->assertSame( 'News', $menu[5][0] );
		$this->assertSame( 'Media', $menu[10][0] );
		$this->assertSame( 'All news', $submenu['edit.php'][5][0] );
		$this->assertSame( 'Add news post', $submenu['edit.php'][10][0] );
		$this->assertSame( 'Categories', $submenu['edit.php'][15][0] );
	}
}
