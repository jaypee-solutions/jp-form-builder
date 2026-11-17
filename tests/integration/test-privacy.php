<?php
/**
 * Verifies the plugin's suggestion for the site's privacy policy.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Privacy extends WP_UnitTestCase {

    public function test_suggested_policy_text_is_registered_for_the_plugin(): void {
        set_current_screen( 'dashboard' );
        $this->run_plugin_admin_init();

        require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
        $entries = array_filter(
            WP_Privacy_Policy_Content::get_suggested_policy_text(),
            static fn( array $entry ): bool => 'JayPee Solutions Form Builder' === $entry['plugin_name']
        );

        $this->assertCount( 1, $entries );
        $text = wp_strip_all_tags( (string) reset( $entries )['policy_text'] );
        $this->assertStringContainsString( 'not stored on this website', $text );
        $this->assertStringContainsString( 'Cloudflare Turnstile', $text );
        $this->assertStringContainsString( 'not your IP address', $text );
        $this->assertStringContainsString( 'github.com', $text );
        $this->assertStringContainsString( 'one confirmation per hour', $text );
    }

    /** Fires `admin_init` for the plugin's callbacks only: core's own callbacks send headers, which PHPUnit's output has already passed. */
    private function run_plugin_admin_init(): void {
        global $wp_filter;
        $original = $wp_filter['admin_init'];
        $only     = new WP_Hook();
        foreach ( $original->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                $function = $callback['function'];
                if ( is_array( $function ) && is_object( $function[0] ) && str_starts_with( get_class( $function[0] ), 'JayPee\\FormBuilder\\' ) ) {
                    $only->add_filter( 'admin_init', $function, $priority, (int) $callback['accepted_args'] );
                }
            }
        }
        $wp_filter['admin_init'] = $only;
        try {
            do_action( 'admin_init' );
        } finally {
            $wp_filter['admin_init'] = $original;
        }
    }
}
