<?php
/**
 * Determines the client IP address, honouring trusted reverse proxies.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Client_Ip {

    /**
     * Parses the comma-separated JP_FORM_BUILDER_TRUSTED_PROXIES value; entries that are neither an IP nor a CIDR are dropped.
     *
     * @return string[]
     */
    public function parse_trusted( string $csv ): array {
        $trusted = [];
        foreach ( explode( ',', $csv ) as $entry ) {
            $entry = trim( $entry );
            if ( '' !== $entry && null !== $this->parse_range( $entry ) ) {
                $trusted[] = $entry;
            }
        }
        return $trusted;
    }

    /**
     * REMOTE_ADDR, or, when it is a trusted proxy, the rightmost X-Forwarded-For entry that is not trusted.
     *
     * @param array<string,mixed> $server  Server variables.
     * @param string[]            $trusted Trusted proxy IPs and CIDRs.
     * @return string Empty when REMOTE_ADDR is not a valid IP address.
     */
    public function resolve( array $server, array $trusted ): string {
        $remote = $this->server_string( $server, 'REMOTE_ADDR' );
        if ( null === $this->pack( $remote ) ) {
            return '';
        }
        if ( ! $this->is_trusted( $remote, $trusted ) ) {
            return $remote;
        }

        $forwarded = array_reverse( explode( ',', $this->server_string( $server, 'HTTP_X_FORWARDED_FOR' ) ) );
        foreach ( $forwarded as $entry ) {
            $entry = trim( $entry );
            if ( null !== $this->pack( $entry ) && ! $this->is_trusted( $entry, $trusted ) ) {
                return $entry;
            }
        }
        return $remote;
    }

    /**
     * Whether the request looks like it passed a reverse proxy that is not configured as trusted.
     *
     * @param array<string,mixed> $server  Server variables.
     * @param string[]            $trusted Trusted proxy IPs and CIDRs.
     */
    public function looks_proxied( array $server, array $trusted ): bool {
        if ( [] !== $trusted || '' === trim( $this->server_string( $server, 'HTTP_X_FORWARDED_FOR' ) ) ) {
            return false;
        }
        $remote = $this->server_string( $server, 'REMOTE_ADDR' );
        return false !== filter_var( $remote, FILTER_VALIDATE_IP )
            && false === filter_var( $remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
    }

    /** @param string[] $trusted Trusted proxy IPs and CIDRs. */
    private function is_trusted( string $ip, array $trusted ): bool {
        $address = $this->pack( $ip );
        if ( null === $address ) {
            return false;
        }
        foreach ( $trusted as $entry ) {
            $range = $this->parse_range( $entry );
            if ( null !== $range && strlen( $range['address'] ) === strlen( $address ) && $this->in_range( $address, $range['address'], $range['bits'] ) ) {
                return true;
            }
        }
        return false;
    }

    private function in_range( string $address, string $network, int $bits ): bool {
        $whole_bytes = intdiv( $bits, 8 );
        if ( substr( $address, 0, $whole_bytes ) !== substr( $network, 0, $whole_bytes ) ) {
            return false;
        }
        $remainder = $bits % 8;
        if ( 0 === $remainder ) {
            return true;
        }
        $mask = ( 0xFF << ( 8 - $remainder ) ) & 0xFF;
        return ( ord( $address[ $whole_bytes ] ) & $mask ) === ( ord( $network[ $whole_bytes ] ) & $mask );
    }

    /** @return array{address: string, bits: int}|null */
    private function parse_range( string $entry ): ?array {
        $parts   = explode( '/', $entry, 2 );
        $address = $this->pack( $parts[0] );
        if ( null === $address ) {
            return null;
        }

        $max_bits = strlen( $address ) * 8;
        if ( ! isset( $parts[1] ) ) {
            return [
                'address' => $address,
                'bits'    => $max_bits,
            ];
        }
        if ( 1 !== preg_match( '/^\d{1,3}$/', $parts[1] ) || (int) $parts[1] > $max_bits ) {
            return null;
        }
        return [
            'address' => $address,
            'bits'    => (int) $parts[1],
        ];
    }

    /** Packed binary form of an IP address; IPv4-mapped IPv6 addresses become IPv4. Null when invalid. */
    private function pack( string $ip ): ?string {
        if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return null;
        }
        $packed = inet_pton( $ip );
        if ( false === $packed ) {
            return null;
        }
        if ( 16 === strlen( $packed ) && str_starts_with( $packed, str_repeat( "\0", 10 ) . "\xFF\xFF" ) ) {
            return substr( $packed, 12 );
        }
        return $packed;
    }

    /** @param array<string,mixed> $server Server variables. */
    private function server_string( array $server, string $key ): string {
        return isset( $server[ $key ] ) && is_string( $server[ $key ] ) ? $server[ $key ] : '';
    }
}
