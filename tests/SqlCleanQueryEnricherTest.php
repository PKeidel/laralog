<?php

namespace PKeidel\Laralog\Tests;

use PHPUnit\Framework\TestCase;
use PKeidel\Laralog\Enrichers\SqlCleanQueryEnricher;

class SqlCleanQueryEnricherTest extends TestCase {

    private function run_enricher($sql) {
        $scqe = new SqlCleanQueryEnricher();
        $arr = $scqe->enrichFrom(['sql' => $sql]);
        return $arr['enriched']['cleanQuery']['query'];
    }

    /** @test */
    public function sql_clean_query_enricher_replace_multiple_questionmarks() {
        $this->assertEquals(
            'SELECT * FROM users WHERE id in (?)',
            $this->run_enricher('Select * from users where id IN (?, ?, ?)'),
        );
    }

    /** @test */
    public function sql_clean_query_enricher_replace_correct_case() {
        $this->assertEquals(
            'SELECT * FROM users WHERE id in (?)',
            $this->run_enricher('seLEcT * FRoM users Where id In (?, ?, ?)'),
        );
    }

    /** @test */
    public function sql_clean_query_enricher_replace_number() {
        $this->assertEquals(
            'SELECT * FROM users WHERE id = ?',
            $this->run_enricher('Select * from users where id = 43.12'),
        );
    }

    /** @test */
    public function sql_clean_query_enricher_replace_number_in_quotes() {
        $this->assertEquals(
            'SELECT * FROM users WHERE id = ?',
            $this->run_enricher('Select * from users where id = \'43\''),
        );
    }

    /** @test */
    public function sql_clean_query_enricher_replace_numbers() {
        $this->assertEquals(
            'SELECT * FROM users WHERE id in (?)',
            $this->run_enricher('Select * from users where id IN (1, 53, 127)'),
        );
    }

    /** @test */
    public function sql_clean_query_enricher_do_not_replace_dot() {
        $this->assertEquals(
            'SELECT * FROM "users" WHERE "id" = ? AND "users"."deleted_at" IS NULL LIMIT ?',
            $this->run_enricher('SELECT * FROM "users" WHERE "id" = 1.2 AND "users"."deleted_at" IS NULL LIMIT 1'),
        );
    }

    /** @test */
    public function sql_clean_query_enricher_do_not_replace_dot2() {
        $this->assertEquals(
            'SELECT "addresses".*, "addressables"."addressable_id" AS "pivot_addressable_id" FROM "addresses" inner join "addressables" on "addresses"."id" = "addressables"."addresses_id" WHERE "addressables"."addressable_id" in (?) AND "addressables"."addressable_type" = ?',
            $this->run_enricher('select "addresses".*, "addressables"."addressable_id" as "pivot_addressable_id" from "addresses" inner join "addressables" on "addresses"."id" = "addressables"."addresses_id" where "addressables"."addressable_id" in (1) and "addressables"."addressable_type" = ?'),
        );
    }
}
