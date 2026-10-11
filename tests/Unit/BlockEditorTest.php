<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\BlockEditor;

final class BlockEditorTest extends TestCase {

	public function test_editors_are_not_offered_a_template_for_a_news_post(): void {
		$settings = array(
			'availableTemplates' => array(
				''                 => 'Default template',
				'elementor_canvas' => 'Elementor Canvas',
			),
			'kept'               => 'yes',
		);
		$out      = BlockEditor::settingsFor( $settings, true );
		$this->assertSame( array(), $out['availableTemplates'] );
		$this->assertSame( 'yes', $out['kept'] );
		$this->assertSame( $settings, BlockEditor::settingsFor( $settings, false ) );
	}

	public function test_the_writing_area_gets_the_favr_accent_after_the_styles_already_there(): void {
		$out = BlockEditor::settingsFor( array( 'styles' => array( array( 'css' => 'p{}' ) ) ), true );
		$this->assertCount( 2, $out['styles'] );
		$this->assertSame( 'p{}', $out['styles'][0]['css'] );
		$this->assertSame( BlockEditor::canvasCss(), $out['styles'][1]['css'] );
		$this->assertStringContainsString( '--wp-admin-theme-color:' . BlockEditor::ACCENT, BlockEditor::canvasCss() );
		$this->assertStringContainsString( '--wp-admin-theme-color--rgb:169,71,42', BlockEditor::canvasCss() );
		$this->assertArrayNotHasKey( 'styles', BlockEditor::settingsFor( array(), false ) );
	}

	public function test_the_back_arrow_says_where_it_goes(): void {
		$labels = (object) array(
			'view_items' => 'View news',
			'all_items'  => 'All news',
		);
		$out    = BlockEditor::labelsFor( $labels );
		$this->assertSame( 'All news', $out->view_items );
		$this->assertSame( 'View news', $labels->view_items, 'The registered labels are left alone.' );
	}

	public function test_a_type_without_an_all_label_keeps_its_own_words(): void {
		$labels = (object) array( 'view_items' => 'View things' );
		$this->assertSame( 'View things', BlockEditor::labelsFor( $labels )->view_items );
	}

	public function test_the_editor_starts_without_the_tour_and_publishes_in_one_press(): void {
		$this->assertSame(
			array(
				'core/edit-post' => array( 'welcomeGuide' => false ),
				'core'           => array( 'isPublishSidebarEnabled' => false ),
			),
			BlockEditor::DEFAULTS
		);
	}

	public function test_the_editors_notices_say_news_post_not_post(): void {
		$labels = (object) array(
			'all_items'      => 'All news',
			'item_published' => 'Post published.',
			'item_updated'   => 'Post updated.',
		);
		$out    = BlockEditor::labelsFor( $labels, BlockEditor::newsNotices() );
		$this->assertSame( 'News post published.', $out->item_published );
		$this->assertSame( 'News post updated.', $out->item_updated );
		$this->assertSame( 'News post moved to the trash.', $out->item_trashed );
		$this->assertSame( 'Post published.', $labels->item_published, 'The registered labels are left alone.' );
		$this->assertSame(
			array( 'item_published', 'item_published_privately', 'item_reverted_to_draft', 'item_scheduled', 'item_updated', 'item_trashed' ),
			array_keys( BlockEditor::newsNotices() )
		);
	}

	public function test_an_administrators_news_editor_gets_none_of_it(): void {
		\Brain\Monkey\Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 1, array( 'administrator' ) ) );
		\Brain\Monkey\Functions\when( 'user_can' )->justReturn( true );
		\FavrSites\Dashboard\Audience::flush();
		\Brain\Monkey\Functions\expect( 'wp_enqueue_style' )->never();
		\Brain\Monkey\Functions\expect( 'wp_enqueue_script' )->never();
		$settings = array( 'availableTemplates' => array( '' => 'Default template' ) );
		$editor   = new BlockEditor();

		$editor->enqueue();
		$out = $editor->settings( $settings );

		\FavrSites\Dashboard\Audience::flush();
		$this->assertSame( $settings, $out );
	}
}
