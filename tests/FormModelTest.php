<?php
/** Tests parsed Form definitions. */
use JayPee\FormBuilder\Block_Defaults;
use JayPee\FormBuilder\Condition;
use JayPee\FormBuilder\Conditional_Group;
use JayPee\FormBuilder\Field_Definition;
use JayPee\FormBuilder\Form_Definition;
use JayPee\FormBuilder\Form_Definition_Parser;
use PHPUnit\Framework\TestCase;
final class FormModelTest extends TestCase {
    public function test_defaults_and_field_keys_are_merged_in_document_order(): void {
        $parser = new Form_Definition_Parser( new Block_Defaults( dirname( __DIR__ ) . '/src/blocks' ) );
        $blocks = [
            [
                'blockName' => 'jp-form-builder/form',
                'attrs' => [],
                'innerBlocks' => [
                    [ 'blockName' => 'core/paragraph', 'attrs' => [ 'content' => 'Intro' ] ],
                    [ 'blockName' => 'jp-form-builder/name', 'attrs' => [ 'fieldName' => 'person', 'split' => true ] ],
                    [ 'blockName' => 'jp-form-builder/email', 'attrs' => [] ],
                    [ 'blockName' => 'jp-form-builder/email', 'attrs' => [] ],
                    [ 'blockName' => 'jp-form-builder/embed', 'attrs' => [ 'formId' => 99 ] ],
                    [ 'blockName' => 'jp-form-builder/unknown', 'attrs' => [] ],
                    [ 'blockName' => 'jp-form-builder/consent', 'attrs' => [ 'required' => false ] ],
                    [ 'blockName' => 'jp-form-builder/text', 'attrs' => [ 'fieldName' => 'Your E-Mail!' ] ],
                ],
            ],
        ];
        $content = 'definition';
        $form = $parser->parse( $blocks, 17, 'Contact', $content );
        self::assertNotNull( $form );
        self::assertSame( Form_Definition::hash_content( $content ), $form->rev );
        self::assertSame( 16, strlen( $form->rev ) );
        self::assertSame( '', $form->attributes['submitLabel'] );
        self::assertIsArray( $form->items[0] );
        self::assertSame( [ 'first', 'last' ], $form->items[1]->subkeys );
        self::assertSame( 'email_1', $form->items[2]->key );
        self::assertSame( 'email_2', $form->items[3]->key );
        self::assertSame( 'consent_1', $form->items[4]->key );
        self::assertTrue( $form->items[4]->attributes['required'] );
        self::assertSame( 'your_e_mail', $form->items[5]->key );
    }

    private function parser(): Form_Definition_Parser {
        return new Form_Definition_Parser( new Block_Defaults( dirname( __DIR__ ) . '/src/blocks' ) );
    }

    /** @param array<int,array<string,mixed>> $children @return array<string,mixed> */
    private function group( array $attrs, array $children = [] ): array {
        return [ 'blockName' => 'jp-form-builder/conditional', 'attrs' => $attrs, 'innerBlocks' => $children ];
    }

    /** @return array<string,mixed> */
    private function field_block( string $type, string $key = '' ): array {
        return [ 'blockName' => 'jp-form-builder/' . $type, 'attrs' => '' === $key ? [] : [ 'fieldName' => $key ], 'innerBlocks' => [] ];
    }

    /** @param array<int,array<string,mixed>> $children */
    private function parse( array $children ): Form_Definition {
        $form = $this->parser()->parse( [ [ 'blockName' => 'jp-form-builder/form', 'attrs' => [], 'innerBlocks' => $children ] ], 3, 'Contact', 'x' );
        self::assertNotNull( $form );
        return $form;
    }

    public function test_groups_are_items_with_their_condition_and_fields_stay_flat(): void {
        $form = $this->parse(
            [
                $this->field_block( 'select', 'topic' ),
                $this->group(
                    [ 'sourceField' => 'topic', 'operator' => 'is_one_of', 'values' => [ 'sales', 'support' ] ],
                    [
                        [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [] ],
                        $this->field_block( 'text', 'budget' ),
                        $this->field_block( 'radio', 'urgency' ),
                        [ 'blockName' => 'core/html', 'attrs' => [], 'innerBlocks' => [] ],
                    ]
                ),
                $this->field_block( 'checkbox', 'extras' ),
            ]
        );

        self::assertCount( 3, $form->items );
        $group = $form->items[1];
        self::assertInstanceOf( Conditional_Group::class, $group );
        self::assertSame( [ 'sourceField' => 'topic', 'operator' => 'is_one_of', 'values' => [ 'sales', 'support' ] ], $group->condition->to_array() );
        self::assertTrue( $group->condition->valid );
        self::assertCount( 3, $group->items );
        self::assertIsArray( $group->items[0] );
        self::assertSame( [ 'budget', 'urgency' ], array_map( static fn( Field_Definition $field ): string => $field->key, $group->fields() ) );
        self::assertSame( [ 'topic', 'budget', 'urgency', 'extras' ], array_map( static fn( Field_Definition $field ): string => $field->key, $form->fields() ) );
        self::assertSame( [ $group ], $form->groups() );
    }

    public function test_field_keys_stay_unique_across_the_whole_form(): void {
        $form = $this->parse(
            [
                $this->field_block( 'text', 'note' ),
                $this->group( [], [ $this->field_block( 'text', 'note' ), $this->field_block( 'text' ) ] ),
                $this->field_block( 'text', 'note' ),
                $this->field_block( 'text' ),
            ]
        );

        self::assertSame(
            [ 'note', 'note_2', 'text_3', 'note_3', 'text_5' ],
            array_map( static fn( Field_Definition $field ): string => $field->key, $form->fields() )
        );
    }

    /** @dataProvider invalid_conditions */
    public function test_a_condition_without_an_earlier_source_is_invalid( array $attrs, array $before, array $inside, array $after ): void {
        $form = $this->parse( array_merge( $before, [ $this->group( $attrs, $inside ) ], $after ) );

        $group = $form->groups()[0];
        self::assertFalse( $group->condition->valid );
        self::assertSame( count( $inside ), count( $group->items ), 'The group keeps its contents.' );
    }

    /** @return array<string,array{array<string,mixed>,array<int,array<string,mixed>>,array<int,array<string,mixed>>,array<int,array<string,mixed>>}> */
    public function invalid_conditions(): array {
        $text = [ 'blockName' => 'jp-form-builder/text', 'attrs' => [ 'fieldName' => 'topic' ], 'innerBlocks' => [] ];
        $is   = [ 'operator' => 'is', 'values' => [ 'a' ] ];
        return [
            'no source'              => [ $is, [ $text ], [ $text ], [] ],
            'source does not exist'  => [ [ 'sourceField' => 'nope' ] + $is, [ $text ], [], [] ],
            'source comes later'     => [ [ 'sourceField' => 'topic' ] + $is, [], [], [ $text ] ],
            'source is in the group' => [ [ 'sourceField' => 'topic' ] + $is, [], [ $text ], [] ],
            'unknown operator'       => [ [ 'sourceField' => 'topic', 'operator' => 'matches', 'values' => [] ], [ $text ], [], [] ],
        ];
    }

    public function test_a_source_inside_an_earlier_group_is_valid_and_the_source_key_is_normalised(): void {
        $form = $this->parse(
            [
                $this->group( [], [ $this->field_block( 'text', 'budget' ) ] ),
                $this->group( [ 'sourceField' => 'Budget', 'operator' => 'is_filled' ], [ $this->field_block( 'text', 'details' ) ] ),
            ]
        );

        self::assertTrue( $form->groups()[1]->condition->valid );
        self::assertSame( 'budget', $form->groups()[1]->condition->source );
    }

    public function test_condition_defaults_and_values_are_cleaned(): void {
        $form = $this->parse(
            [
                $this->field_block( 'checkbox', 'extras' ),
                $this->group( [ 'sourceField' => 'extras', 'values' => [ 'a', 3, null, 'b' ] ] ),
            ]
        );

        $condition = $form->groups()[0]->condition;
        self::assertSame( 'is', $condition->operator );
        self::assertSame( [ 'a', 'b' ], $condition->values );
        self::assertTrue( $condition->valid );
    }

    public function test_a_nested_group_is_ignored_like_a_foreign_block(): void {
        $form = $this->parse(
            [
                $this->field_block( 'text', 'topic' ),
                $this->group(
                    [],
                    [
                        $this->field_block( 'text', 'outer' ),
                        $this->group( [ 'sourceField' => 'topic' ], [ $this->field_block( 'text', 'inner' ) ] ),
                    ]
                ),
            ]
        );

        self::assertCount( 1, $form->groups() );
        self::assertCount( 1, $form->groups()[0]->items );
        self::assertSame( [ 'topic', 'outer' ], array_map( static fn( Field_Definition $field ): string => $field->key, $form->fields() ) );
    }
}
