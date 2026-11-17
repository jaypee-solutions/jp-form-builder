<?php
/**
 * Provides the plugin settings page.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Settings_Page {

    private Settings $settings;
    private Mailer $mailer;

    public function __construct( ?Settings $settings = null, ?Mailer $mailer = null ) {
        $this->settings = $settings ?? new Settings();
        $this->mailer   = $mailer ?? new Mailer( $this->settings );
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_jp_form_builder_save_settings', [ $this, 'save' ] );
        add_action( 'admin_post_jp_form_builder_test_mail', [ $this, 'test_mail' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_notices', [ $this, 'notices' ] );
    }

    public function enqueue( string $hook ): void {
        if ( 'jp_form_page_jp-form-builder-settings' !== $hook ) {
            return;
        }
        $editor_settings = wp_enqueue_code_editor( [ 'type' => 'text/html' ] );
        wp_enqueue_script( 'jp-form-builder-mail-settings', plugins_url( 'assets/admin/mail-settings.js', PLUGIN_FILE ), [ 'code-editor', 'wp-api-fetch' ], VERSION, true );
        wp_localize_script( 'jp-form-builder-mail-settings', 'jpFormBuilderMailEditor', false !== $editor_settings ? $editor_settings : [] );
    }

    public function test_mail(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to send test mail.', 'jp-form-builder' ) );
        }
        check_admin_referer( 'jp_form_builder_test_mail' );
        $to = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
        if ( '' === $to ) {
            $to = wp_get_current_user()->user_email;
        }
        $result = $this->mailer->send_test( $to );
        $url = add_query_arg( 'test-mail', is_wp_error( $result ) ? 'failed' : 'sent', menu_page_url( 'jp-form-builder-settings', false ) );
        wp_safe_redirect( $url );
        exit;
    }

    public function register_menu(): void {
        add_submenu_page( 'edit.php?post_type=jp_form', __( 'Settings', 'jp-form-builder' ), __( 'Settings', 'jp-form-builder' ), 'manage_options', 'jp-form-builder-settings', [ $this, 'render' ] );
    }

    public function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage these settings.', 'jp-form-builder' ) );
        }
        check_admin_referer( 'jp_form_builder_save_settings' );
        $input = isset( $_POST['jp_form_builder_settings'] ) && is_array( $_POST['jp_form_builder_settings'] ) ? wp_unslash( $_POST['jp_form_builder_settings'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated per-key by Settings.
        try {
            $this->settings->save( $input );
        } catch ( \InvalidArgumentException $exception ) {
            wp_safe_redirect( add_query_arg( 'settings-error', 'invalid-layout', menu_page_url( 'jp-form-builder-settings', false ) ) );
            exit;
        }
        wp_safe_redirect( add_query_arg( 'settings-updated', '1', menu_page_url( 'jp-form-builder-settings', false ) ) );
        exit;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $fields = [
            'smtp_host'             => __( 'SMTP host', 'jp-form-builder' ),
            'smtp_port'             => __( 'SMTP port', 'jp-form-builder' ),
            'smtp_encryption'       => __( 'Encryption', 'jp-form-builder' ),
            'smtp_auth'             => __( 'SMTP authentication', 'jp-form-builder' ),
            // Microsoft 365 OAuth settings stay hidden until P2b implements them (ADR 0012).
            'smtp_user'             => __( 'SMTP user', 'jp-form-builder' ),
            'smtp_password'         => __( 'SMTP password', 'jp-form-builder' ),
            'from_email'            => __( 'From email', 'jp-form-builder' ),
            'from_name'             => __( 'From name', 'jp-form-builder' ),
            'force_from'            => __( 'Force From address', 'jp-form-builder' ),
            'turnstile_site_key'    => __( 'Turnstile site key', 'jp-form-builder' ),
            'turnstile_secret_key'  => __( 'Turnstile secret key', 'jp-form-builder' ),
            'default_recipient'     => __( 'Default recipient', 'jp-form-builder' ),
            'mail_layout'           => __( 'Mail layout', 'jp-form-builder' ),
            'notification_subject'  => __( 'Default notification subject', 'jp-form-builder' ),
            'notification_body'     => __( 'Default notification body', 'jp-form-builder' ),
            'confirmation_subject'  => __( 'Default confirmation subject', 'jp-form-builder' ),
            'confirmation_body'     => __( 'Default confirmation body', 'jp-form-builder' ),
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Form Builder Settings', 'jp-form-builder' ); ?></h1>
            <?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'jp-form-builder' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['test-mail'] ) && 'sent' === $_GET['test-mail'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Test mail sent.', 'jp-form-builder' ); ?></p></div>
            <?php elseif ( isset( $_GET['test-mail'] ) && 'failed' === $_GET['test-mail'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'Test mail failed. Check the mail failure log.', 'jp-form-builder' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['settings-error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'The mail layout must contain {content}; the previous layout was kept.', 'jp-form-builder' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="jp_form_builder_save_settings" />
                <?php wp_nonce_field( 'jp_form_builder_save_settings' ); ?>
                <table class="form-table" role="presentation"><tbody>
                <?php foreach ( $fields as $key => $label ) : ?>
                    <?php if ( 'mail_layout' === $key ) : ?>
                        <tr><th colspan="2"><h2><?php esc_html_e( 'Mail', 'jp-form-builder' ); ?></h2></th></tr>
                    <?php endif; ?>
                    <?php
                    $locked = $this->settings->is_locked( $key );
                    $value  = $this->settings->get( $key );
                    $secret = in_array( $key, [ 'smtp_password', 'turnstile_secret_key', 'm365_client_secret' ], true );
                    $type   = in_array( $key, [ 'smtp_auth', 'force_from' ], true ) ? 'checkbox' : ( $secret ? 'password' : ( 'smtp_port' === $key ? 'number' : 'text' ) );
                    $choices = $this->choices( $key );
                    ?>
                    <tr><th scope="row"><label for="jpfb-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
                    <?php if ( in_array( $key, [ 'mail_layout', 'notification_body', 'confirmation_body' ], true ) ) : ?>
                        <textarea id="jpfb-<?php echo esc_attr( $key ); ?>" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" rows="8" class="large-text code" <?php disabled( $locked ); ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
                        <?php if ( 'mail_layout' === $key ) : ?>
                            <button type="button" class="button" id="jpfb-reset-mail-layout"><?php esc_html_e( 'Reset to default', 'jp-form-builder' ); ?></button>
                        <?php endif; ?>
                    <?php elseif ( $choices ) : ?>
                        <select id="jpfb-<?php echo esc_attr( $key ); ?>" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" <?php disabled( $locked ); ?>>
                            <?php foreach ( $choices as $choice => $choice_label ) : ?>
                                <option value="<?php echo esc_attr( $choice ); ?>" <?php selected( (string) $value, $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ( 'checkbox' === $type ) : ?>
                        <input type="hidden" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" value="0" />
                        <input type="checkbox" id="jpfb-<?php echo esc_attr( $key ); ?>" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( (bool) $value ); ?> <?php disabled( $locked ); ?> />
                    <?php else : ?>
                        <input type="<?php echo esc_attr( $type ); ?>" id="jpfb-<?php echo esc_attr( $key ); ?>" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $secret ? '' : (string) $value ); ?>" class="regular-text" <?php disabled( $locked ); ?>
                        <?php if ( $secret ) : ?>
                            autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== (string) $value ? __( 'Saved — overwrite to change', 'jp-form-builder' ) : __( 'Not set', 'jp-form-builder' ) ); ?>"<?php endif; ?> />
                    <?php endif; ?>
                    <?php
                    if ( $locked ) :
                        ?>
                        <p class="description"><?php esc_html_e( 'Set by constant.', 'jp-form-builder' ); ?></p><?php endif; ?>
                    <?php
                    if ( $secret ) :
                        ?>
                        <p class="description"><?php echo esc_html( '' !== (string) $value ? __( 'Set. Leave empty to keep the saved secret.', 'jp-form-builder' ) : __( 'Not set.', 'jp-form-builder' ) ); ?></p><?php endif; ?>
                    </td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php submit_button( __( 'Save settings', 'jp-form-builder' ) ); ?>
                <h2><?php esc_html_e( 'Live mail preview', 'jp-form-builder' ); ?></h2>
                <iframe id="jpfb-mail-preview" title="<?php esc_attr_e( 'Mail preview', 'jp-form-builder' ); ?>" sandbox="" style="width:100%;min-height:360px"></iframe>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="jp_form_builder_test_mail" />
                <?php wp_nonce_field( 'jp_form_builder_test_mail' ); ?>
                <label for="jpfb-test-recipient"><?php esc_html_e( 'Send test mail to', 'jp-form-builder' ); ?></label>
                <input type="email" id="jpfb-test-recipient" name="recipient" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required />
                <?php submit_button( __( 'Send test mail', 'jp-form-builder' ), 'secondary' ); ?>
            </form>
            <h2><?php esc_html_e( 'Recent mail failures', 'jp-form-builder' ); ?></h2>
            <?php foreach ( (array) get_option( 'jp_form_builder_mail_log', [] ) as $failure ) : ?>
                <p><?php echo esc_html( $failure['time'] . ' — ' . ( empty( $failure['form_id'] ) ? '' : sprintf( /* translators: %d: Form ID. */ __( 'Form %d: ', 'jp-form-builder' ), $failure['form_id'] ) ) . $failure['message'] ); ?></p>
            <?php endforeach; ?>
        </div>

        <?php
    }

    public function notices(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ( 'jp_form' !== $screen->post_type && 'jp_form_page_jp-form-builder-settings' !== $screen->id ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings_link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=jp_form&page=jp-form-builder-settings' ) ) . '">' . esc_html__( 'Form Builder Settings', 'jp-form-builder' ) . '</a>';
        if ( $this->settings->has_undecryptable_secret() ) {
            printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'A saved secret cannot be decrypted. Re-enter it in Form Builder Settings.', 'jp-form-builder' ) );
        }
        if ( ! $this->settings->turnstile_configured() ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: link to the settings page. */
                    esc_html__( 'Cloudflare Turnstile is not configured. Forms are protected by the honeypot, the minimum fill time and the rate limit only, and Confirmations cannot be sent. Add the site key and secret key in %s.', 'jp-form-builder' ),
                    $settings_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
                )
            );
            $this->confirmation_notice();
        }
        $trusted = ( new Client_Ip() )->parse_trusted( (string) $this->settings->tunable( 'trusted_proxies' ) );
        if ( ( new Client_Ip() )->looks_proxied( $_SERVER, $trusted ) ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__( 'This request came through a reverse proxy, but no trusted proxies are set. All visitors would share one rate limit. Define JP_FORM_BUILDER_TRUSTED_PROXIES with the proxy IPs or CIDR ranges (for example 10.0.0.0/8) in wp-config.php.', 'jp-form-builder' )
            );
        }
    }

    /** Lists Forms whose Confirmation cannot be sent while Turnstile is missing (ADR 0011). */
    private function confirmation_notice(): void {
        $links = [];
        foreach ( ( new Form_Repository() )->find_all() as $form ) {
            if ( ! empty( $form->attributes['confirmationEnabled'] ) ) {
                $links[] = '<a href="' . esc_url( (string) get_edit_post_link( $form->id ) ) . '">' . esc_html( $form->title ) . '</a>';
            }
        }
        if ( [] === $links ) {
            return;
        }
        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            sprintf(
                /* translators: %s: list of Form titles, linked to their editors. */
                esc_html__( 'These Forms have a Confirmation enabled, but it is not sent while Turnstile is not configured: %s', 'jp-form-builder' ),
                implode( ', ', $links ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
            )
        );
    }

    /** @return array<string,string> Option values and labels for settings with a fixed set of values; empty otherwise. */
    private function choices( string $key ): array {
        switch ( $key ) {
            case 'smtp_encryption':
                return [
                    'starttls' => __( 'STARTTLS (port 587)', 'jp-form-builder' ),
                    'smtps'    => __( 'SMTPS / implicit TLS (port 465)', 'jp-form-builder' ),
                    'none'     => __( 'None', 'jp-form-builder' ),
                ];
            default:
                return [];
        }
    }
}
