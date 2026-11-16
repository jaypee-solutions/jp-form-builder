<?php
/**
 * Unit tests for Mail rendering and composition.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class MailCoreTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['jp_form_builder_test_options'] = [];
    }


    public function test_header_guard_and_reply_to(): void {
        $guard = new Mail_Header_Guard();
        $this->assertTrue( $guard->is_safe( 'okay' ) );
        $this->assertFalse( $guard->is_safe( "bad\rvalue" ) );
        $this->assertSame( 'Reply-To: "Jane" <jane@example.test>', $guard->reply_to( 'J"<a>ne', 'jane@example.test' ) );
        $this->assertNull( $guard->reply_to( 'Name', "bad\n@example.test" ) );
    }

    public function test_body_templates_are_single_pass_and_support_labels_sections_and_escaping(): void {
        $template = new Mail_Template();
        $fields   = [ new Submitted_Field( 'name', 'Your name', 'name', '<b>Ada</b>' ), new Submitted_Field( 'note', 'Note', 'textarea', "one\ntwo" ), new Submitted_Field( 'other_key', 'Other', 'text', '{name}' ) ];
        $html   = $template->render( '{name:label}={name};{#note}{note}{/note}{#missing}hidden{/missing} {unknown} {other_key}', $fields );
        $this->assertSame( 'Your name=&lt;b&gt;Ada&lt;/b&gt;;one<br>' . "\n" . 'two  {name}', $html );
    }

    public function test_text_rendering_and_all_fields(): void {
        $template = new Mail_Template();
        $fields   = [ new Submitted_Field( 'message', 'Message', 'textarea', "first\nsecond" ) ];
        $this->assertSame( "Message: first\n  second", $template->render( '{all_fields}', $fields, [], 'text' ) );
        $this->assertSame( "Message: first\n  second", $template->render( '{all_fields}', $fields, [], 'text' ) );
    }
    public function test_text_mode_converts_html_and_indents_multiline_field_values(): void {
        $template = new Mail_Template();
        $fields   = [ new Submitted_Field( 'message', 'Message', 'textarea', "first\nsecond" ) ];
        $this->assertSame( "Hello\nworld\nMessage: first\n  second", $template->render( '<p>Hello<br>world</p>{all_fields}', $fields, [], 'text' ) );
    }


    public function test_builder_form_precedence_default_subject_and_page_url_host(): void {
        $settings = new Settings();
        $settings->save( [ 'notification_subject' => 'Site default', 'notification_body' => 'Site body' ] );
        $builder = new Mail_Builder( new Mail_Template(), new Mail_Layout( $settings, __DIR__ . '/../templates/mail/layout.html' ), null, $settings );
        $fields  = [ new Submitted_Field( 'message', 'Message', 'textarea', "a\nb" ) ];
        $form_mail = $builder->notification(
            [ 'team@example.test' ],
            [ 'subject' => 'Form {form_title}', 'notificationBody' => 'Form body' ],
            $fields,
            [ 'form_title' => 'Contact', 'site_name' => 'Site', 'page_url' => 'https://attacker.test/path' ]
        );
        $this->assertSame( 'Form Contact', $form_mail->subject() );
        $this->assertStringNotContainsString( 'attacker.test', $form_mail->html_body() );

        $GLOBALS['jp_form_builder_test_options'] = [];
        $default_mail = ( new Mail_Builder( new Mail_Template(), new Mail_Layout( new Settings(), __DIR__ . '/../templates/mail/layout.html' ) ) )->notification( [ 'team@example.test' ], [], [], [ 'form_title' => 'Contact', 'site_name' => 'Site' ] );
        $this->assertSame( 'New message via Contact', $default_mail->subject() );
        $this->assertSame( '', $default_mail->text_body() );
    }

    public function test_builder_limits_subject_value_and_total_lengths(): void {
        $builder = new Mail_Builder( new Mail_Template(), new Mail_Layout( new Settings(), __DIR__ . '/../templates/mail/layout.html' ) );
        $value   = str_repeat( 'x', 240 );
        $mail    = $builder->notification( [], [ 'subject' => str_repeat( 's', 245 ) . ' {name}' ], [ new Submitted_Field( 'name', 'Name', 'text', $value ) ], [] );
        $this->assertSame( 250, strlen( $mail->subject() ) );
    }

    public function test_builder_uses_site_defaults_and_confirmation_never_renders_fields(): void {
        $settings = new Settings();
        $settings->save( [ 'notification_subject' => 'Site {name}', 'notification_body' => 'Hi {name}' ] );
        $builder = new Mail_Builder( new Mail_Template(), new Mail_Layout( $settings, __DIR__ . '/../templates/mail/layout.html' ), null, $settings );
        $fields  = [ new Submitted_Field( 'name', 'Name', 'name', 'Ada' ), new Submitted_Field( 'email', 'Email', 'email', 'ada@example.test', true ) ];
        $mail    = $builder->notification( [ 'team@example.test' ], [ 'subject' => '', 'notificationBody' => '' ], $fields, [ 'form_title' => 'Contact', 'site_name' => 'Site' ] );
        $this->assertSame( 'Site Ada', $mail->subject() );
        $this->assertSame( [ 'Reply-To: "Ada" <ada@example.test>' ], $mail->headers() );
        $confirmation = $builder->confirmation( 'ada@example.test', [ 'team@example.test' ], [ 'confirmationSubject' => 'Hi {name}', 'confirmationBody' => 'Your value was {email}; {all_fields} {#email}secret{/email}' ], $fields, [ 'form_title' => 'Contact', 'site_name' => 'Site' ] );
        $this->assertSame( 'Hi', $confirmation->subject() );
        $this->assertStringNotContainsString( 'ada@example.test', $confirmation->html_body() );
        $this->assertStringNotContainsString( 'secret', $confirmation->html_body() );
        $this->assertSame( [ 'Reply-To: "" <team@example.test>' ], $confirmation->headers() );
    }
}
