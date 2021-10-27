<?php

namespace PKeidel\Laralog\Enrichers;

class SqlCleanQueryEnricher implements ILaralogEnricher {

    /**
     * Cleans up an sql statement to be able to find similar ones in kibana.
     * Examples:
     *  - Select * from users where id = ?   =>  SELECT * FROM users WHERE id = ?
     *  - Select * from users where id IN (?, ?, ?)   =>  SELECT * FROM users WHERE id IN (?)
     * @param array $data
     */
    public function enrichFrom(array &$data): void {
        $query = preg_replace("/('[^']+')/", '?', $data['query']);   // replace strings
        $query = preg_replace("/([0-9][0-9.]+)/", '?', $query);    // replace decimal numbers
        $query = preg_replace("/([0-9]+)/", '?', $query);    // replace numbers
        $query = preg_replace("/[iI][nN] \((?:\?,? ?)+\)/", 'in (?)', $query);
        $query = strtolower($query);
        foreach(['select ','from ','insert into ','update ','delete ',' and ',' or ',' as ',' is ','null','left join ','where ','group by ','order by ','limit '] as $keyword) {
            $query = preg_replace("/$keyword/", strtoupper($keyword), $query);
        }

        $data['enriched'] ??= [];
        $data['enriched']['cleanQuery'] ??= [];
        $data['enriched']['cleanQuery']['query'] = $query;
    }
}
