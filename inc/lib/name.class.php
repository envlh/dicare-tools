<?php

class name {
    
    public static function getStatsByDepartment($cache = 86400 * 30) {
        
        $departments = array();
        
        $query = 'SELECT ?departement ?insee ?label
WHERE {
    ?departement wdt:P2586 ?insee .
    ?departement rdfs:label ?label .
    FILTER (LANG(?label) = "fr")
}
ORDER BY ?insee';
        $items = wdqs::query($query, $cache);
        foreach ($items->results->bindings as $item) {
            $departments[$item->insee->value]['qid'] = substr($item->departement->value, 31);
            $departments[$item->insee->value]['label'] = $item->label->value;
        }
        
        foreach ($departments as $insee => &$value) {
            
            // total
            $query = 'SELECT (COUNT(DISTINCT ?item) AS ?count)
WHERE {
    ?item wdt:P31 wd:Q5 .
    ?item wdt:P19/wdt:P131* wd:'.$value['qid'].' .
}';
            $items = wdqs::query($query, $cache);
            $value['total'] = $items->results->bindings[0]->count->value;
            
            // lastname
            $query = 'SELECT (COUNT(DISTINCT ?item) AS ?count)
WHERE {
    ?item wdt:P31 wd:Q5 .
    ?item wdt:P734 ?anything .
    ?item wdt:P19/wdt:P131* wd:'.$value['qid'].' .
}';
            $items = wdqs::query($query, $cache);
            $value['lastname'] = $items->results->bindings[0]->count->value;
            
            // firstname
            $query = 'SELECT (COUNT(DISTINCT ?item) AS ?count)
WHERE {
    ?item wdt:P31 wd:Q5 .
    ?item wdt:P735 ?anything .
    ?item wdt:P19/wdt:P131* wd:'.$value['qid'].' .
}';
            $items = wdqs::query($query, $cache);
            $value['firstname'] = $items->results->bindings[0]->count->value;
            
        }
        
        return $departments;
        
    }
    
    public static function getStatsByCountry($cache = 86400 * 30) {
        
        $countries = array();
        
        $query = 'SELECT ?country ?label
WHERE {
    ?country wdt:P31 wd:Q6256 .
    ?country rdfs:label ?label .
    FILTER (LANG(?label) = "fr")
}
ORDER BY ?label';
        $items = wdqs::query($query, $cache);
        foreach ($items->results->bindings as $item) {
            $qid = substr($item->country->value, 31);
            $countries[$qid]['label'] = $item->label->value;
        }
        
        foreach ($countries as $qid => &$value) {
            
            // total
            $query = 'SELECT (COUNT(DISTINCT ?item) AS ?count)
WHERE {
    ?item wdt:P31 wd:Q5 .
    ?item wdt:P27 wd:'.$qid.' .
}';
            $items = wdqs::query($query, $cache);
            $value['total'] = $items->results->bindings[0]->count->value;
            
            // lastname
            $query = 'SELECT (COUNT(DISTINCT ?item) AS ?count)
WHERE {
    ?item wdt:P31 wd:Q5 .
    ?item wdt:P734 ?anything .
    ?item wdt:P27 wd:'.$qid.' .
}';
            $items = wdqs::query($query, $cache);
            $value['lastname'] = $items->results->bindings[0]->count->value;
            
            // firstname
            $query = 'SELECT (COUNT(DISTINCT ?item) AS ?count)
WHERE {
    ?item wdt:P31 wd:Q5 .
    ?item wdt:P735 ?anything .
    ?item wdt:P27 wd:'.$qid.' .
}';
            $items = wdqs::query($query, $cache);
            $value['firstname'] = $items->results->bindings[0]->count->value;
            
        }
        
        return $countries;

    }
    
}

?>