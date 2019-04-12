<?php

class bnf extends sparql {
    
    const ENDPOINT_ID = 'bnf';
    
    public static function query($query, $cache = 86400) {
        return parent::a_query('https://data.bnf.fr/sparql', self::ENDPOINT_ID, $query, $cache);
    }
    
    public static function getQueryTime($query) {
        return parent::a_getQueryTime(self::ENDPOINT_ID, $query);
    }
    
}

?>