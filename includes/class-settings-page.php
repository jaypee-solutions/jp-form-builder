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

    public function __construct( ?Settings $settings = null ) {
        $this->settings = $settings ?? new Settings();
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_jp_form_builder_save_settings', [ $this, 'save' ] );
        add_action( 'admin_notices', [ $this, 'notices' ] );
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
        $this->settings->save( $input );
        wp_safe_redirect( add_query_arg( 'settings-updated', '1', menu_page_url( 'jp-form-builder-settings', false ) ) );
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
            'smtp_auth_method'      => __( 'SMTP authentication method', 'jp-form-builder' ),
            'smtp_user'             => __( 'SMTP user', 'jp-form-builder' ),
            'smtp_password'         => __( 'SMTP password', 'jp-form-builder' ),
            'm365_tenant_id'        => __( 'Microsoft 365 tenant ID', 'jp-form-builder' ),
            'm365_client_id'        => __( 'Microsoft 365 client ID', 'jp-form-builder' ),
            'm365_client_secret'    => __( 'Microsoft 365 client secret', 'jp-form-builder' ),
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
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="jp_form_builder_save_settings" />
                <?php wp_nonce_field( 'jp_form_builder_save_settings' ); ?>
                <table class="form-table" role="presentation"><tbody>
                <?php
                foreach ( $fields as $key => $label ) :
                    $locked = $this->settings->is_locked( $key );
                    $value  = $this->settings->get( $key );
                    $secret = in_array( $key, [ 'smtp_password', 'turnstile_secret_key', 'm365_client_secret' ], true );
                    $type   = in_array( $key, [ 'smtp_auth', 'force_from' ], true ) ? 'checkbox' : ( $secret ? 'password' : ( 'smtp_port' === $key ? 'number' : 'text' ) );
                    ?>
                    <tr><th scope="row"><label for="jpfb-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
                    <?php if ( 'checkbox' === $type ) : ?>
                        <input type="hidden" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" value="0" />
                        <input type="checkbox" id="jpfb-<?php echo esc_attr( $key ); ?>" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( (bool) $value ); ?> <?php disabled( $locked ); ?> />
                    <?php else : ?>
                        <input type="<?php echo esc_attr( $type ); ?>" id="jpfb-<?php echo esc_attr( $key ); ?>" name="jp_form_builder_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $secret ? '' : (string) $value ); ?>" class="regular-text" <?php disabled( $locked ); ?>
                        <?php
                        if ( $secret ) :
                            ?>
                            autocomplete="new-password" readonly="readonly" onfocus="this.removeAttribute('readonly');" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" placeholder="<?php echo esc_attr( '' !== (string) $value ? __( 'Saved — overwrite to change', 'jp-form-builder' ) : __( 'Not set', 'jp-form-builder' ) ); ?>"<?php endif; ?> />
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
            </form>
        </div>

        <?php
    }

    public function notices(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ( 'jp_form' !== $screen->post_type && 'jp_form_page_jp-form-builder-settings' !== $screen->id ) ) {
            return;
        }
        if ( $this->settings->has_undecryptable_secret() ) {
            printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'A saved secret cannot be decrypted. Re-enter it in Form Builder Settings.', 'jp-form-builder' ) );
        }
    }
}
