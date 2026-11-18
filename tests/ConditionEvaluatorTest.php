<?php
/**
 * Unit tests for Condition_Evaluator. tests/js/condition.test.mjs checks the same fixtures.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

namespace JayPee\FormBuilder;

use PHPUnit\Framework\TestCase;

final class ConditionEvaluatorTest extends TestCase {

    /** @return array<string,mixed> */
    private static function fixtures(): array {
        return (array) json_decode( (string) file_get_contents( __DIR__ . '/fixtures/conditions.json' ), true );
    }

    /** @return array<string,array{array<string,mixed>}> */
    public function conditions(): array {
        $cases = [];
        foreach ( self::fixtures()['conditions'] as $fixture ) {
            $cases[ $fixture['name'] ] = [ $fixture ];
        }
        return $cases;
    }

    /** @return array<string,array{array<string,mixed>}> */
    public function groups(): array {
        $cases = [];
        foreach ( self::fixtures()['groups'] as $fixture ) {
            $cases[ $fixture['name'] ] = [ $fixture ];
        }
        return $cases;
    }

    /**
     * @dataProvider conditions
     * @param array<string,mixed> $fixture Fixture entry.
     */
    public function test_condition_matches_the_fixture( array $fixture ): void {
        $condition = new Condition( 'source', $fixture['operator'], $fixture['values'] );

        self::assertSame( $fixture['expected'], ( new Condition_Evaluator() )->is_met( $condition, [ 'source' => $fixture['value'] ] ) );
    }

    public function test_the_fixture_covers_every_operator(): void {
        $covered = array_unique( array_column( self::fixtures()['conditions'], 'operator' ) );
        sort( $covered );
        $operators = Condition::OPERATORS;
        sort( $operators );

        self::assertSame( $operators, $covered );
    }

    /**
     * @dataProvider groups
     * @param array<string,mixed> $fixture Fixture entry.
     */
    public function test_groups_resolve_as_the_fixture_says( array $fixture ): void {
        $groups = [];
        foreach ( $fixture['groups'] as $entry ) {
            $condition = null === $entry['condition']
                ? new Condition( '', 'is', [], false )
                : new Condition( $entry['condition']['sourceField'], $entry['condition']['operator'], $entry['condition']['values'] );
            $fields    = array_map( static fn( string $key ): Field_Definition => new Field_Definition( 'text', $key, [] ), $entry['fields'] );
            $groups[]  = new Conditional_Group( $condition, $fields );
        }

        $result = ( new Condition_Evaluator() )->resolve( $groups, $fixture['values'] );

        self::assertSame( $fixture['shown'], $result['shown'] );
        self::assertSame( $fixture['hidden'], $result['hidden'] );
        self::assertEquals( $fixture['visibleValues'], $result['values'] );
    }

    public function test_an_unknown_operator_never_holds(): void {
        self::assertFalse( ( new Condition_Evaluator() )->is_met( new Condition( 'a', 'matches', [ 'x' ] ), [ 'a' => 'x' ] ) );
    }

    public function test_the_condition_serialises_to_the_data_condition_format(): void {
        self::assertSame(
            '{"sourceField":"topic","operator":"is_one_of","values":["a","b"]}',
            (string) json_encode( ( new Condition( 'topic', 'is_one_of', [ 'a', 'b' ] ) )->to_array() )
        );
    }
}
