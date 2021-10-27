<?php

namespace PKeidel\Laralog\Enrichers;

class SqlFilledQueryEnricher implements ILaralogEnricher {

    /**
     * Takes the prepared query and fills in the values for the params
     * @param array $data
     */
    public function enrichFrom(array &$data): void {
        $strParams = [];

        $sql = $data['sql'];
        $params = $data['bindingsorig'];

        while(count($params) > 0) {
            $v = array_shift($params);
            if(is_string($v))
                $v = "'$v'";
            elseif(is_object($v) && method_exists($v, 'format')) {
                /** @var \DateTime|\Carbon\Carbon $v */
                $v = "'".$v->format('Y-m-d H:i:s')."'";
            }
            $strParams[] = trim((string) $v, "\"'");
            $sql = implode($v, explode('?', $sql, 2));
        }

        $sql = strtolower($sql);
        foreach(['select ','from ','insert into ','update ','delete ',' and ',' or ',' as ',' is ','null','left join ','where ','group by ','order by ','limit '] as $keyword) {
            $sql = preg_replace("/$keyword/", strtoupper($keyword), $sql);
        }

        $data['enriched'] ??= [];
        $data['enriched']['filledQuery'] ??= [];
        $data['enriched']['filledQuery']['query'] = $sql;
        $data['enriched']['filledQuery']['bindings'] = $strParams;
        $data['enriched']['filledQuery']['bindingsCount'] = count($strParams);
    }
}
