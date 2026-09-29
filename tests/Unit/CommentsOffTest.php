<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Comments\Off;

final class CommentsOffTest extends TestCase {

	public function test_comment_routes_are_removed(): void {
		$out = Off::endpoints(
			array(
				'/wp/v2/posts'                  => 1,
				'/wp/v2/comments'               => 2,
				'/wp/v2/comments/(?P<id>[\d]+)' => 3,
			)
		);
		$this->assertSame( array( '/wp/v2/posts' ), array_keys( $out ) );
	}

	public function test_pingbacks_are_removed_from_xmlrpc(): void {
		$out = Off::xmlrpc(
			array(
				'wp.getPosts'                      => 'a',
				'pingback.ping'                    => 'b',
				'pingback.extensions.getPingbacks' => 'c',
			)
		);
		$this->assertSame( array( 'wp.getPosts' ), array_keys( $out ) );
	}

	public function test_comment_column_is_removed(): void {
		$this->assertSame(
			array( 'title' ),
			array_keys(
				Off::columns(
					array(
						'title'    => 'T',
						'comments' => 'C',
					)
				)
			)
		);
	}
}
