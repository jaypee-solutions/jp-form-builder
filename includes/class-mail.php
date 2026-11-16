<?php
/**
 * Immutable mail value object.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

defined( 'ABSPATH' ) || exit;

final class Mail {

    /** @var string[] */
    private array $recipients;
    private string $subject;
    private string $html_body;
    private string $text_body;
    /** @var string[] */
    private array $headers;

    /**
     * @param string[] $recipients
     * @param string[] $headers
     */
    public function __construct( array $recipients, string $subject, string $html_body, string $text_body, array $headers = [] ) {
        $this->recipients = array_values( $recipients );
        $this->subject    = $subject;
        $this->html_body  = $html_body;
        $this->text_body  = $text_body;
        $this->headers    = array_values( $headers );
    }

    /** @return string[] */
    public function recipients(): array {
        return $this->recipients;
    }

    public function subject(): string {
        return $this->subject;
    }

    public function html_body(): string {
        return $this->html_body;
    }

    public function text_body(): string {
        return $this->text_body;
    }

    /** @return string[] */
    public function headers(): array {
        return $this->headers;
    }
}
