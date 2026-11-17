<?php
/**
 * Unit tests for Field key normalisation. tests/js/field-key.test.mjs checks the same fixtures.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class FieldKeyTest extends TestCase {

    /** @return array<string,array{string,string}> */
    public function fixtures(): array {
        $cases = [];
        foreach ( (array) json_decode( (string) file_get_contents( __DIR__ . '/fixtures/field-keys.json' ), true ) as $case ) {
            $cases[ $case[0] ] = $case;
        }
        return $cases;
    }

    /** @dataProvider fixtures */
    public function test_normalize_follows_the_shared_rule( string $input, string $expected ): void {
        $this->assertSame( $expected, Field_Key::normalize( $input ) );
    }

    public function test_the_parser_keys_fields_by_the_same_rule(): void {
        $parser = new Form_Definition_Parser( new Block_Defaults( dirname( __DIR__ ) . '/src/blocks' ) );
        $form   = $parser->parse(
            [
                [
                    'blockName'   => 'jp-form-builder/form',
                    'attrs'       => [],
                    'innerBlocks' => [
                        [ 'blockName' => 'jp-form-builder/text', 'attrs' => [ 'fieldName' => 'Größe' ] ],
                        [ 'blockName' => 'jp-form-builder/text', 'attrs' => [ 'fieldName' => 'groesse' ] ],
                    ],
                ],
            ]
        );
        $this->assertNotNull( $form );
        $this->assertSame( [ 'groesse', 'groesse_2' ], array_map( static fn( Field_Definition $field ): string => $field->key, $form->fields() ) );
    }
}
