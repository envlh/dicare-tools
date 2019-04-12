<?php

class wdqs extends sparql {
    
    const ENDPOINT_ID = 'wdqs';
    
    private static $queries = array();
    
    public static function query($query, $cache = 300) {
        self::$queries[] = $query;
        return parent::a_query('https://query.wikidata.org/sparql', self::ENDPOINT_ID, $query, $cache);
    }
    
    public static function displayQueries($lang = 'en') {
        $queries = self::getQueries();
        if (count($queries) >= 1) {
            switch ($lang) {
                case 'fr':
                    echo '<h2>Requête'.((count($queries) >= 2) ? 's' : '').' SPARQL</h2>';
                break;
                default:
                    echo '<h2>SPARQL quer'.((count($queries) >= 2) ? 'ies' : 'y').'</h2>';
            }
            $i = 0;
            foreach ($queries as $query) {
                echo '<h3>#'.(++$i).' [<a href="https://query.wikidata.org/#'.rawurlencode($query).'">WDQS</a>]</h3><pre>'.htmlentities(trim($query)).'</pre>';
            }
        }
    }
    
    public static function getQueryTime($query) {
        return parent::a_getQueryTime(self::ENDPOINT_ID, $query);
    }
    
    public static function getQueries() {
        return self::$queries;
    }
    
}

?>