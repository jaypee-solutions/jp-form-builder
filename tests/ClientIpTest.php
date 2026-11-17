<?php
/**
 * Unit tests for Client_Ip.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase {

    /** @return array<string,string> */
    private function server( string $remote, ?string $forwarded = null ): array {
        $server = [ 'REMOTE_ADDR' => $remote ];
        if ( null !== $forwarded ) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwarded;
        }
        return $server;
    }

    public function test_uses_remote_addr_without_trusted_proxies(): void {
        $this->assertSame( '203.0.113.9', ( new Client_Ip() )->resolve( $this->server( '203.0.113.9' ) ) );
    }

    public function test_spoofed_forwarded_header_is_ignored_without_a_trusted_proxy(): void {
        $this->assertSame( '203.0.113.9', ( new Client_Ip() )->resolve( $this->server( '203.0.113.9', '1.2.3.4' ) ) );
        $this->assertSame( '203.0.113.9', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $this->server( '203.0.113.9', '1.2.3.4' ) ) );
    }

    public function test_trusted_ipv4_cidr_proxy_uses_the_forwarded_client(): void {
        $this->assertSame( '198.51.100.7', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $this->server( '10.1.2.3', '198.51.100.7' ) ) );
        $this->assertSame( '198.51.100.7', ( new Client_Ip( [ '172.16.0.0/12' ] ) )->resolve( $this->server( '172.18.0.2', '198.51.100.7' ) ) );
        $this->assertSame( '172.32.0.2', ( new Client_Ip( [ '172.16.0.0/12' ] ) )->resolve( $this->server( '172.32.0.2', '198.51.100.7' ) ), 'outside the /12' );
    }

    public function test_trusted_single_ip_proxy(): void {
        $this->assertSame( '198.51.100.7', ( new Client_Ip( [ '192.0.2.1' ] ) )->resolve( $this->server( '192.0.2.1', '198.51.100.7' ) ) );
        $this->assertSame( '192.0.2.2', ( new Client_Ip( [ '192.0.2.1' ] ) )->resolve( $this->server( '192.0.2.2', '198.51.100.7' ) ) );
    }

    public function test_trusted_ipv6_cidr_proxy(): void {
        $trusted = [ 'fd00::/8', '2001:db8::/32' ];

        $this->assertSame( '2001:db9::5', ( new Client_Ip( $trusted ) )->resolve( $this->server( 'fd12:3456::1', '2001:db9::5' ) ) );
        $this->assertSame( '2001:db9::5', ( new Client_Ip( $trusted ) )->resolve( $this->server( '2001:db8:1::1', '2001:db9::5' ) ) );
        $this->assertSame( '2001:db9::1', ( new Client_Ip( $trusted ) )->resolve( $this->server( '2001:db9::1', '198.51.100.7' ) ) );
    }

    public function test_ipv4_mapped_remote_addr_matches_ipv4_ranges(): void {
        $this->assertSame( '198.51.100.7', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $this->server( '::ffff:10.1.2.3', '198.51.100.7' ) ) );
    }

    public function test_takes_the_rightmost_untrusted_hop(): void {
        $server = $this->server( '10.0.0.1', '6.6.6.6, 198.51.100.7, 10.0.0.2' );

        $this->assertSame( '198.51.100.7', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $server ) );
    }

    public function test_invalid_forwarded_entries_are_ignored(): void {
        $server = $this->server( '10.0.0.1', '198.51.100.7, not-an-ip, 198.51.100.8:4711, ' );

        $this->assertSame( '198.51.100.7', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $server ) );
    }

    public function test_falls_back_to_remote_addr_when_nothing_usable_is_forwarded(): void {
        $this->assertSame( '10.0.0.1', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $this->server( '10.0.0.1' ) ) );
        $this->assertSame( '10.0.0.1', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $this->server( '10.0.0.1', 'garbage' ) ) );
        $this->assertSame( '10.0.0.1', ( new Client_Ip( [ '10.0.0.0/8' ] ) )->resolve( $this->server( '10.0.0.1', '10.0.0.5, 10.0.0.6' ) ) );
    }

    public function test_invalid_remote_addr_yields_an_empty_string(): void {
        $this->assertSame( '', ( new Client_Ip() )->resolve( [] ) );
        $this->assertSame( '', ( new Client_Ip( [ '0.0.0.0/0' ] ) )->resolve( $this->server( 'unknown', '1.2.3.4' ) ) );
    }

    public function test_from_settings_keeps_valid_trusted_entries_only(): void {
        $trusted_for = static fn( string $csv ): Client_Ip => Client_Ip::from_settings(
            new Settings( static fn( string $name ): array => 'JP_FORM_BUILDER_TRUSTED_PROXIES' === $name ? [ true, $csv ] : [ false, null ] )
        );
        $server      = [ 'REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7' ];

        $this->assertSame( '198.51.100.7', $trusted_for( ' 10.0.0.0/8, nope, 192.0.2.1 ,10.0.0.0/33, 1.2.3.4/x ' )->resolve( $server ), 'the valid single IP is trusted' );
        $this->assertSame( '192.0.2.1', $trusted_for( 'nope, 10.0.0.0/33' )->resolve( $server ), 'only invalid entries trust nothing' );
        $this->assertSame( '192.0.2.1', ( new Client_Ip() )->resolve( $server ) );
        $this->assertSame( '192.0.2.1', Client_Ip::from_settings( new Settings() )->resolve( $server ), 'unset trusts nothing' );
    }

    public function test_looks_proxied(): void {
        $this->assertTrue( ( new Client_Ip() )->looks_proxied( $this->server( '172.18.0.2', '198.51.100.7' ) ) );
        $this->assertTrue( ( new Client_Ip() )->looks_proxied( $this->server( '127.0.0.1', '198.51.100.7' ) ) );
        $this->assertTrue( ( new Client_Ip() )->looks_proxied( $this->server( '::1', '198.51.100.7' ) ) );
        $this->assertFalse( ( new Client_Ip( [ '172.16.0.0/12' ] ) )->looks_proxied( $this->server( '172.18.0.2', '198.51.100.7' ) ), 'proxies configured' );
        $this->assertFalse( ( new Client_Ip() )->looks_proxied( $this->server( '172.18.0.2' ) ), 'no forwarded header' );
        $this->assertFalse( ( new Client_Ip() )->looks_proxied( $this->server( '172.18.0.2', '' ) ), 'empty forwarded header' );
        $this->assertFalse( ( new Client_Ip() )->looks_proxied( $this->server( '203.0.113.9', '198.51.100.7' ) ), 'public remote' );
    }
}
