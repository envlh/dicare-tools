<?php

require '../../inc/load.inc.php';

$title = '<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'">Lexemes Party</a>';

define('PAGE_TITLE', $title);

require '../../inc/header.inc.php';

define('WDQS_CACHE', 3600);
define('CONCEPTS_MAX_COUNT', 30);
$errors = array();

/*

# examples

# seven deadly sins
SELECT ?concept { wd:Q166502 wdt:P527 ?concept }

# days of week (ISO 8601)
SELECT ?concept { ?concept wdt:P31 wd:Q41825 ; p:P1545 [ rdf:type wikibase:BestRank ; ps:P1545 ?rank ; pq:P1013 wd:Q50101 ] } ORDER BY xsd:integer(?rank)

# months of year (ISO 8601)
SELECT ?concept { ?concept wdt:P31 wd:Q47018901 ; p:P1545 [ rdf:type wikibase:BestRank ; ps:P1545 ?rank ; pq:P1013 wd:Q50101 ] } ORDER BY xsd:integer(?rank)

# colors of the rainbow flag
SELECT ?concept { wd:Q51401 p:P462 [ rdf:type wikibase:BestRank ; ps:P462 ?concept ; pq:P1545 ?rank ] } ORDER BY xsd:integer(?rank)

*/

if (!empty($_GET['query'])) {
    $results = wdqs::query($_GET['query'], WDQS_CACHE);
    if (count($results->results->bindings) >= 1) {
        $concepts = array();
         foreach ($results->results->bindings as $item) {
            $concepts[] = substr($item->concept->value, 31);
        }
        $concepts = array_slice($concepts, 0, CONCEPTS_MAX_COUNT);
    } else {
        $errors = 'The query returned no result.';
    }
}

if (!empty($concepts)) {
    
    $languages = array();
    $senses = array();
    
    $items = wdqs::query('SELECT * {
      ?sense wdt:P5137 ?concept .
      VALUES ?concept { wd:'.implode(' wd:', $concepts).' }
      ?lexeme ontolex:sense ?sense ; wikibase:lemma ?lemma ; dct:language ?language .
    }', WDQS_CACHE)->results->bindings;
    
    // $languages initilization
    foreach ($items as $item) {
        $language_qid = substr($item->language->value, 31);
        // TODO: add an option to display British Sign Language (Q33000)
        if (!isset($languages[$language_qid]) && ($language_qid != 'Q33000')) {
            $l = get_language($language_qid);
            if ($l !== false) {
                $languages[$language_qid] = $l;
            } else {
                $sense = substr($item->sense->value, 31);
                // TODO: group errors by language
                $errors[] = 'Missing or multiple <a href="https://www.wikidata.org/wiki/Property:P424">P424</a> values for language <a href="https://www.wikidata.org/wiki/'.$language_qid.'">'.$language_qid.'</a> used in <a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense).'">'.$sense.'</a>.';
            }
        }
    }
    // sort languages by code
    usort($languages, function($a, $b) {
        return $a->code <=> $b->code;
    });
    
    // $senses initilization
    foreach ($concepts as $concept) {
        foreach ($languages as $language) {
            $senses[$concept][$language->qid] = array();
        }
    }
    foreach ($items as $item) {
        $concept_qid = substr($item->concept->value, 31);
        $language_qid = substr($item->language->value, 31);
        if (isset($senses[$concept_qid][$language_qid])) {
            $senses[$concept_qid][$language_qid][] = (object) array('sense' => substr($item->sense->value, 31), 'lemma' => $item->lemma->value);
        }
    }
    
}

// form
echo '<h2>Query</h2>
<form action="'.SITE_DIR.LEXEMES_SITE_DIR.'" method="get">
<p><label for="query">A SPARQL query that returns a variable named <code>?concept</code> representing Wikidata items (<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?query=SELECT+%3Fconcept+{+wd%3AQ166502+wdt%3AP527+%3Fconcept+}">example</a>):</label><br /><textarea id="query" name="query" style="width: 50%; max-width: 50%;">'.htmlentities(@$_GET['query']).'</textarea><br /><input type="submit" value="Search" /></p>
</form>';

// main table
if (!empty($senses)) {
    echo '<h2>Results</h2>
    <table id="lexemes">
    <tr><th></th>';
    foreach ($concepts as $concept) {
        echo '<th><a href="https://www.wikidata.org/wiki/'.$concept.'">'.$concept.'</a></th>';
    }
    echo '</tr>';
    // TODO: row with Wikipedia pages
    foreach ($languages as $language) {
        echo '<tr><th class="row"><a href="https://www.wikidata.org/wiki/'.$language->qid.'"><span class="language">['.htmlentities($language->code).']</span> '.htmlentities($language->label).'</a></th>';
        foreach ($concepts as $concept) {
            echo '<td>';
            // sort senses by lemma
            usort($senses[$concept][$language->qid], function($a, $b) {
                return $a->lemma <=> $b->lemma;
            });
            foreach ($senses[$concept][$language->qid] as $sense) {
                echo '<a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense->sense).'">'.htmlentities($sense->lemma).'</a> ('.$sense->sense.')<br />';
            }
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
}

// errors display
if (!empty($errors)) {
    echo '<h2>Errors</h2>
<ul>';
    $errors = array_unique($errors);
    sort($errors);
    foreach ($errors as $error) {
        echo '<li>'.$error.'</li>';
    }
    echo '</ul>';
}

require '../../inc/footer.inc.php';

function get_language($qid) {
    $items = wdqs::query('SELECT ?code ?label {
  wd:'.$qid.' wdt:P424 ?code .
  OPTIONAL {
    wd:'.$qid.' rdfs:label ?label .
    FILTER(LANG(?label) = "en") .
  }
}', WDQS_CACHE)->results->bindings;
    // TODO: handle languages without a code
    if (count($items) !== 1) {
        return false;
    }
    $item = $items[0];
    return (object) array('qid' => $qid, 'code' => $item->code->value, 'label' => $item->label->value);
}

?>