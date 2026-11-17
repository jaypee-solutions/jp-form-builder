<?php
/**
 * Adds privacy policy suggestion text to the WordPress privacy guide.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Privacy {

    public function init(): void {
        add_action( 'admin_init', [ $this, 'add_policy_content' ] );
    }

    public function add_policy_content(): void {
        wp_add_privacy_policy_content( __( 'JayPee Solutions Form Builder', 'jp-form-builder' ), $this->policy_text() );
    }

    private function policy_text(): string {
        $paragraphs = [
            '<h2>' . esc_html__( 'Contact forms', 'jp-form-builder' ) . '</h2>',
            '<p class="privacy-policy-tutorial">' . esc_html__( 'Form Builder sends what visitors enter into a Form by email. The text below describes this behaviour. Adapt it to your Forms, your mail provider and your legal basis, and remove what does not apply.', 'jp-form-builder' ) . '</p>',
            '<strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'jp-form-builder' ) . '</strong>',
            '<p>' . esc_html__( 'When you send a contact form, the data you enter is sent by email to the recipients of the form and is not stored on this website. The email is delivered through the mail server configured for this website.', 'jp-form-builder' ) . '</p>',
            '<p>' . esc_html__( 'To limit abuse, a pseudonymised value derived from your IP address (a keyed hash, not the address itself) is kept for a short time (ten minutes by default) to count how often forms are sent from one connection. If the form sends you a confirmation email, a pseudonymised value derived from your email address is kept for one hour so that no address receives more than one confirmation per hour.', 'jp-form-builder' ) . '</p>',
            '<p>' . esc_html__( 'Forms are protected by Cloudflare Turnstile. When you start filling in a form, your browser loads a script from Cloudflare, Inc., which processes your IP address and technical browser information to tell humans from bots. This website sends only a secret key and the Turnstile response to Cloudflare for verification, not your IP address. Details are in the privacy policy of Cloudflare.', 'jp-form-builder' ) . '</p>',
            '<p>' . esc_html__( 'If an email cannot be delivered, the time, the form and the error message are logged in the website database with all email addresses removed.', 'jp-form-builder' ) . '</p>',
            '<p>' . esc_html__( 'The administrator’s server periodically asks github.com whether a new version of this plugin is available. No visitor data is sent.', 'jp-form-builder' ) . '</p>',
        ];

        return wp_kses_post( implode( "\n", $paragraphs ) );
    }
}
