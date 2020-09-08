<?php

namespace PKeidel\Laralog\Tests;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use PKeidel\Laralog\Enrichers\SqlFilledQueryEnricher;

class SqlFilledQueryEnricherTest extends TestCase {

    private function run_enricher(string $sql, array $bindings = []) {
        $scqe = new SqlFilledQueryEnricher();
        return $scqe->enrichFrom(['sql' => $sql, 'bindingsorig' => $bindings])['enriched']['filledQuery'];
    }

    /** @test */
    public function sql_filled_query_enricher_replace_multiple_questionmarks() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where id IN (?, ?, ?)', $bindings = [1, 2, 54]), [
                'query' => 'SELECT * FROM users WHERE id in (1, 2, 54)',
                'bindings' => $bindings,
                'bindingsCount' => 3,
            ]
        );
    }

    /** @test */
    public function sql_filled_query_enricher_replace_correct_case() {
        $this->assertEquals(
            $this->run_enricher('seLEcT * FRoM users Where id In (?, ?, ?)', $bindings = [1, 53, 127]), [
                'query' => 'SELECT * FROM users WHERE id in (1, 53, 127)',
                'bindings' => $bindings,
                'bindingsCount' => 3,
            ]
        );
    }

    /** @test */
    public function sql_filled_query_enricher_replace_number() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where id = 43'), [
                'query' => 'SELECT * FROM users WHERE id = 43',
                'bindings' => [],
                'bindingsCount' => 0,
            ]
        );
    }

    /** @test */
    public function sql_filled_query_enricher_replace_numbers() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where id IN (1, 53, 127)'), [
                'query' => 'SELECT * FROM users WHERE id in (1, 53, 127)',
                'bindings' => [],
                'bindingsCount' => 0,
            ]
        );
    }

    /** @test */
    public function sql_filled_query_enricher_replace_date() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where last_login = ?', [Carbon::parse($date = '2020-01-02 13:37:42')]), [
                'query' => 'SELECT * FROM users WHERE last_login = \'2020-01-02 13:37:42\'',
                'bindings' => [$date],
                'bindingsCount' => 1,
            ]
        );
    }

    /** @test */
    public function sql_filled_query_enricher_replace_string() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where username = ?', [$str = 'pkeidel']), [
                'query' => "SELECT * FROM users WHERE username = '$str'",
                'bindings' => [$str],
                'bindingsCount' => 1,
            ]
        );
    }
}
