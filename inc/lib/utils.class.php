<?php

class utils {
    
    public static function date2qs($date) {
        // TODO handle more date formats
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
            return '+'.$date.'T00:00:00Z/11';
        }
        elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $date)) {
            return '+'.$date.'-01T00:00:00Z/10';
        }
        elseif (preg_match('/^[0-9]{4}$/', $date)) {
            return '+'.$date.'-01-01T00:00:00Z/9';
        }
        return null;
    }
    
    public static function checkUsage($property, $propertyLabel, $value) {
        $qid = null;
        if (!empty($value) && ($qid == null)) {
            $res = wdqs::query('SELECT DISTINCT ?item { ?item wdt:'.$property.' \''.$value.'\' ; wikibase:sitelinks ?sitelinks ; wikibase:statements ?statements } ORDER BY DESC(?sitelinks) DESC(?statements)', 300)->results->bindings;
            if (count($res) >= 1) {
                $qids = array();
                foreach ($res as $item) {
                    $qid = substr($item->item->value, 31);
                    $qids[] = '<a href="https://www.wikidata.org/wiki/'.$qid.'">'.$qid.'</a>';
                }
                echo '<p><strong style="color: red;">Warning: there is already one or more Wikidata items with this '.$propertyLabel.' ('.implode(', ', $qids).').</strong></p>';
                $qid = substr($res[0]->item->value, 31);
            }
        }
        return $qid;
    }
    
}

?>