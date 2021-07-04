<?php

require '../../inc/load.inc.php';

$title = (!empty($_GET['title']) ? htmlentities($_GET['title']).' — ' : '').'<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'">Lexemes Party</a>';

define('PAGE_TITLE', $title);

require '../../inc/header.inc.php';

define('WDQS_CACHE', 3600);
define('CONCEPTS_MAX_COUNT', 30);
$errors = array();

$languages_filter = array();
if (!empty($_GET['languages_filter'])) {
    $languages_filter = explode(' ', $_GET['languages_filter']);
}
$languages_filter_action = 'block';
if (!empty($_GET['languages_filter_action']) && ($_GET['languages_filter_action'] === 'allow')) {
    $languages_filter_action = 'allow';
}
    
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

# semantic field about books
SELECT ?concept { VALUES ?concept { wd:Q571 wd:Q1069725 wd:Q1065044 wd:Q7075 wd:Q200764 wd:Q36279 wd:Q36180 } }

# quarter-finalists at UEFA Euro 2020
SELECT ?concept { VALUES ?concept { wd:Q31 wd:Q38 wd:Q39 wd:Q29 wd:Q212 wd:Q21 wd:Q213 wd:Q35 } }

# diseases
SELECT DISTINCT ?concept ?sitelinks { ?concept wdt:P31/wdt:P279 wd:Q12136 ; wikibase:sitelinks ?sitelinks } ORDER BY DESC(?sitelinks) LIMIT 8

# planets of the Solar System (unordered and ordered)
SELECT DISTINCT ?concept { ?concept wdt:P31/wdt:P279* wd:Q13205267 }
SELECT ?concept { VALUES ?concept { wd:Q308 wd:Q313 wd:Q2 wd:Q111 wd:Q319 wd:Q193 wd:Q324 wd:Q332 } }

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
        $errors[] = 'The query returned no result.';
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
        if (!isset($languages[$language_qid])) {
            $l = get_language($language_qid);
            if ($l !== false) {
                if ((($languages_filter_action === 'allow') && in_array($l->code, $languages_filter))
                    || (($languages_filter_action === 'block') && !in_array($l->code, $languages_filter))) {
                    $languages[$language_qid] = $l;
                }
            } else {
                $sense = substr($item->sense->value, 31);
                // TODO: group errors by language
                $errors[] = 'Multiple <a href="https://www.wikidata.org/wiki/Property:P424">P424</a> values for language <a href="https://www.wikidata.org/wiki/'.$language_qid.'">'.$language_qid.'</a> used in <a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense).'">'.$sense.'</a>.';
            }
        }
    }
    // sort languages by code
    usort($languages, function($a, $b) {
        $code = $a->code <=> $b->code;
        if ($code !== 0) {
            return $code;
        }
        return $a->label <=> $b->label;
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
<p><label for="title">Title (optional):</label><br /><input type="text" id="title" name="title" style="width: 40%;" value="'.htmlentities(@$_GET['title']).'" /></p>
<p><label for="query">A SPARQL query that returns a variable named <code>?concept</code> representing Wikidata items (examples: <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?title=Colors+of+the+rainbow+flag&query=SELECT+%3Fconcept+{+wd%3AQ51401+p%3AP462+[+rdf%3Atype+wikibase%3ABestRank+%3B+ps%3AP462+%3Fconcept+%3B+pq%3AP1545+%3Frank+]+}+ORDER+BY+xsd%3Ainteger(%3Frank)&languages_filter_action=block&languages_filter=">colors of the rainbow flag</a>, <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?title=Planets+of+the+Solar+System&query=SELECT+%3Fconcept+{+VALUES+%3Fconcept+{+wd%3AQ308+wd%3AQ313+wd%3AQ2+wd%3AQ111+wd%3AQ319+wd%3AQ193+wd%3AQ324+wd%3AQ332+}+}&languages_filter_action=block&languages_filter=">planets of the Solar System</a>, <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?title=Planets+of+the+Solar+System&query=SELECT+%3Fconcept+{+VALUES+%3Fconcept+{+wd%3AQ308+wd%3AQ313+wd%3AQ2+wd%3AQ111+wd%3AQ319+wd%3AQ193+wd%3AQ324+wd%3AQ332+}+}&languages_filter_action=allow&languages_filter=bn+ml+ha+ig+dag+en">focus languages</a>):</label><br /><textarea id="query" name="query" style="width: 50%; max-width: 50%;">'.htmlentities(@$_GET['query']).'</textarea></p>
<p><label for="languages_filter">Languages filter (optional, codes from <a href="https://www.wikidata.org/wiki/Property:P424">P424</a>, &#8709; for languages with no code, values separated by a space)</label> to <input type="radio" id="languages_allow" name="languages_filter_action" value="allow" '.(($languages_filter_action === 'allow') ? 'checked="checked" ' : '').'/> <label for="languages_allow">allow</label> <input type="radio" id="languages_block" name="languages_filter_action" value="block" '.(($languages_filter_action === 'block') ? 'checked="checked" ' : '').'/> <label for="languages_block">block</label>:<br /><input type="text" id="languages_filter" name="languages_filter" style="width: 40%;" value="'.(isset($_GET['languages_filter']) ? htmlentities($_GET['languages_filter']) : '').'" /></p>
<p><input type="submit" value="Search" /></p>
</form>';

// main table
if (!empty($senses)) {
    $medals = array('gold' => 0, 'silver' => 0, 'bronze' => 0, '' => 0);
    echo '<h2>Results</h2>
    <table id="lexemes">
    <tr><td class="medal"></td><th></th>';
    foreach ($concepts as $concept) {
        echo '<th><a href="https://www.wikidata.org/wiki/'.$concept.'">'.$concept.'</a></th>';
    }
    echo '</tr>';
    // TODO: row with Wikipedia pages
    foreach ($languages as $language) {
        $medal = '';
        $score = 0;
        foreach ($concepts as $concept) {
            if (count($senses[$concept][$language->qid]) >= 1) {
                $score++;
            }
        }
        $score = 100.0 * $score / count($concepts);
        if ($score === 100.0) {
            $medal = 'gold';
        } elseif ($score >= 80) {
            $medal = 'silver';
        } elseif ($score >= 50) {
            $medal = 'bronze';
        }
        $medals[$medal]++;
        echo '<tr><td class="medal" title="'.round($score).'%">';
        if (!empty($medal)) {
            echo '<img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_'.$medal.'_3.png" alt="" />';
        }
        echo '</td><th class="row"><a href="https://www.wikidata.org/wiki/'.$language->qid.'">';
        if ($language->code != '∅') {
            echo '<span class="language">['.htmlentities($language->code).']</span> ';
        }
        echo htmlentities($language->label).'</a></th>';
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
        echo '</tr>'."\n";
    }
    echo '</table>
<p>'.count($languages).' languages
&nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_gold_3.png" alt="" class="medal" title="100%" /> '.$medals['gold'].'
&nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_silver_3.png" alt="" class="medal" title="80%+" /> '.$medals['silver'].'
&nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_bronze_3.png" alt="" class="medal" title="50%+" /> '.$medals['bronze'].'
&nbsp;&nbsp;&nbsp;&nbsp; &#8709; '.$medals[''];
    echo '</p>';
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
  OPTIONAL { wd:'.$qid.' wdt:P424 ?code }
  OPTIONAL {
    wd:'.$qid.' rdfs:label ?label .
    FILTER(LANG(?label) = "en") .
  }
}', WDQS_CACHE)->results->bindings;
    if (count($items) > 1) {
        return false;
    }
    $item = $items[0];
    $r = (object) array('qid' => $qid, 'code' => $item->code->value, 'label' => $item->label->value);
    if ($r->code == '') {
        $r->code = '∅';
    }
    return $r;
}

?>