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

    private const SLOT_BLOCK = 'jp-form-builder/slot';

    private static int $instances = 0;

    private Condition_Evaluator $evaluator;

    public function __construct( ?Condition_Evaluator $evaluator = null ) {
        $this->evaluator = $evaluator ?? new Condition_Evaluator();
    }

    /**
     * @param int    $instance Instance number for DOM ids; 0 assigns the next free one.
     * @param string $site_key Turnstile site key; '' renders no Turnstile container.
     * @param int    $min_fill Seconds the script waits after fetching the token before it submits.
     */
    public function render( Form_Definition $form, int $instance = 0, string $site_key = '', int $min_fill = 0 ): string {
        if ( $instance < 1 ) {
            $instance = ++self::$instances;
        }

        $attrs = $form->attributes;
        $html  = '<form method="post" class="jp-form-builder" data-form-id="' . esc_attr( (string) $form->id ) . '" data-rev="' . esc_attr( $form->rev ) . '" data-locale="' . esc_attr( determine_locale() ) . '" data-rest-root="' . esc_url( rest_url( 'jp-form-builder/v1/' ) ) . '" data-min-fill="' . esc_attr( (string) $min_fill ) . '"';
        if ( '' !== $site_key ) {
            $html .= ' data-turnstile-sitekey="' . esc_attr( $site_key ) . '"';
        }
        $html .= ' novalidate><div class="jp-form-builder__error-summary" tabindex="-1"></div><div class="jp-form-builder__status" role="status"></div>';

        $html .= '<div class="jp-form-builder__fields">' . $this->render_items( $form->id, $instance, $form->items ) . '</div>';

        $submit_label = '' !== (string) ( $attrs['submitLabel'] ?? '' )
            ? (string) $attrs['submitLabel']
            : __( 'Submit', 'jp-form-builder' );
        $html .= '<div class="jp-form-builder__honeypot" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'jp-form-builder' ) . '<input type="text" name="_jpfb_field_914" tabindex="-1" autocomplete="off"></label></div>';
        if ( '' !== $site_key ) {
            $html .= '<div class="jp-form-builder__turnstile"></div>';
        }
        $html .= '<noscript>' . esc_html__( 'JavaScript is required to submit this form.', 'jp-form-builder' ) . '</noscript>';
        $html .= '<button type="submit" class="wp-element-button" disabled>' . esc_html( $submit_label ) . '</button></form>';

        return $html;
    }

    /** @param array<int,Field_Definition|Conditional_Group|Layout_Block|array<string,mixed>> $items */
    private function render_items( int $form_id, int $instance, array $items ): string {
        $html = '';
        foreach ( $items as $item ) {
            if ( $item instanceof Field_Definition ) {
                $html .= $this->render_field( $form_id, $instance, $item );
            } elseif ( $item instanceof Conditional_Group ) {
                $html .= $this->render_group( $form_id, $instance, $item );
            } elseif ( $item instanceof Layout_Block ) {
                $html .= $this->render_layout( $form_id, $instance, $item );
            } else {
                $html .= render_block( $item );
            }
        }
        return $html;
    }

    /**
     * Core renders the layout block so its layout classes and generated styles stay; WP_Block::render() applies
     * pre_render_block to every inner block, which fills each slot with the plugin's markup.
     */
    private function render_layout( int $form_id, int $instance, Layout_Block $layout ): string {
        $slots = [];
        $block = $this->slotted( $form_id, $instance, $layout, $slots );
        $fill  = static fn( $pre, array $parsed ) => self::SLOT_BLOCK === ( $parsed['blockName'] ?? '' )
            ? ( $slots[ $parsed['attrs']['slot'] ] ?? '' )
            : $pre;

        add_filter( 'pre_render_block', $fill, 10, 2 );
        try {
            return render_block( $block );
        } finally {
            remove_filter( 'pre_render_block', $fill, 10 );
        }
    }

    /**
     * @param string[] $slots Rendered markup per slot placeholder; appended to.
     * @return array<string,mixed> The layout block with a slot placeholder block per item, nested layouts included.
     */
    private function slotted( int $form_id, int $instance, Layout_Block $layout, array &$slots ): array {
        $inner = [];
        foreach ( $layout->items as $item ) {
            if ( $item instanceof Layout_Block ) {
                $inner[] = $this->slotted( $form_id, $instance, $item, $slots );
                continue;
            }
            $slots[] = $this->render_items( $form_id, $instance, [ $item ] );
            $inner[] = [
                'blockName'    => self::SLOT_BLOCK,
                'attrs'        => [ 'slot' => count( $slots ) - 1 ],
                'innerBlocks'  => [],
                'innerHTML'    => '',
                'innerContent' => [],
            ];
        }
        return array_merge( $layout->block, [ 'innerBlocks' => $inner ] );
    }

    /**
     * A group carries its Condition as JSON for view.js. A group whose Condition fails for an empty Form is rendered
     * `hidden`, so a cached page starts in the right state; an invalid Condition leaves it shown and unconditioned.
     */
    private function render_group( int $form_id, int $instance, Conditional_Group $group ): string {
        $attributes = '';
        if ( $group->condition->valid ) {
            $attributes = ' data-condition="' . esc_attr( (string) wp_json_encode( $group->condition->to_array() ) ) . '"';
            if ( ! $this->evaluator->is_met( $group->condition, [] ) ) {
                $attributes .= ' hidden';
            }
        }
        return '<div class="jp-form-builder__group"' . $attributes . '><div class="jp-form-builder__fields">' . $this->render_items( $form_id, $instance, $group->items ) . '</div></div>';
    }

    /** Radio buttons or Checkboxes: a fieldset with one labelled input per option. */
    private function render_choices( Field_Definition $field, string $id, string $described, string $label, string $indicator, bool $required ): string {
        $is_radio = 'radio' === $field->type;
        $name     = $is_radio ? $field->key : $field->key . '[]';
        // Checkboxes are required as a group (at least one), which no single `required` attribute can say.
        $group_required = ! $is_radio && $required ? ' data-required="true"' : '';
        $html           = '<fieldset aria-describedby="' . esc_attr( $described ) . '"' . $group_required . '><legend>' . esc_html( $label ) . $indicator . '</legend>';
        $position       = 0;
        foreach ( $field->attributes['options'] ?? [] as $option ) {
            ++$position;
            $value        = (string) ( $option['value'] ?? '' );
            $option_label = '' !== trim( (string) ( $option['label'] ?? '' ) ) ? (string) $option['label'] : $value;
            $option_id    = $id . '-' . $position;
            $html        .= '<label for="' . esc_attr( $option_id ) . '"><input type="' . ( $is_radio ? 'radio' : 'checkbox' ) . '" id="' . esc_attr( $option_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $is_radio && $required ? ' required' : '' ) . '> ' . esc_html( $option_label ) . '</label>';
        }
        return $html . '</fieldset>';
    }

    private function render_field( int $form_id, int $instance, Field_Definition $field ): string {
        $attributes = $field->attributes;
        $id         = 'jpfb-' . $form_id . '-' . $instance . '-' . $field->key;
        $error_id   = $id . '-error';
        $help_text  = trim( (string) ( $attributes['helpText'] ?? '' ) );
        $described  = ( '' !== $help_text ? $id . '-help ' : '' ) . $error_id;
        $required   = ! empty( $attributes['required'] ) || 'consent' === $field->type;
        $required_attribute = $required ? ' required' : '';
        $indicator  = $required ? ' <span class="jp-form-builder__required" aria-hidden="true">*</span>' : '';
        $label      = Field_Labels::for_field( $field );
        $classes    = 'jp-form-builder__field jp-form-builder__field--' . $field->type;
        if ( 'consent' !== $field->type ) {
            $width_class = Form_Style::width_class( $attributes['width'] ?? '' );
            $classes    .= '' !== $width_class ? ' ' . $width_class : '';
        }
        $html = '<div class="' . esc_attr( $classes ) . '" data-field-key="' . esc_attr( $field->key ) . '">';

        if ( 'name' === $field->type && $field->subkeys ) {
            $html .= '<fieldset><legend>' . esc_html( $label ) . $indicator . '</legend>';
            foreach ( $field->subkeys as $subkey ) {
                $sub_id = $id . '-' . $subkey;
                $autocomplete = 'first' === $subkey ? 'given-name' : 'family-name';
                $html .= '<label for="' . esc_attr( $sub_id ) . '">' . esc_html( Field_Labels::for_part( $field, $subkey ) ) . '</label>';
                $html .= '<input type="text" id="' . esc_attr( $sub_id ) . '" name="' . esc_attr( $field->key . '[' . $subkey . ']' ) . '"' . $required_attribute . ' maxlength="' . Field_Validator::part_max_length( $subkey ) . '" autocomplete="' . esc_attr( $autocomplete ) . '" aria-describedby="' . esc_attr( $described ) . '">';
            }
            $html .= '</fieldset>';
        } elseif ( 'address' === $field->type ) {
            $patterns = [];
            foreach ( Countries::all() as $code => $country_name ) {
                $patterns[ $code ] = Countries::postcode_pattern( $code );
            }
            $html .= '<fieldset data-postcode-patterns="' . esc_attr( (string) wp_json_encode( $patterns ) ) . '"><legend>' . esc_html( $label ) . $indicator . '</legend>';
            $autocomplete = [ 'street' => 'street-address', 'postcode' => 'postal-code', 'city' => 'address-level2', 'country' => 'country' ];
            foreach ( $field->subkeys as $subkey ) {
                $sub_id = $id . '-' . $subkey;
                $html .= '<label for="' . esc_attr( $sub_id ) . '">' . esc_html( Field_Labels::for_part( $field, $subkey ) ) . ( $required ? $indicator : '' ) . '</label>';
                $name = esc_attr( $field->key . '[' . $subkey . ']' );
                if ( 'country' === $subkey ) {
                    $selected = (string) ( $attributes['defaultCountry'] ?? 'DE' );
                    $html .= '<select id="' . esc_attr( $sub_id ) . '" name="' . $name . '"' . $required_attribute . ' autocomplete="country" aria-describedby="' . esc_attr( $described ) . '">';
                    foreach ( Countries::all() as $code => $country_name ) {
                        $html .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $country_name ) . '</option>';
                    }
                    $html .= '</select>';
                    continue;
                }
                $html .= '<input type="text" id="' . esc_attr( $sub_id ) . '" name="' . $name . '"' . $required_attribute . ' maxlength="' . Field_Validator::part_max_length( $subkey ) . '" autocomplete="' . esc_attr( $autocomplete[ $subkey ] ) . '" aria-describedby="' . esc_attr( $described ) . '">';
            }
            $html .= '</fieldset>';
        } elseif ( 'consent' === $field->type ) {
            $url = ! empty( $attributes['privacyUrl'] ) ? $attributes['privacyUrl'] : get_privacy_policy_url();
            $link_text = '' !== (string) ( $attributes['linkText'] ?? '' ) ? $attributes['linkText'] : __( 'privacy policy', 'jp-form-builder' );
            $text = '' !== (string) ( $attributes['text'] ?? '' ) ? $attributes['text'] : __( 'I agree to the {privacy_policy}.', 'jp-form-builder' );
            $link = '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $link_text ) . '</a>';
            $text = str_replace( '{privacy_policy}', $link, esc_html( (string) $text ) );
            $html .= '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '" value="1" required aria-describedby="' . esc_attr( $described ) . '"> ' . $text . $indicator . '</label>';
        } elseif ( 'select' === $field->type ) {
            $html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $indicator . '</label>';
            $html .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '"' . $required_attribute . ' aria-describedby="' . esc_attr( $described ) . '">';
            $html .= '<option value="">' . esc_html( (string) ( $attributes['placeholder'] ?? '' ) ) . '</option>';
            foreach ( $attributes['options'] ?? [] as $option ) {
                $html .= '<option value="' . esc_attr( (string) ( $option['value'] ?? '' ) ) . '">' . esc_html( (string) ( $option['label'] ?? '' ) ) . '</option>';
            }
            $html .= '</select>';
        } elseif ( 'radio' === $field->type || 'checkbox' === $field->type ) {
            $html .= $this->render_choices( $field, $id, $described, $label, $indicator, $required );
        } elseif ( 'textarea' === $field->type ) {
            $html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $indicator . '</label>';
            $html .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '"' . $required_attribute;
            $html .= ' rows="' . esc_attr( (string) ( $attributes['rows'] ?? 6 ) ) . '" maxlength="' . Field_Validator::max_length( $field ) . '"';
            if ( ! empty( $attributes['placeholder'] ) ) {
                $html .= ' placeholder="' . esc_attr( (string) $attributes['placeholder'] ) . '"';
            }
            $html .= ' aria-describedby="' . esc_attr( $described ) . '"></textarea>';
        } else {
            $types = [ 'email' => 'email', 'phone' => 'tel' ];
            $type  = $types[ $field->type ] ?? 'text';
            $autocomplete = [ 'name' => 'name', 'email' => 'email', 'phone' => 'tel' ];
            $auto = 'name' === $field->type && ! $field->subkeys ? 'name' : ( $autocomplete[ $field->type ] ?? '' );
            $html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $indicator . '</label>';
            $html .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field->key ) . '"' . $required_attribute;
            if ( 'phone' === $field->type ) {
                $html .= ' data-default-region="' . esc_attr( (string) ( $attributes['defaultRegion'] ?? 'DE' ) ) . '"';
            }
            if ( '' !== $auto ) {
                $html .= ' autocomplete="' . esc_attr( $auto ) . '"';
            }
            $html .= ' maxlength="' . Field_Validator::max_length( $field ) . '"';
            if ( ! empty( $attributes['placeholder'] ) ) {
                $html .= ' placeholder="' . esc_attr( (string) $attributes['placeholder'] ) . '"';
            }
            $html .= ' aria-describedby="' . esc_attr( $described ) . '">';
        }

        if ( '' !== $help_text ) {
            $html .= '<p id="' . esc_attr( $id . '-help' ) . '">' . esc_html( $help_text ) . '</p>';
        }
        $html .= '<div id="' . esc_attr( $error_id ) . '" class="jp-form-builder__error"></div></div>';
        return $html;
    }
}
