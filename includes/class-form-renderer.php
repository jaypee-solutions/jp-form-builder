<?php
/**
 * Renders a Form definition as accessible markup.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Form_Renderer {

    private static int $instances = 0;

    public function render( Form_Definition $form, int $instance = 0, string $site_key = '' ): string {
        if ( $instance < 1 ) {
            $instance = ++self::$instances;
        }

        $attrs = $form->attributes;
        $html  = '<form data-form-id="' . esc_attr( (string) $form->id ) . '" data-rev="' . esc_attr( $form->rev ) . '" data-locale="' . esc_attr( determine_locale() ) . '"';
        if ( '' !== $site_key ) {
            $html .= ' data-turnstile-sitekey="' . esc_attr( $site_key ) . '"';
        }
        $html .= ' novalidate><div class="jp-form-builder__error-summary" tabindex="-1"></div><div class="jp-form-builder__status" role="status"></div>';

        foreach ( $form->items as $item ) {
            if ( ! $item instanceof Field_Definition ) {
                $html .= render_block( $item );
                continue;
            }
            $html .= $this->render_field( $form->id, $instance, $item );
        }

        $submit_label = '' !== (string) ( $attrs['submitLabel'] ?? '' )
            ? (string) $attrs['submitLabel']
            : __( 'Submit', 'jp-form-builder' );
        $html .= '<div class="jp-form-builder__honeypot" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'jp-form-builder' ) . '<input type="text" name="_jpfb_field_914" tabindex="-1" autocomplete="off"></label></div>';
        $html .= '<noscript>' . esc_html__( 'JavaScript is required to submit this form.', 'jp-form-builder' ) . '</noscript>';
        $html .= '<button type="submit" class="wp-element-button" disabled>' . esc_html( $submit_label ) . '</button></form>';

        return $html;
    }

    private function render_field( int $form_id, int $instance, Field_Definition $field ): string {
        $attributes = $field->attributes;
        $id          = 'jpfb-' . $form_id . '-' . $instance . '-' . $field->key;
        $help_id     = $id . '-help';
        $error_id    = $id . '-error';
        $described   = $help_id . ' ' . $error_id;
        $required    = ! empty( $attributes['required'] ) ? ' required' : '';
        $html        = '<div class="jp-form-builder__field" data-field-key="' . esc_attr( $field->key ) . '">';

        if ( 'name' === $field->type && $field->subkeys ) {
            $html .= '<fieldset><legend>' . esc_html( (string) ( $attributes['label'] ?? '' ) ) . '</legend>';
            foreach ( $field->subkeys as $subkey ) {
                $sub_id = $id . '-' . $subkey;
                $label  = 'first' === $subkey ? ( $attributes['firstLabel'] ?? __( 'First name', 'jp-form-builder' ) ) : ( $attributes['lastLabel'] ?? __( 'Last name', 'jp-form-builder' ) );
                $html  .= '<label for="' . esc_attr( $sub_id ) . '">' . esc_html( (string) $label ) . '</label>';
                $html  .= '<input type="text" id="' . esc_attr( $sub_id ) . '" name="' . esc_attr( $field->key . '[' . $subkey . ']' ) . '"' . $required . ' maxlength="100" aria-describedby="' . esc_attr( $described ) . '">';
            }
            $html .= '</fieldset>';
        } elseif ( 'address' === $field->type ) {
            $html .= '<fieldset><legend>' . esc_html( (string) ( $attributes['label'] ?? '' ) ) . '</legend>';
            $labels = [
                'street'   => __( 'Street and number', 'jp-form-builder' ),
                'postcode' => __( 'Postcode', 'jp-form-builder' ),
                'city'     => __( 'City', 'jp-form-builder' ),
                'country'  => __( 'Country', 'jp-form-builder' ),
            ];
            foreach ( $field->subkeys as $subkey ) {
                $sub_id = $id . '-' . $subkey;
                $html  .= '<label for="' . esc_attr( $sub_id ) . '">' . esc_html( $labels[ $subkey ] ) . '</label>';
                $name   = esc_attr( $field->key . '[' . $subkey . ']' );
                if ( 'country' === $subkey ) {
                    $selected = (string) ( $attributes['defaultCountry'] ?? 'DE' );
                    $html    .= '<select id="' . esc_attr( $sub_id ) . '" name="' . $name . '"' . $required . ' aria-describedby="' . esc_attr( $described ) . '">';
                    foreach ( Countries::all() as $code => $country_name ) {
                        $html .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $country_name ) . '</option>';
                    }
                    $html .= '</select>';
                    continue;
                }
                $maxlength = 'street' === $subkey ? 200 : ( 'city' === $subkey ? 100 : 10 );
                $pattern   = 'postcode' === $subkey ? ' pattern="' . esc_attr( Countries::postcode_pattern( (string) ( $attributes['defaultCountry'] ?? 'DE' ) ) ) . '"' : '';
                $html     .= '<input type="text" id="' . esc_attr( $sub_id ) . '" name="' . $name . '"' . $required . ' maxlength="' . $maxlength . '"' . $pattern . ' aria-describedby="' . esc_attr( $described ) . '">';
            }
            $html .= '</fieldset>';
        } elseif ( 'consent' === $field->type ) {
            $url  = ! empty( $attributes['privacyUrl'] ) ? $attributes['privacyUrl'] : get_privacy_policy_url();
            $link = '<a href="' . esc_url( $url ) . '">' . esc_html( (string) ( $attributes['linkText'] ?? __( 'privacy policy', 'jp-form-builder' ) ) ) . '</a>';
            $text = str_replace( '{privacy_policy}', $link, esc_html( (string) ( $attributes['text'] ?? '' ) ) );
            $html .= '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '" value="1" required aria-describedby="' . esc_attr( $described ) . '"> ' . $text . '</label>';
        } elseif ( 'select' === $field->type ) {
            $html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $attributes['label'] ?? '' ) ) . '</label>';
            $html .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '"' . $required . ' aria-describedby="' . esc_attr( $described ) . '">';
            $html .= '<option value="">' . esc_html( (string) ( $attributes['placeholder'] ?? '' ) ) . '</option>';
            foreach ( $attributes['options'] ?? [] as $option ) {
                $html .= '<option value="' . esc_attr( (string) ( $option['value'] ?? '' ) ) . '">' . esc_html( (string) ( $option['label'] ?? '' ) ) . '</option>';
            }
            $html .= '</select>';
        } elseif ( 'textarea' === $field->type ) {
            $html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $attributes['label'] ?? '' ) ) . '</label>';
            $html .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '"' . $required;
            $html .= ' rows="' . esc_attr( (string) ( $attributes['rows'] ?? 6 ) ) . '" maxlength="' . esc_attr( (string) ( $attributes['maxLength'] ?? 5000 ) ) . '"';
            if ( ! empty( $attributes['placeholder'] ) ) {
                $html .= ' placeholder="' . esc_attr( (string) $attributes['placeholder'] ) . '"';
            }
            $html .= ' aria-describedby="' . esc_attr( $described ) . '"></textarea>';
        } else {
            $type  = 'email' === $field->type ? 'email' : ( 'phone' === $field->type ? 'tel' : 'text' );
            $html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $attributes['label'] ?? '' ) ) . '</label>';
            $html .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '"' . $required;
            if ( 'phone' === $field->type ) {
                $html .= ' data-default-region="' . esc_attr( (string) ( $attributes['defaultRegion'] ?? 'DE' ) ) . '"';
            }
            $html .= ' maxlength="' . esc_attr( (string) ( $attributes['maxLength'] ?? ( 'email' === $field->type ? 254 : 200 ) ) ) . '"';
            if ( ! empty( $attributes['placeholder'] ) ) {
                $html .= ' placeholder="' . esc_attr( (string) $attributes['placeholder'] ) . '"';
            }
            $html .= ' aria-describedby="' . esc_attr( $described ) . '">';
        }

        $html .= '<p id="' . esc_attr( $help_id ) . '">' . esc_html( (string) ( $attributes['helpText'] ?? '' ) ) . '</p>';
        $html .= '<div id="' . esc_attr( $error_id ) . '" class="jp-form-builder__error"></div></div>';
        return $html;
    }
}
