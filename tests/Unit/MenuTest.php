<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\Menu;

final class MenuTest extends TestCase {

	private const WEBSITE      = 'separator-favr-website';
	private const ORGANIZATION = 'separator-favr-organization';
	private const ACCOUNT      = 'separator-favr-account';

	public function test_keeps_known_entries_in_favr_order_under_their_groups_and_removes_the_rest(): void {
		$slugs = array( 'index.php', 'separator1', 'favr-menus', 'favr-banner', 'edit.php', 'upload.php', 'edit.php?post_type=page', 'favr-approvals', 'edit-comments.php', 'edit.php?post_type=elementor_library', 'edit.php?post_type=favr_sponsor', 'edit.php?post_type=favr_business', 'edit.php?post_type=favr_event', 'edit.php?post_type=favr_person', 'edit.php?post_type=favr_member', 'edit.php?post_type=favr_document', 'elementor', 'separator2', 'profile.php', 'tools.php', 'wpseo_workouts' );
		$out   = Menu::arrange( $slugs );
		$this->assertSame(
			array(
				'index.php',
				'favr-approvals',
				self::WEBSITE,
				'edit.php?post_type=page',
				'edit.php',
				'favr-menus',
				'favr-banner',
				'upload.php',
				self::ORGANIZATION,
				'edit.php?post_type=favr_member',
				'edit.php?post_type=favr_business',
				'edit.php?post_type=favr_event',
				'edit.php?post_type=favr_document',
				'edit.php?post_type=favr_person',
				'edit.php?post_type=favr_sponsor',
				self::ACCOUNT,
				'profile.php',
			),
			$out['keep']
		);
		$this->assertSame( array( 'separator1', 'edit-comments.php', 'edit.php?post_type=elementor_library', 'elementor', 'separator2', 'tools.php', 'wpseo_workouts' ), $out['remove'] );
	}

	public function test_missing_plugins_are_skipped(): void {
		$this->assertSame(
			array( 'index.php', self::WEBSITE, 'edit.php', self::ACCOUNT, 'profile.php' ),
			Menu::arrange( array( 'profile.php', 'edit.php', 'index.php' ) )['keep']
		);
	}

	public function test_a_site_without_the_organization_plugins_shows_no_organization_heading(): void {
		$keep = Menu::arrange( array( 'index.php', 'edit.php?post_type=page', 'edit.php', 'favr-menus', 'favr-banner', 'upload.php', 'profile.php' ) )['keep'];
		$this->assertNotContains( self::ORGANIZATION, $keep );
		$this->assertSame( array( 'index.php', self::WEBSITE, 'edit.php?post_type=page', 'edit.php', 'favr-menus', 'favr-banner', 'upload.php', self::ACCOUNT, 'profile.php' ), $keep );
	}

	public function test_a_heading_needs_an_entry_even_when_its_separator_is_already_in_the_menu(): void {
		// The order filter sees the separators Favr added on an earlier pass; an emptied group loses its heading.
		$out = Menu::arrange( array( 'index.php', self::WEBSITE, self::ORGANIZATION, 'edit.php?post_type=favr_event', self::ACCOUNT, 'profile.php' ) );
		$this->assertSame( array( 'index.php', self::ORGANIZATION, 'edit.php?post_type=favr_event', self::ACCOUNT, 'profile.php' ), $out['keep'] );
		$this->assertSame( array(), $out['remove'] );
	}

	public function test_the_ungrouped_top_of_the_menu_has_no_heading(): void {
		$this->assertSame( array( 'index.php', 'favr-approvals' ), Menu::arrange( array( 'favr-approvals', 'index.php' ) )['keep'] );
	}

	public function test_arranging_an_arranged_menu_changes_nothing(): void {
		$keep = Menu::arrange( array( 'profile.php', 'edit.php?post_type=favr_member', 'upload.php', 'index.php' ) )['keep'];
		$this->assertSame( $keep, Menu::arrange( $keep )['keep'] );
	}

	public function test_every_known_entry_is_in_exactly_one_place(): void {
		$all = array_merge( Menu::TOP, ...array_values( Menu::GROUPS ) );
		$this->assertSame( $all, array_values( array_unique( $all ) ) );
		$this->assertSame( array( 'website', 'organization', 'account' ), array_keys( Menu::GROUPS ) );
	}

	public function test_a_group_separator_is_a_wordpress_separator_with_the_group_classes(): void {
		$this->assertSame(
			array( '', 'read', self::WEBSITE, '', 'wp-menu-separator favr-menu-group favr-menu-group--website' ),
			Menu::separator( 'website' )
		);
		$this->assertSame( 'wp-menu-separator favr-menu-group favr-menu-group--organization', Menu::separator( 'organization' )[4] );
		$this->assertSame( 'wp-menu-separator favr-menu-group favr-menu-group--account', Menu::separator( 'account' )[4] );
	}

	public function test_the_menu_gets_the_separators_the_order_asks_for_and_loses_stale_ones(): void {
		$menu = array(
			2  => array( 'Dashboard', 'read', 'index.php', '', 'menu-top' ),
			5  => array( 'News', 'edit_posts', 'edit.php', '', 'menu-top' ),
			70 => array( 'Profile', 'read', 'profile.php', '', 'menu-top' ),
			71 => Menu::separator( 'organization' ),
		);
		$out  = Menu::withSeparators( $menu, array( 'index.php', self::WEBSITE, 'edit.php', self::ACCOUNT, 'profile.php' ) );
		$this->assertSame(
			array( 'index.php', 'edit.php', 'profile.php', self::WEBSITE, self::ACCOUNT ),
			array_column( $out, 2 )
		);
		// Running it again (another plugin asking for the order) adds nothing.
		$this->assertSame( $out, Menu::withSeparators( $out, array( 'index.php', self::WEBSITE, 'edit.php', self::ACCOUNT, 'profile.php' ) ) );
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

	public function test_pages_and_media_read_in_sentence_case(): void {
		$menu    = array(
			10 => array( 'Media', 'upload_files', 'upload.php' ),
			20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page' ),
		);
		$submenu = array(
			'edit.php?post_type=page' => array(
				5  => array( 'All Pages', 'edit_pages', 'edit.php?post_type=page' ),
				10 => array( 'Add Page', 'edit_pages', 'post-new.php?post_type=page' ),
			),
			'upload.php'              => array(
				5  => array( 'Library', 'upload_files', 'upload.php' ),
				10 => array( 'Add Media File', 'upload_files', 'media-new.php' ),
			),
		);
		list( $menu, $submenu ) = Menu::relabel( $menu, $submenu );
		$this->assertSame( 'Media', $menu[10][0] );
		$this->assertSame( 'Pages', $menu[20][0] );
		$this->assertSame( 'All pages', $submenu['edit.php?post_type=page'][5][0] );
		$this->assertSame( 'Add page', $submenu['edit.php?post_type=page'][10][0] );
		$this->assertSame( 'Library', $submenu['upload.php'][5][0] );
		$this->assertSame( 'Add media', $submenu['upload.php'][10][0] );
	}

	public function test_relabelling_leaves_other_submenus_alone(): void {
		$submenu = array(
			'edit.php?post_type=favr_event' => array(
				5 => array( 'All Events', 'edit_posts', 'edit.php?post_type=favr_event' ),
			),
		);
		$this->assertSame( array( array(), $submenu ), Menu::relabel( array(), $submenu ) );
	}

	public function test_page_labels_read_like_the_news_ones(): void {
		$labels = Menu::pageLabels();
		$this->assertSame( 'All pages', $labels['all_items'] );
		$this->assertSame( 'Add page', $labels['add_new'] );
		$this->assertSame( 'Add page', $labels['add_new_item'] );
		$this->assertSame( 'Edit page', $labels['edit_item'] );
		$this->assertSame( 'View page', $labels['view_item'] );
		$this->assertSame( 'Search pages', $labels['search_items'] );
		foreach ( $labels as $label ) {
			$this->assertDoesNotMatchRegularExpression( '/\s[A-Z]/', $label, 'Sentence case: only the first word is capitalized.' );
		}
	}

	public function test_news_and_media_labels_are_sentence_case_too(): void {
		foreach ( array( Menu::newsLabels(), Menu::mediaLabels() ) as $labels ) {
			foreach ( $labels as $key => $label ) {
				$this->assertDoesNotMatchRegularExpression( '/\s[A-Z]/', str_replace( 'Trash', 'trash', $label ), $key );
			}
		}
		$this->assertSame( 'All news', Menu::newsLabels()['all_items'] );
		$this->assertSame( 'Add news post', Menu::newsLabels()['add_new_item'] );
		$this->assertSame( 'Add media', Menu::mediaLabels()['add_new_item'] );
	}

	public function test_news_categories_and_tags_are_sentence_case_too(): void {
		foreach ( array( Menu::categoryLabels(), Menu::tagLabels() ) as $labels ) {
			foreach ( $labels as $key => $label ) {
				// "← Go to categories" starts after its arrow.
				$this->assertDoesNotMatchRegularExpression( '/\s[A-Z]/', (string) preg_replace( '/^← /u', '', $label ), $key );
			}
		}
		$this->assertSame( 'All categories', Menu::categoryLabels()['all_items'] );
		$this->assertSame( 'Add category', Menu::categoryLabels()['add_new_item'] );
		$this->assertSame( 'Edit category', Menu::categoryLabels()['edit_item'] );
		$this->assertSame( 'All tags', Menu::tagLabels()['all_items'] );
		$this->assertSame( 'Add tag', Menu::tagLabels()['add_new_item'] );
	}

	public function test_every_relabelled_type_and_taxonomy_has_its_words(): void {
		$this->assertSame(
			array( 'post_type_labels_post', 'post_type_labels_page', 'post_type_labels_attachment', 'taxonomy_labels_category', 'taxonomy_labels_post_tag' ),
			array_keys( Menu::LABELS )
		);
		foreach ( Menu::LABELS as $method ) {
			$this->assertNotEmpty( Menu::$method() );
		}
	}

	public function test_the_media_screens_words_come_from_the_dictionary(): void {
		// WordPress has no label for the Media screens' title, button and search; Editors\Words has them.
		$this->assertSame( Menu::mediaLabels()['add_new_item'], \FavrSites\Editors\Words::MAP['Add Media File'] );
		$this->assertSame( Menu::mediaLabels()['add_new_item'], \FavrSites\Editors\Words::MAP['Upload Media'] );
		$this->assertSame( Menu::mediaLabels()['all_items'], \FavrSites\Editors\Words::MAP['Media Library'] );
	}

	public function test_the_group_headings_are_printed_as_css_content(): void {
		$css = Menu::headingCss(
			array(
				'website'      => 'Website',
				'organization' => 'Organization',
				'account'      => '',
			)
		);
		$this->assertStringContainsString( '#adminmenu li.favr-menu-group--website::before{content:"Website"}', $css );
		$this->assertStringContainsString( '#adminmenu li.favr-menu-group--organization::before{content:"Organization"}', $css );
		$this->assertStringContainsString( '#adminmenu li.favr-menu-group--account::before{content:""}', $css );
	}

	public function test_the_headings_this_site_prints_cover_every_group(): void {
		$this->assertSame( array_keys( Menu::GROUPS ), array_keys( Menu::headings() ) );
		$this->assertSame( 'Website', Menu::headings()['website'] );
		$this->assertSame( 'Organization', Menu::headings()['organization'] );
		$this->assertSame( '', Menu::headings()['account'] );
	}

	public function test_a_translated_heading_cannot_break_out_of_the_css_string_or_the_style_tag(): void {
		$this->assertSame( '"Sitio web"', Menu::cssString( 'Sitio web' ) );
		$this->assertSame( '"Organización"', Menu::cssString( 'Organización' ) );
		$this->assertSame( '"a\22 b"', Menu::cssString( 'a"b' ) );
		$this->assertSame( '"a\5c b"', Menu::cssString( 'a\\b' ) );
		$this->assertSame( '"a\a b"', Menu::cssString( "a\nb" ) );
		$this->assertSame( '"\3c \2f style\3e \3c script\3e "', Menu::cssString( '</style><script>' ) );
		$this->assertSame( '"x\7d body\7b display\3a none\7d "', Menu::cssString( 'x}body{display:none}' ) );
		$this->assertSame( '""', Menu::cssString( "\xB1\x31" ), 'Invalid UTF-8 prints nothing.' );

		$css = Menu::headingCss( array( 'website' => '"}</style><script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<', $css );
		$this->assertSame( 2, substr_count( $css, '"' ) );
		$this->assertSame( 1, substr_count( $css, '}' ) );
	}

	public function test_a_heading_for_an_unknown_group_is_not_printed(): void {
		$this->assertSame( '', Menu::headingCss( array( 'x}body{display:none' => 'Nope' ) ) );
	}
}
