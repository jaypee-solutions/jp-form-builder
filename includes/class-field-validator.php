<?php
/**
 * Validates and cleans submitted Field values.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Field_Validator {

    public const MAX_EMAIL_LENGTH      = 254;
    public const MAX_NAME_LENGTH       = 100;
    public const MAX_STREET_LENGTH     = 200;
    public const MAX_CITY_LENGTH       = 100;
    public const MAX_POSTCODE_LENGTH   = 10;
    public const DEFAULT_TEXT_LENGTH   = 200;
    public const DEFAULT_TEXTAREA_LENGTH = 5000;

    private Phone_Normalizer $phone_normalizer;

    private Mail_Header_Guard $header_guard;

    private Condition_Evaluator $evaluator;

    public function __construct( ?Phone_Normalizer $phone_normalizer = null, ?Mail_Header_Guard $header_guard = null, ?Condition_Evaluator $evaluator = null ) {
        $this->phone_normalizer = $phone_normalizer ?? new Phone_Normalizer();
        $this->header_guard     = $header_guard ?? new Mail_Header_Guard();
        $this->evaluator        = $evaluator ?? new Condition_Evaluator();
    }

    /**
     * Validates submitted values against the Fields; keys not defined by a Field are ignored.
     *
     * Values hold only valid Fields: a string, a list of strings for Checkboxes, or a sub-key map for compound
     * Fields. Errors are keyed "key" or "key.sub".
     *
     * @param Field_Definition[]  $fields    Fields of the Form.
     * @param array<string,mixed> $submitted Submitted `fields` map.
     * @return array{values: array<string,string|array<int|string,string>>, errors: array<string,string>}
     */
    public function validate( array $fields, array $submitted ): array {
        $values = [];
        $errors = [];

        foreach ( $fields as $field ) {
            $result = $this->validate_field( $field, $submitted[ $field->key ] ?? null );

            if ( [] === $result['errors'] ) {
                $values[ $field->key ] = $result['value'];
                continue;
            }
            foreach ( $result['errors'] as $sub => $message ) {
                $errors[ '' === $sub ? $field->key : $field->key . '.' . $sub ] = $message;
            }
        }

        return [
            'values' => $values,
            'errors' => $errors,
        ];
    }

    /**
     * Validates a Form: Fields inside a hidden Conditional group are neither required nor validated, and their
     * values and errors are dropped. A Condition looks at the cleaned values of the Fields before it.
     *
     * @param array<string,mixed> $submitted Submitted `fields` map.
     * @return array{values: array<string,string|array<int|string,string>>, errors: array<string,string>}
     */
    public function validate_form( Form_Definition $form, array $submitted ): array {
        $checked  = $this->validate( $form->fields(), $submitted );
        $resolved = $this->evaluator->resolve( $form->groups(), $checked['values'] );
        $hidden   = array_flip( $resolved['hidden'] );

        return [
            'values' => $resolved['values'],
            'errors' => array_filter(
                $checked['errors'],
                static fn( string $error_key ): bool => ! isset( $hidden[ explode( '.', $error_key )[0] ] ),
                ARRAY_FILTER_USE_KEY
            ),
        ];
    }

    /**
     * @param mixed $raw Submitted value of the Field.
     * @return array{value: string|array<int|string,string>, errors: array<string,string>}
     */
    private function validate_field( Field_Definition $field, $raw ): array {
        if ( 'checkbox' === $field->type ) {
            return $this->validate_checkbox( $field, $raw );
        }
        if ( [] === $field->subkeys ) {
            return $this->validate_scalar( $field, is_string( $raw ) ? $raw : '' );
        }
        if ( 'address' === $field->type ) {
            return $this->validate_address( $field, is_array( $raw ) ? $raw : [] );
        }
        return $this->validate_split_name( $field, is_array( $raw ) ? $raw : [] );
    }

    /**
     * Checkboxes: a list of option values in the order of the options; required means at least one.
     *
     * @param mixed $raw Submitted list; a single string counts as a list of one.
     * @return array{value: string[], errors: array<string,string>}
     */
    private function validate_checkbox( Field_Definition $field, $raw ): array {
        $entries = is_array( $raw ) ? $raw : [ null === $raw ? '' : $raw ];
        $chosen  = [];
        foreach ( $entries as $entry ) {
            if ( ! is_string( $entry ) ) {
                return $this->checkbox_failure( __( 'Please choose only from the available options.', 'jp-form-builder' ) );
            }
            $value = Text::single_line( $entry );
            if ( '' !== $value ) {
                $chosen[] = $value;
            }
        }

        $options = $this->option_values( $field );
        if ( [] !== array_diff( $chosen, $options ) ) {
            return $this->checkbox_failure( __( 'Please choose only from the available options.', 'jp-form-builder' ) );
        }
        if ( [] === $chosen && $this->is_required( $field ) ) {
            return $this->checkbox_failure( $this->required_message( $this->label( $field ) ) );
        }
        return [
            'value'  => array_values( array_intersect( $options, $chosen ) ),
            'errors' => [],
        ];
    }

    /** @return array{value: string[], errors: array<string,string>} */
    private function checkbox_failure( string $message ): array {
        return [
            'value'  => [],
            'errors' => [ '' => $message ],
        ];
    }

    /** @return array{value: string, errors: array<string,string>} Errors keyed by sub-key; '' for the Field itself. */
    private function validate_scalar( Field_Definition $field, string $raw ): array {
        $label = $this->label( $field );

        if ( 'consent' === $field->type ) {
            return $this->validate_consent( $field, $raw );
        }

        $value = 'textarea' === $field->type ? Text::multi_line( $raw ) : Text::single_line( $raw );
        if ( '' === $value ) {
            return $this->is_required( $field ) ? $this->failure( '', $this->required_message( $label ) ) : $this->success( '' );
        }

        switch ( $field->type ) {
            case 'email':
                return $this->validate_email( $raw, $value );
            case 'phone':
                $phone = $this->phone_normalizer->normalize( $value, (string) ( $field->attributes['defaultRegion'] ?? 'DE' ) );
                return null === $phone
                    ? $this->failure( '', __( 'Please enter a valid phone number.', 'jp-form-builder' ) )
                    : $this->success( $phone );
            case 'select':
            case 'radio':
                return in_array( $value, $this->option_values( $field ), true )
                    ? $this->success( $value )
                    : $this->failure( '', __( 'Please choose one of the available options.', 'jp-form-builder' ) );
            default:
                return $this->check_length( $value, self::max_length( $field ), $label );
        }
    }

    /** @return array{value: string, errors: array<string,string>} */
    private function validate_consent( Field_Definition $field, string $raw ): array {
        if ( '1' === $raw ) {
            return $this->success( '1' );
        }
        if ( '' === $raw && ! $this->is_required( $field ) ) {
            return $this->success( '' );
        }
        return $this->failure( '', __( 'Please accept to continue.', 'jp-form-builder' ) );
    }

    /** @return array{value: string, errors: array<string,string>} */
    private function validate_email( string $raw, string $value ): array {
        $invalid = $this->failure( '', __( 'Please enter a valid email address.', 'jp-form-builder' ) );

        // Text::single_line() would silently remove line breaks; an address carrying them is an injection attempt.
        if ( ! $this->header_guard->is_safe( $raw ) ) {
            return $invalid;
        }

        $at = strrpos( $value, '@' );
        if ( false === $at ) {
            return $invalid;
        }
        $domain = substr( $value, $at + 1 );
        if ( 1 === preg_match( '/[^\x00-\x7F]/', $domain ) && function_exists( 'idn_to_ascii' ) ) {
            $ascii_domain = idn_to_ascii( $domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
            if ( false === $ascii_domain ) {
                return $invalid;
            }
            $value = substr( $value, 0, $at + 1 ) . $ascii_domain;
        }

        if ( strlen( $value ) > self::MAX_EMAIL_LENGTH || false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
            return $invalid;
        }
        return $this->success( $value );
    }

    /**
     * @param array<mixed> $raw Submitted sub-key map.
     * @return array{value: array<string,string>, errors: array<string,string>}
     */
    private function validate_split_name( Field_Definition $field, array $raw ): array {
        $labels = [
            'first' => Field_Labels::for_part( $field, 'first' ),
            'last'  => Field_Labels::for_part( $field, 'last' ),
        ];
        $parts  = $this->parts( $field, $raw );

        return $this->validate_parts(
            $field,
            $parts,
            $labels,
            static fn( string $sub, string $value, string $label ): ?string => Text::length( $value ) > self::part_max_length( $sub )
                ? self::too_long_message( $label, self::part_max_length( $sub ) )
                : null
        );
    }

    /**
     * @param array<mixed> $raw Submitted sub-key map.
     * @return array{value: array<string,string>, errors: array<string,string>}
     */
    private function validate_address( Field_Definition $field, array $raw ): array {
        $labels  = [];
        foreach ( $field->subkeys as $sub ) {
            $labels[ $sub ] = Field_Labels::for_part( $field, $sub );
        }
        $parts   = $this->parts( $field, $raw );

        // A country alone is the preselected default, so it does not make an optional address "filled".
        if ( ! $this->is_required( $field ) && '' === $parts['street'] . $parts['postcode'] . $parts['city'] ) {
            return $this->success( array_fill_keys( $field->subkeys, '' ) );
        }

        return $this->validate_parts(
            $field,
            $parts,
            $labels,
            static function ( string $sub, string $value, string $label ) use ( $parts ): ?string {
                switch ( $sub ) {
                    case 'street':
                    case 'city':
                        return Text::length( $value ) > self::part_max_length( $sub ) ? self::too_long_message( $label, self::part_max_length( $sub ) ) : null;
                    case 'country':
                        return isset( Countries::all()[ $value ] ) ? null : __( 'Please choose a valid country.', 'jp-form-builder' );
                    default:
                        // Postcode: only checkable against a valid country.
                        if ( ! isset( Countries::all()[ $parts['country'] ] ) ) {
                            return null;
                        }
                        return 1 === preg_match( '~' . Countries::postcode_pattern( $parts['country'] ) . '~D', $value )
                            ? null
                            : __( 'Please enter a valid postcode.', 'jp-form-builder' );
                }
            }
        );
    }

    /**
     * Shared compound logic: a compound Field is empty only when every part is; otherwise each empty part is missing.
     *
     * @param array<string,string>                    $parts   Cleaned parts keyed by sub-key.
     * @param array<string,string>                    $labels  Part labels keyed by sub-key.
     * @param callable(string,string,string):?string $check   Returns an error message for a non-empty part.
     * @return array{value: array<string,string>, errors: array<string,string>}
     */
    private function validate_parts( Field_Definition $field, array $parts, array $labels, callable $check ): array {
        $is_empty = '' === implode( '', $parts );
        if ( $is_empty && ! $this->is_required( $field ) ) {
            return $this->success( $parts );
        }

        $errors = [];
        foreach ( $parts as $sub => $value ) {
            $message = '' === $value ? $this->required_message( $labels[ $sub ] ) : $check( $sub, $value, $labels[ $sub ] );
            if ( null !== $message ) {
                $errors[ $sub ] = $message;
            }
        }
        return [
            'value'  => $parts,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<mixed> $raw Submitted sub-key map.
     * @return array<string,string>
     */
    private function parts( Field_Definition $field, array $raw ): array {
        $parts = [];
        foreach ( $field->subkeys as $sub ) {
            $value         = $raw[ $sub ] ?? '';
            $parts[ $sub ] = is_string( $value ) ? Text::single_line( $value ) : '';
        }
        return $parts;
    }

    /** @return array{value: string, errors: array<string,string>} */
    private function check_length( string $value, int $max, string $label ): array {
        return Text::length( $value ) > $max
            ? $this->failure( '', self::too_long_message( $label, $max ) )
            : $this->success( $value );
    }

    private function is_required( Field_Definition $field ): bool {
        return ! empty( $field->attributes['required'] );
    }

    /** The most characters a single-control Field accepts; the Form Renderer sets the same `maxlength`. */
    public static function max_length( Field_Definition $field ): int {
        switch ( $field->type ) {
            case 'name':
                return self::MAX_NAME_LENGTH;
            case 'email':
                return self::MAX_EMAIL_LENGTH;
            default:
                $fallback = 'textarea' === $field->type ? self::DEFAULT_TEXTAREA_LENGTH : self::DEFAULT_TEXT_LENGTH;
                $max      = (int) ( $field->attributes['maxLength'] ?? $fallback );
                return $max > 0 ? $max : $fallback;
        }
    }

    /** The most characters one part of a compound Field accepts. */
    public static function part_max_length( string $sub ): int {
        switch ( $sub ) {
            case 'street':
                return self::MAX_STREET_LENGTH;
            case 'city':
                return self::MAX_CITY_LENGTH;
            case 'postcode':
                return self::MAX_POSTCODE_LENGTH;
            default:
                return self::MAX_NAME_LENGTH;
        }
    }

    /** @return string[] */
    private function option_values( Field_Definition $field ): array {
        $options = $field->attributes['options'] ?? [];
        $values  = [];
        foreach ( is_array( $options ) ? $options : [] as $option ) {
            if ( is_array( $option ) && isset( $option['value'] ) && is_string( $option['value'] ) ) {
                $values[] = $option['value'];
            }
        }
        return $values;
    }

    private function label( Field_Definition $field ): string {
        $label = Field_Labels::for_field( $field );
        return '' !== $label ? $label : __( 'This field', 'jp-form-builder' );
    }

    private function required_message( string $label ): string {
        /* translators: %s: field label. */
        return sprintf( __( '%s is required.', 'jp-form-builder' ), $label );
    }

    private static function too_long_message( string $label, int $max ): string {
        /* translators: 1: field label, 2: maximum number of characters. */
        return sprintf( __( '%1$s must not be longer than %2$d characters.', 'jp-form-builder' ), $label, $max );
    }

    /**
     * @param string|array<string,string> $value Cleaned value.
     * @return array{value: string|array<string,string>, errors: array<string,string>}
     */
    private function success( $value ): array {
        return [
            'value'  => $value,
            'errors' => [],
        ];
    }

    /** @return array{value: string, errors: array<string,string>} */
    private function failure( string $sub, string $message ): array {
        return [
            'value'  => '',
            'errors' => [ $sub => $message ],
        ];
    }
}
