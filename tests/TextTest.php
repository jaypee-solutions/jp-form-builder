<?php
/**
 * Unit tests for Text.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase {

    public function test_single_line_strips_control_characters_and_trims(): void {
        $this->assertSame( 'Jane Doe', Text::single_line( "  Jane\x00 Doe\r\n\t" ) );
        $this->assertSame( 'Zoë', Text::single_line( "\u{00A0}Zoë\u{00A0}" ) );
        $this->assertSame( '', Text::single_line( "\xB1\x31" ) );
    }

    public function test_multi_line_keeps_newlines_and_normalises_line_endings(): void {
        $this->assertSame( "one\ntwo\n\nthree", Text::multi_line( "\r\none\r\ntwo\r\rthree\x07\t \n" ) );
        $this->assertSame( '', Text::multi_line( "ok\xFF" ) );
    }

    public function test_length_counts_characters(): void {
        $this->assertSame( 3, Text::length( 'Zoë' ) );
        $this->assertTrue( Text::is_valid_utf8( 'Zoë' ) );
        $this->assertFalse( Text::is_valid_utf8( "\xFF" ) );
    }
}
