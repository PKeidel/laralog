<?php

namespace PKeidel\Laralog\Tests;

use PHPUnit\Framework\TestCase;
use PKeidel\Laralog\Enrichers\SqlCleanQueryEnricher;

class SqlCleanQueryEnricherTest extends TestCase {

    private function run_enricher($sql) {
        $scqe = new SqlCleanQueryEnricher();
        $arr = $scqe->enrichFrom(['sql' => $sql]);
        return $arr['cleanQuery'];
    }

    /** @test */
    public function sql_clean_query_enricher_replace_multiple_questionmarks() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where id IN (?, ?, ?)'),
            'SELECT * FROM users WHERE id in (?)'
        );
    }

    /** @test */
    public function sql_clean_query_enricher_replace_correct_case() {
        $this->assertEquals(
            $this->run_enricher('seLEcT * FRoM users Where id In (?, ?, ?)'),
            'SELECT * FROM users WHERE id in (?)'
        );
    }

    /** @test */
    public function sql_clean_query_enricher_replace_number() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where id = 43'),
            'SELECT * FROM users WHERE id = ?'
        );
    }

    /** @test */
    public function sql_clean_query_enricher_replace_numbers() {
        $this->assertEquals(
            $this->run_enricher('Select * from users where id IN (1, 53, 127)'),
            'SELECT * FROM users WHERE id in (?)'
        );
    }
}
