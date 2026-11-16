<?php
/** Renders a Form definition as accessible markup. */
declare( strict_types = 1 );
namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;
final class Form_Renderer {
    public function render( Form_Definition $form, int $instance = 1, string $site_key = '' ): string {
        $attrs = $form->attributes;
        $html = '<form data-form-id="' . esc_attr( (string) $form->id ) . '" data-rev="' . esc_attr( $form->rev ) . '" data-locale="' . esc_attr( determine_locale() ) . '"';
        if ( '' !== $site_key ) {
            $html .= ' data-turnstile-sitekey="' . esc_attr( $site_key ) . '"'; }
        $html .= ' novalidate><div class="jp-form-builder__error-summary" tabindex="-1"></div><div class="jp-form-builder__status" role="status"></div>';
        foreach ( $form->items as $item ) {
            if ( ! $item instanceof Field_Definition ) {
                $html .= render_block( $item );
                continue; }
            $a = $item->attributes;
            $label = esc_html( (string) ( $a['label'] ?? '' ) );
            $id = 'jpfb-' . $form->id . '-' . $instance . '-' . $item->key;
            $help_id = $id . '-help';
            $error_id = $id . '-error';
            $described = trim( $help_id . ' ' . $error_id );
            $html .= '<div class="jp-form-builder__field" data-field-key="' . esc_attr( $item->key ) . '">';
            if ( $item->subkeys ) {
                $html .= '<fieldset><legend>' . $label . '</legend>';
                foreach ( $item->subkeys as $sub ) {
                    $subid = $id . '-' . $sub;
                    $html .= '<label for="' . esc_attr( $subid ) . '">' . esc_html( ucfirst( $sub ) ) . '</label><input id="' . esc_attr( $subid ) . '" name="' . esc_attr( $item->key . '[' . $sub . ']' ) . '"' . ( ! empty( $a['required'] ) ? ' required' : '' ) . ' aria-describedby="' . esc_attr( $described ) . '">'; }
                $html .= '</fieldset>';
            } elseif ( 'consent' === $item->type ) {
                $url = ! empty( $a['privacyUrl'] ) ? $a['privacyUrl'] : get_privacy_policy_url();
                $text = str_replace( '{privacy_policy}', '<a href="' . esc_url( $url ) . '">' . esc_html( (string) ( $a['linkText'] ?? __( 'privacy policy', 'jp-form-builder' ) ) ) . '</a>', esc_html( (string) ( $a['text'] ?? '' ) ) );
                $html .= '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $item->key ) . '" value="1" required aria-describedby="' . esc_attr( $described ) . '"> ' . $text . '</label>';
            } else {
                $type = 'email' === $item->type ? 'email' : ( 'phone' === $item->type ? 'tel' : ( 'textarea' === $item->type ? 'textarea' : ( 'select' === $item->type ? 'select' : 'text' ) ) );
                $html .= '<label for="' . esc_attr( $id ) . '">' . $label . '</label>';
                $common = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $item->key ) . '"' . ( ! empty( $a['required'] ) ? ' required' : '' ) . ' aria-describedby="' . esc_attr( $described ) . '"';
                if ( 'textarea' === $type ) {
                    $html .= '<textarea' . $common . ' rows="' . esc_attr( (string) ( $a['rows'] ?? 6 ) ) . '" maxlength="' . esc_attr( (string) ( $a['maxLength'] ?? 5000 ) ) . '"></textarea>'; } elseif ( 'select' === $type ) {
                    $html .= '<select' . $common . '><option value="">' . esc_html( (string) ( $a['placeholder'] ?? '' ) ) . '</option>';
                    foreach ( $a['options'] ?? [] as $option ) {
                        $html .= '<option value="' . esc_attr( (string) ( $option['value'] ?? '' ) ) . '">' . esc_html( (string) ( $option['label'] ?? '' ) ) . '</option>';
                    } $html .= '</select>'; } else {
                        $html .= '<input type="' . esc_attr( $type ) . '"' . $common . ( isset( $a['maxLength'] ) ? ' maxlength="' . esc_attr( (string) $a['maxLength'] ) . '"' : '' ) . '>'; }
            }
            if ( ! empty( $a['helpText'] ) ) {
                $html .= '<p id="' . esc_attr( $help_id ) . '">' . esc_html( (string) $a['helpText'] ) . '</p>'; }
            $html .= '<div id="' . esc_attr( $error_id ) . '" class="jp-form-builder__error"></div></div>';
        }
        $html .= '<div class="jp-form-builder__honeypot" aria-hidden="true"><label>Leave blank<input name="contact_website" tabindex="-1" autocomplete="off"></label></div><noscript>' . esc_html__( 'JavaScript is required to submit this form.', 'jp-form-builder' ) . '</noscript><button type="submit" class="wp-element-button" disabled>' . esc_html( (string) ( $attrs['submitLabel'] ?? '' ) ) . '</button></form>';
        return $html;
    }
}
