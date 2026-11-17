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
    private Form_Backup $backup;

    public function __construct( ?Settings $settings = null, ?Mailer $mailer = null, ?Form_Backup $backup = null ) {
        $this->settings = $settings ?? new Settings();
        $this->mailer   = $mailer ?? new Mailer( $this->settings );
        $this->backup   = $backup ?? new Form_Backup( $this->settings );
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_jp_form_builder_save_settings', [ $this, 'save' ] );
        add_action( 'admin_post_jp_form_builder_test_mail', [ $this, 'test_mail' ] );
        add_action( 'admin_post_jp_form_builder_download_backup', [ $this, 'download_backup' ] );
        add_action( 'admin_post_jp_form_builder_restore_backup', [ $this, 'restore_backup' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), [ $this, 'plugin_action_links' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_notices', [ $this, 'notices' ] );
    }

    /**
     * @param string[] $links Row actions of the Plugins list.
     * @return string[]
     */
    public function plugin_action_links( array $links ): array {
        if ( current_user_can( 'manage_options' ) ) {
            $links['jp-form-builder-backup'] = '<a href="' . esc_url( $this->download_url() ) . '">' . esc_html__( 'Back up forms', 'jp-form-builder' ) . '</a>';
        }
        return $links;
    }

    public function download_backup(): void {
        $this->authorize_backup( 'jp_form_builder_download_backup' );
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="jp-form-builder-backup-' . gmdate( 'Y-m-d' ) . '.json"' );
        echo $this->backup->export_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
        exit;
    }

    public function restore_backup(): void {
        $this->authorize_backup( 'jp_form_builder_restore_backup' );
        $file = isset( $_FILES['jp_form_builder_backup'] ) && is_array( $_FILES['jp_form_builder_backup'] ) ? $_FILES['jp_form_builder_backup'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Nonce checked in authorize_backup(); only the PHP-provided tmp_name, size and error are read.
        $error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
        if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
            $report = $this->backup->failed( __( 'The Backup file is too large.', 'jp-form-builder' ) );
        } elseif ( UPLOAD_ERR_OK !== $error || ! is_string( $file['tmp_name'] ?? null ) ) {
            $report = $this->backup->failed( __( 'No Backup file was uploaded.', 'jp-form-builder' ) );
        } else {
            $report = $this->backup->restore_file( $file['tmp_name'], ! empty( $_POST['jp_form_builder_overwrite'] ), false ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in authorize_backup().
        }
        set_transient( $this->report_key(), $report, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( 'backup', 'restored', $this->settings_url() ) );
        exit;
    }

    private function authorize_backup( string $nonce_action ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage Backups.', 'jp-form-builder' ) );
        }
        check_admin_referer( $nonce_action );
    }

    private function download_url(): string {
        return wp_nonce_url( admin_url( 'admin-post.php?action=jp_form_builder_download_backup' ), 'jp_form_builder_download_backup' );
    }

    /** The settings page URL; menu_page_url() is empty in admin-post requests, where admin_menu has not run. */
    private function settings_url(): string {
        return admin_url( 'edit.php?post_type=jp_form&page=jp-form-builder-settings' );
    }

    private function report_key(): string {
        return 'jp_form_builder_backup_report_' . get_current_user_id();
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
        $url = add_query_arg( 'test-mail', is_wp_error( $result ) ? 'failed' : 'sent', $this->settings_url() );
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
            wp_safe_redirect( add_query_arg( 'settings-error', 'invalid-layout', $this->settings_url() ) );
            exit;
        }
        wp_safe_redirect( add_query_arg( 'settings-updated', '1', $this->settings_url() ) );
        exit;
    }

    /** @return array<string,array{title:string,description:string,fields:array<string,string>}> */
    private function sections(): array {
        return [
            'smtp'       => [
                'title'       => __( 'SMTP', 'jp-form-builder' ),
                'description' => __( 'Leave the host empty to send mail with the default WordPress mailer. Saved secrets are encrypted with a key derived from the site’s salts; if you change the salts, enter them again.', 'jp-form-builder' ),
                'fields'      => [
                    'smtp_host'       => __( 'SMTP host', 'jp-form-builder' ),
                    'smtp_port'       => __( 'SMTP port', 'jp-form-builder' ),
                    'smtp_encryption' => __( 'Encryption', 'jp-form-builder' ),
                    'smtp_auth'       => __( 'SMTP authentication', 'jp-form-builder' ),
                    // Microsoft 365 OAuth settings stay hidden until P2b implements them (ADR 0012).
                    'smtp_user'       => __( 'SMTP user', 'jp-form-builder' ),
                    'smtp_password'   => __( 'SMTP password', 'jp-form-builder' ),
                ],
            ],
            'sender'     => [
                'title'       => __( 'Sender', 'jp-form-builder' ),
                'description' => __( 'The address and name mail is sent from. Unless forced, they only replace the WordPress default sender (wordpress@your-domain).', 'jp-form-builder' ),
                'fields'      => [
                    'from_email' => __( 'From email', 'jp-form-builder' ),
                    'from_name'  => __( 'From name', 'jp-form-builder' ),
                    'force_from' => __( 'Force From address', 'jp-form-builder' ),
                ],
            ],
            'turnstile'  => [
                'title'       => __( 'Spam protection (Cloudflare Turnstile)', 'jp-form-builder' ),
                'description' => __( 'Create a Turnstile widget in the Cloudflare dashboard and enter its keys. Without both keys, Forms rely on the honeypot, the minimum fill time and the rate limit, and Confirmations are not sent.', 'jp-form-builder' ),
                'fields'      => [
                    'turnstile_site_key'   => __( 'Turnstile site key', 'jp-form-builder' ),
                    'turnstile_secret_key' => __( 'Turnstile secret key', 'jp-form-builder' ),
                ],
            ],
            'recipients' => [
                'title'       => __( 'Recipients', 'jp-form-builder' ),
                'description' => __( 'Used by Forms without Recipients of their own. If this is empty too, the site administrator email receives the Notification.', 'jp-form-builder' ),
                'fields'      => [
                    'default_recipient' => __( 'Default recipient', 'jp-form-builder' ),
                ],
            ],
            'mail'       => [
                'title'       => __( 'Mail', 'jp-form-builder' ),
                'description' => __( 'The layout that frames every mail and the default texts of Forms that do not set their own Body templates.', 'jp-form-builder' ),
                'fields'      => [
                    'mail_layout'          => __( 'Mail layout', 'jp-form-builder' ),
                    'notification_subject' => __( 'Default notification subject', 'jp-form-builder' ),
                    'notification_body'    => __( 'Default notification body', 'jp-form-builder' ),
                    'confirmation_subject' => __( 'Default confirmation subject', 'jp-form-builder' ),
                    'confirmation_body'    => __( 'Default confirmation body', 'jp-form-builder' ),
                ],
            ],
        ];
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
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
                <?php foreach ( $this->sections() as $section_id => $section ) : ?>
                    <h2 class="title" id="jpfb-section-<?php echo esc_attr( $section_id ); ?>"><?php echo esc_html( $section['title'] ); ?></h2>
                    <p><?php echo esc_html( $section['description'] ); ?></p>
                    <table class="form-table" role="presentation"><tbody>
                    <?php foreach ( $section['fields'] as $key => $label ) : ?>
                        <?php $this->render_field( $key, $label ); ?>
                    <?php endforeach; ?>
                    </tbody></table>
                    <?php if ( 'mail' === $section_id ) : ?>
                        <h3><?php esc_html_e( 'Live mail preview', 'jp-form-builder' ); ?></h3>
                        <iframe id="jpfb-mail-preview" title="<?php esc_attr_e( 'Mail preview', 'jp-form-builder' ); ?>" sandbox="" style="width:100%;min-height:360px"></iframe>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php submit_button( __( 'Save settings', 'jp-form-builder' ) ); ?>
            </form>
            <h2 class="title" id="jpfb-test-mail"><?php esc_html_e( 'Test mail', 'jp-form-builder' ); ?></h2>
            <p><?php esc_html_e( 'Save your changes first: the test mail uses the saved settings.', 'jp-form-builder' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="jp_form_builder_test_mail" />
                <?php wp_nonce_field( 'jp_form_builder_test_mail' ); ?>
                <label for="jpfb-test-recipient"><?php esc_html_e( 'Send test mail to', 'jp-form-builder' ); ?></label>
                <input type="email" id="jpfb-test-recipient" name="recipient" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required />
                <?php submit_button( __( 'Send test mail', 'jp-form-builder' ), 'secondary' ); ?>
            </form>
            <h2 class="title" id="jpfb-backup"><?php esc_html_e( 'Backup & restore', 'jp-form-builder' ); ?></h2>
            <?php $this->render_backup_report(); ?>
            <p><?php esc_html_e( 'A Backup holds every Form and the settings that are not secrets or set by constants. It contains no submissions.', 'jp-form-builder' ); ?></p>
            <p><a class="button" href="<?php echo esc_url( $this->download_url() ); ?>"><?php esc_html_e( 'Download backup', 'jp-form-builder' ); ?></a></p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="jp_form_builder_restore_backup" />
                <?php wp_nonce_field( 'jp_form_builder_restore_backup' ); ?>
                <p>
                    <label for="jpfb-backup-file"><?php esc_html_e( 'Backup file', 'jp-form-builder' ); ?></label>
                    <input type="file" id="jpfb-backup-file" name="jp_form_builder_backup" accept=".json,application/json" required aria-describedby="jpfb-backup-limit" />
                </p>
                <p class="description" id="jpfb-backup-limit">
                    <?php
                    /* translators: %s: maximum Backup file size, for example "2 MB". */
                    echo esc_html( sprintf( __( 'Maximum file size: %s.', 'jp-form-builder' ), size_format( (int) $this->settings->tunable( 'max_backup_bytes' ) ) ) );
                    ?>
                </p>
                <p><label><input type="checkbox" name="jp_form_builder_overwrite" value="1" /> <?php esc_html_e( 'Overwrite Forms with the same ID', 'jp-form-builder' ); ?></label></p>
                <?php submit_button( __( 'Restore from backup', 'jp-form-builder' ), 'secondary' ); ?>
            </form>
            <p><?php esc_html_e( 'Deleting the plugin removes all Forms and the settings. Download a backup first.', 'jp-form-builder' ); ?></p>
            <h2 class="title" id="jpfb-mail-failures"><?php esc_html_e( 'Recent mail failures', 'jp-form-builder' ); ?></h2>
            <?php $this->render_mail_failures(); ?>
        </div>
        <?php
    }

    /** One settings row; a constant-locked field is disabled and says so, a secret is never printed. */
    private function render_field( string $key, string $label ): void {
        $locked  = $this->settings->is_locked( $key );
        $value   = $this->settings->get( $key );
        $secret  = $this->settings->is_secret( $key );
        $type    = in_array( $key, [ 'smtp_auth', 'force_from' ], true ) ? 'checkbox' : ( $secret ? 'password' : ( 'smtp_port' === $key ? 'number' : 'text' ) );
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
        <?php if ( $locked ) : ?>
            <p class="description"><?php esc_html_e( 'Set by constant.', 'jp-form-builder' ); ?></p>
        <?php endif; ?>
        <?php if ( $secret ) : ?>
            <p class="description"><?php echo esc_html( '' !== (string) $value ? __( 'Set. Leave empty to keep the saved secret.', 'jp-form-builder' ) : __( 'Not set.', 'jp-form-builder' ) ); ?></p>
        <?php endif; ?>
        </td></tr>
        <?php
    }

    private function render_mail_failures(): void {
        $failures = array_filter( (array) get_option( 'jp_form_builder_mail_log', [] ), 'is_array' );
        if ( [] === $failures ) {
            echo '<p>' . esc_html__( 'No mail failures recorded.', 'jp-form-builder' ) . '</p>';
            return;
        }
        echo '<ul class="ul-disc">';
        foreach ( $failures as $failure ) {
            $form_id = (int) ( $failure['form_id'] ?? 0 );
            /* translators: %d: Form ID. */
            $form = $form_id > 0 ? sprintf( __( 'Form %d: ', 'jp-form-builder' ), $form_id ) : '';
            echo '<li>' . esc_html( ( $failure['time'] ?? '' ) . ' — ' . $form . ( $failure['message'] ?? '' ) ) . '</li>';
        }
        echo '</ul>';
    }

    /** Shows the result of the last restore once, after the redirect. */
    private function render_backup_report(): void {
        if ( ! isset( $_GET['backup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only; the report is stored per user.
            return;
        }
        $report = get_transient( $this->report_key() );
        delete_transient( $this->report_key() );
        if ( ! is_array( $report ) ) {
            return;
        }
        $lines = $this->backup->describe( $report );
        if ( '' !== $report['error'] ) {
            $class = 'notice-error';
        } elseif ( [] !== $report['rejected'] ) {
            $class = 'notice-warning';
        } else {
            $class = 'notice-success';
        }
        ?>
        <div class="notice <?php echo esc_attr( $class ); ?>">
            <?php if ( [] === $lines ) : ?>
                <p><?php esc_html_e( 'The Backup file contains nothing to restore.', 'jp-form-builder' ); ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $lines as $line ) : ?>
                        <li><?php echo esc_html( $line ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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
