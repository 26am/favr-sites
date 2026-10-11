<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\Terms;
use FavrSites\Editors\Words;

final class TermsTest extends TestCase {

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
}
