<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\ListTables;

final class ListTablesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'esc_attr' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
	}

	public function test_only_view_and_trash_survive_and_trash_becomes_an_icon(): void {
		$actions = array(
			'edit'                 => '<a href="post.php?post=1&amp;action=edit">Edit</a>',
			'inline hide-if-no-js' => '<button>Quick&nbsp;Edit</button>',
			'trash'                => '<a href="post.php?post=1&amp;action=trash&amp;_wpnonce=abc" class="submitdelete" aria-label="Move “About” to the Trash">Trash</a>',
			'view'                 => '<a href="/about/">View</a>',
			'edit_with_elementor'  => '<a href="post.php?post=1&amp;action=elementor">Edit with Elementor</a>',
			'duplicate'            => '<a href="#">Duplicate</a>',
		);
		$out     = ListTables::rowActions( $actions );
		$this->assertSame( array( 'view', 'trash' ), array_keys( $out ) );
		$this->assertStringContainsString( 'href="post.php?post=1&amp;action=trash&amp;_wpnonce=abc"', $out['trash'] );
		$this->assertStringContainsString( 'class="favr-trash submitdelete"', $out['trash'] );
		$this->assertStringContainsString( '<svg', $out['trash'] );
		$this->assertStringNotContainsString( '>Trash<', $out['trash'] );
	}

	public function test_trash_view_keeps_restore_and_delete(): void {
		$actions = array(
			'untrash' => '<a href="#r">Restore</a>',
			'delete'  => '<a href="#d" class="submitdelete">Delete Permanently</a>',
		);
		$this->assertSame( $actions, ListTables::rowActions( $actions ) );
	}

	public function test_columns_drop_comments_and_yoast(): void {
		$cols = array(
			'cb'          => '',
			'title'       => 'Title',
			'author'      => 'Author',
			'comments'    => 'C',
			'wpseo-score' => 'SEO',
			'wpseo-links' => 'L',
			'date'        => 'Date',
		);
		$this->assertSame( array( 'cb', 'title', 'author', 'date' ), array_keys( ListTables::columns( $cols ) ) );
	}
}
