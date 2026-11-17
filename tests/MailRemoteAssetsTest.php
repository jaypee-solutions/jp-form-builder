<?php
/**
 * Unit tests for the remote-asset stripper shared by Body templates and Mail layouts.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class MailRemoteAssetsTest extends TestCase {

    public function test_strip_removes_sources_posters_remote_css_and_images(): void {
        $html = '<video src="https://remote.test/a.mp4" poster="https://remote.test/p.png" controls></video>'
            . '<audio src="https://remote.test/a.mp3"></audio><p style="background-image:url(https://remote.test/b.png);color:red">Keep</p>'
            . '<div style="background:URL( \'//remote.test/c.png\' )">x</div><style>@import url(https://remote.test/a.css);p{color:blue}</style>'
            . '<img src="data:image/gif;base64,R0lGOD" alt="x"><table background="https://remote.test/t.png"></table>';

        $clean = Mail_Remote_Assets::strip( $html );

        $this->assertStringNotContainsString( 'remote.test', $clean );
        $this->assertStringNotContainsString( 'poster=', $clean );
        $this->assertStringNotContainsString( 'src=', $clean );
        $this->assertStringNotContainsString( '<img', $clean );
        $this->assertStringNotContainsString( '@import', $clean );
        $this->assertStringContainsString( 'color:red', $clean );
        $this->assertStringContainsString( 'color:blue', $clean );
    }

    public function test_strip_keeps_links_to_remote_pages(): void {
        $this->assertSame( '<a href="https://remote.test/page">Link</a>', Mail_Remote_Assets::strip( '<a href="https://remote.test/page">Link</a>' ) );
    }

    public function test_the_tags_that_fetch_content_are_removed_from_an_allow_list(): void {
        $allowed = Mail_Remote_Assets::without_fetching_tags( [ 'p' => [], 'img' => [ 'src' => true ], 'video' => [ 'src' => true, 'poster' => true ], 'audio' => [], 'form' => [], 'a' => [ 'href' => true ] ] );
        $this->assertSame( [ 'p', 'a' ], array_keys( $allowed ) );
    }

    public function test_body_templates_and_layouts_share_the_stripper(): void {
        $template = ( new Mail_Template() )->sanitize( '<p style="background-image:url(https://remote.test/b.png)">Hi</p>' );
        $this->assertStringNotContainsString( 'remote.test', $template );
        $layout = ( new Mail_Layout() )->sanitize( '<p style="background-image:url(https://remote.test/b.png)">{content}</p>' );
        $this->assertStringNotContainsString( 'remote.test', $layout );
    }
}
