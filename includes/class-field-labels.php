<?php
/**
 * Resolves the labels a Field shows, so the Form, its errors and its mails agree.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Field_Labels {

    /** The Field's own label, or the translated default for its type when the label is empty. */
    public static function for_field( Field_Definition $field ): string {
        $label = trim( (string) ( $field->attributes['label'] ?? '' ) );
        if ( '' !== $label ) {
            return $label;
        }

        $defaults = [
            'name'     => __( 'Name', 'jp-form-builder' ),
            'email'    => __( 'Email', 'jp-form-builder' ),
            'phone'    => __( 'Phone', 'jp-form-builder' ),
            'address'  => __( 'Address', 'jp-form-builder' ),
            'text'     => __( 'Text', 'jp-form-builder' ),
            'select'   => __( 'Select', 'jp-form-builder' ),
            'radio'    => __( 'Radio buttons', 'jp-form-builder' ),
            'checkbox' => __( 'Checkboxes', 'jp-form-builder' ),
            'textarea' => __( 'Message', 'jp-form-builder' ),
            'consent'  => __( 'Consent', 'jp-form-builder' ),
        ];
        return $defaults[ $field->type ] ?? '';
    }

    /** Label of one part of a compound Field (name `first`/`last`; address `street`/`postcode`/`city`/`country`). */
    public static function for_part( Field_Definition $field, string $sub ): string {
        switch ( $sub ) {
            case 'first':
                return self::attribute_or( $field, 'firstLabel', __( 'First name', 'jp-form-builder' ) );
            case 'last':
                return self::attribute_or( $field, 'lastLabel', __( 'Last name', 'jp-form-builder' ) );
            case 'street':
                return __( 'Street and number', 'jp-form-builder' );
            case 'postcode':
                return __( 'Postcode', 'jp-form-builder' );
            case 'city':
                return __( 'City', 'jp-form-builder' );
            case 'country':
                return __( 'Country', 'jp-form-builder' );
            default:
                return '';
        }
    }

    private static function attribute_or( Field_Definition $field, string $attribute, string $fallback ): string {
        $value = Text::single_line( (string) ( $field->attributes[ $attribute ] ?? '' ) );
        return '' !== $value ? $value : $fallback;
    }
}
