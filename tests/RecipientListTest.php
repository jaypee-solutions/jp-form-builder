<?php
/**
 * Unit tests for Recipient lists.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class RecipientListTest extends TestCase {

    public function test_parse_keeps_valid_unique_addresses_in_order(): void {
        $this->assertSame( [ 'a@x.de', 'b@y.de' ], Recipient_List::parse( ' a@x.de,, nope , b@y.de, a@x.de ' ) );
        $this->assertSame( [], Recipient_List::parse( '' ) );
        $this->assertSame( [], Recipient_List::parse( 'a@x.deby.de@' ) );
    }
}
