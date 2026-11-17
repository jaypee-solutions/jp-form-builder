<?php
/** Tests parsed Form definitions. */
use JayPee\FormBuilder\Block_Defaults;
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
}
