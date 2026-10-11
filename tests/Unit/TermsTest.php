<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\Terms;
use FavrSites\Editors\Words;

final class TermsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Audience::flush();
	}

	protected function tearDown(): void {
		Audience::flush();
		parent::tearDown();
	}

	private function seenBy( string $role ): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 'editor' === $role ? 456 : 1, array( $role ) ) );
		Functions\when( 'user_can' )->justReturn( 'editor' !== $role );
	}

	public function test_the_table_has_no_slug_column(): void {
		$columns = array(
			'cb'          => '',
			'name'        => 'Name',
			'description' => 'Description',
			'slug'        => 'Slug',
			'posts'       => 'Count',
		);
		$this->assertSame( array( 'cb', 'name', 'description', 'posts' ), array_keys( Terms::columns( $columns, true ) ) );
	}

	public function test_a_plugins_own_columns_stay(): void {
		$columns = array(
			'cb'            => '',
			'name'          => 'Name',
			'favr_order'    => 'Order',
			'slug'          => 'Slug',
			'favr_pay_dues' => 'Dues',
			'posts'         => 'Members',
		);
		$this->assertSame( array( 'cb', 'name', 'favr_order', 'favr_pay_dues', 'posts' ), array_keys( Terms::columns( $columns, true ) ) );
	}

	/**
	 * Membership levels are shared by members and directory listings, so WordPress's Count adds
	 * the two (Professional 44 on a site with 22 such members) and matches neither list.
	 */
	public function test_a_taxonomy_shared_by_two_record_types_has_no_count_column(): void {
		$columns = array(
			'cb'         => '',
			'name'       => 'Name',
			'favr_order' => 'Order',
			'slug'       => 'Slug',
			'posts'      => 'Count',
		);
		$this->assertSame( array( 'cb', 'name', 'favr_order' ), array_keys( Terms::columns( $columns, true, true ) ) );
		$this->assertSame( $columns, Terms::columns( $columns, false, true ), 'An Administrator keeps it.' );
		$this->assertTrue( Terms::isShared( 'favr_member_level' ) );
		$this->assertFalse( Terms::isShared( 'category' ) );
	}

	public function test_everybody_else_keeps_the_slug_column(): void {
		$columns = array(
			'name' => 'Name',
			'slug' => 'Slug',
		);
		$this->assertSame( $columns, Terms::columns( $columns, false ) );
		$this->assertSame( 'broken', Terms::columns( 'broken', false ) );
	}

	public function test_a_broken_column_list_becomes_an_empty_one_for_editors(): void {
		$this->assertSame( array(), Terms::columns( 'broken', true ) );
	}

	public function test_wordpress_helper_paragraphs_go_or_become_one_plain_line(): void {
		$help = Terms::help();
		$this->assertSame(
			array( 'name_field_description', 'slug_field_description', 'parent_field_description', 'desc_field_description' ),
			array_keys( $help )
		);
		$this->assertSame( '', $help['name_field_description'] );
		$this->assertSame( '', $help['slug_field_description'] );
		foreach ( $help as $key => $line ) {
			$this->assertLessThanOrEqual( 60, strlen( $line ), $key );
			$this->assertStringNotContainsStringIgnoringCase( 'slug', $line, $key );
			$this->assertStringNotContainsString( 'Jazz', $line, $key );
			$this->assertStringNotContainsString( 'theme', $line, $key );
		}
	}

	public function test_a_news_category_gets_the_same_parent_help_as_every_other_taxonomy(): void {
		// WordPress prints its own sentence under a category's parent instead of the taxonomy's label.
		$this->assertSame( Terms::help()['parent_field_description'], Words::MAP[ Words::CATEGORY_PARENT_HELP ] );
	}

	public function test_the_labels_of_a_taxonomy_get_the_help_for_editors_only(): void {
		$labels = (object) array(
			'name'                     => 'Levels',
			'name_field_description'   => 'The name is how it appears on your site.',
			'slug_field_description'   => 'The “slug” is the URL-friendly version of the name.',
			'parent_field_description' => 'Assign a parent term to create a hierarchy.',
			'desc_field_description'   => 'The description is not prominent by default; however, some themes may show it.',
		);
		$stock  = clone $labels;
		$this->assertEquals( $stock, Terms::labels( clone $labels, false ) );

		$mine = Terms::labels( clone $labels, true );
		$this->assertSame( 'Levels', $mine->name );
		$this->assertSame( '', $mine->name_field_description );
		$this->assertSame( '', $mine->slug_field_description );
		$this->assertSame( Terms::help()['parent_field_description'], $mine->parent_field_description );
		$this->assertSame( Terms::help()['desc_field_description'], $mine->desc_field_description );
	}

	public function test_something_that_is_not_a_set_of_labels_is_left_alone(): void {
		$this->assertSame( 'broken', Terms::labels( 'broken', true ) );
		$this->assertNull( Terms::labels( null, true ) );
	}

	public function test_the_slug_field_is_hidden_in_the_add_form_quick_edit_and_the_edit_screen(): void {
		$css = Terms::css();
		$this->assertStringContainsString( '.term-slug-wrap', $css, 'The add form (a div) and the edit screen (a table row).' );
		$this->assertStringContainsString( '.inline-edit-row label:has(input[name="slug"])', $css, 'Quick Edit.' );
		$this->assertStringContainsString( 'display:none', $css );
		$this->assertStringNotContainsString( '<', $css );
	}

	public function test_an_emptied_helper_paragraph_takes_no_room(): void {
		$this->assertStringContainsString( ':empty', Terms::css() );
	}

	public function test_the_slug_field_is_only_hidden_on_category_and_level_screens(): void {
		$this->assertTrue( Terms::isTermScreen( 'edit-tags' ) );
		$this->assertTrue( Terms::isTermScreen( 'term' ) );
		$this->assertFalse( Terms::isTermScreen( 'edit' ) );
		$this->assertFalse( Terms::isTermScreen( 'post' ) );
		$this->assertFalse( Terms::isTermScreen( '' ) );
	}

	public function test_the_confirmations_use_the_word_for_the_thing(): void {
		$this->assertSame(
			array(
				0 => '',
				1 => 'Category added.',
				2 => 'Category deleted.',
				3 => 'Category updated.',
				4 => 'Category not added.',
				5 => 'Category not updated.',
				6 => 'Categories deleted.',
			),
			Terms::messages( 'Category', 'Categories' )
		);
		$level = Terms::messages( 'Membership Level', 'Membership Levels' );
		$this->assertSame( 'Membership level added.', $level[1] );
		$this->assertSame( 'Membership level updated.', $level[3] );
		$this->assertSame( 'Membership levels deleted.', $level[6] );
		$this->assertSame( 'Group deleted.', Terms::messages( 'Group', 'Groups' )[2] );
	}

	public function test_no_confirmation_says_item_or_term(): void {
		foreach ( Terms::messages( 'Level', 'Levels' ) as $message ) {
			$this->assertStringNotContainsStringIgnoringCase( 'item', $message );
			$this->assertStringNotContainsStringIgnoringCase( 'term', $message );
		}
	}

	public function test_a_name_used_twice_is_refused_in_the_things_own_word(): void {
		$this->assertSame( 'Another category already has that name.', Terms::duplicate( 'Category' ) );
		$this->assertSame( 'Another membership level already has that name.', Terms::duplicate( 'Membership Level' ) );
		$this->assertSame( 'Another CPA firm type already has that name.', Terms::duplicate( 'CPA Firm Type' ) );
		$this->assertStringNotContainsString( 'term', Terms::duplicate( 'Group' ) );
	}

	public function test_only_wordpress_two_sentences_about_a_taken_name_are_reworded(): void {
		$this->assertSame( 'Another level already has that name.', Terms::taken( 'x', 'A term with the name provided already exists with this parent.', 'default', 'Level' ) );
		$this->assertSame( 'Another level already has that name.', Terms::taken( 'x', 'A term with the name provided already exists in this taxonomy.', 'default', 'Level' ) );
		$this->assertSame( 'x', Terms::taken( 'x', 'A term with the name provided already exists in this taxonomy.', 'some-plugin', 'Level' ) );
		$this->assertSame( 'Publish', Terms::taken( 'Publish', 'Publish', 'default', 'Level' ) );
	}

	public function test_a_level_has_no_parent_field_and_a_category_keeps_it(): void {
		$this->assertContains( 'favr_member_level', Terms::FLAT );
		$this->assertStringContainsString( '.term-parent-wrap', Terms::css( true ) );
		$this->assertStringNotContainsString( '.term-parent-wrap', Terms::css( false ) );
		$this->assertStringNotContainsString( '.term-parent-wrap', Terms::css() );
	}

	public function test_the_second_way_back_under_a_confirmation_is_hidden(): void {
		$this->assertStringContainsString( '#message p:has(>a[href*="edit-tags.php"])', Terms::css() );
	}

	public function test_somebody_who_is_not_an_editor_keeps_wordpress_term_screens(): void {
		$this->seenBy( 'administrator' );
		Functions\expect( 'wp_add_inline_style' )->never();
		Functions\when( 'get_current_screen' )->justReturn( null );
		Filters\expectAdded( 'gettext' )->never();
		$terms    = new Terms();
		$messages = array( '_item' => array( 1 => 'Item added.' ) );
		$this->assertSame( $messages, $terms->termMessages( $messages ) );
		$this->assertSame( 'Mixers', $terms->naming( 'Mixers', 'favr_event_cat' ) );
		$terms->styles();
	}

	public function test_an_editor_gets_the_words_of_every_taxonomy_on_show(): void {
		$this->seenBy( 'editor' );
		Functions\when( 'get_taxonomies' )->justReturn(
			array(
				'favr_event_cat'    => (object) array(
					'labels' => (object) array(
						'singular_name' => 'Category',
						'name'          => 'Event categories',
					),
				),
				'favr_member_level' => (object) array(
					'labels' => (object) array(
						'singular_name' => 'Membership level',
						'name'          => 'Membership levels',
					),
				),
			)
		);
		$messages = ( new Terms() )->termMessages( array( '_item' => array( 1 => 'Item added.' ) ) );
		$this->assertSame( 'Category added.', $messages['favr_event_cat'][1] );
		$this->assertSame( 'Membership level updated.', $messages['favr_member_level'][3] );
		$this->assertSame( 'Event categories deleted.', $messages['favr_event_cat'][6] );
		$this->assertSame( 'Item added.', $messages['_item'][1] );
	}
}
