<?php

namespace PKeidel\laralog\src\Enrichers;


class SqlFilledQueryEnricher implements ILaralogEnricher {

    /**
     * Takes the prepared query and fills in the values for the params
     * @param array $data
     * @return array
     */
    public function enrichFrom(array $data): array {
        $strParams = [];

        $sql = $data['sql'];
        $params = $data['bindingsorig'];

        while(count($params) > 0) {
            $v = array_shift($params);
            if(is_string($v))
                $v = "'$v'";
            elseif($v !== NULL && !is_numeric($v) && !is_bool($v) && get_class($v) === \DateTime::class) {
                /** @var \DateTime $v */
                $v = "'".$v->format('Y-m-d H:i:s')."'";
            }
            $strParams[] = trim((string) $v, "\"'");
            $sql = implode($v, explode('?', $sql, 2));
        }

        $sql = strtolower($sql);
        foreach(['select ','from ','insert into ','update ','delete ',' and ',' or ',' as ',' is ','null','left join ','where ','group by ','order by ','limit '] as $keyword) {
            $sql = preg_replace("/$keyword/", strtoupper($keyword), $sql);
        }

        return [
            'query' => $sql,
            'bindings' => $strParams,
            'bindingsCount' => count($strParams),
        ];
    }
}
