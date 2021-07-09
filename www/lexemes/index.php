<?php

require '../../inc/load.inc.php';

$title = (!empty($_GET['title']) ? htmlentities($_GET['title']).' — ' : '').'<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'">Lexemes Party</a>';
define('PAGE_TITLE', $title);

require '../../inc/header.inc.php';

define('WDQS_CACHE', 3600);
define('LANGUAGE_REGEX', '[a-z]{2,3}(-[a-z]+){0,2}|simple');
$errors = array();

// filters
$languages_filter = array();
if (!empty($_GET['languages_filter'])) {
    preg_match_all('/'.LANGUAGE_REGEX.'/', $_GET['languages_filter'], $matches);
    $languages_filter = $matches[0];
}
$languages_filter_action = 'block';
if (!empty($_GET['languages_filter_action']) && ($_GET['languages_filter_action'] === 'allow')) {
    $languages_filter_action = 'allow';
}

// table direction
$languages_direction = 'rows';
if (!empty($_GET['languages_direction']) && ($_GET['languages_direction'] === 'columns')) {
    $languages_direction = 'columns';
}

// language display
$language_display = 'en';
if (preg_match('/^'.LANGUAGE_REGEX.'$/', $_GET['language_display'])) {
    $language_display = $_GET['language_display'];
}

if (!empty($_GET['query'])) {
    
    // input query
    $results = wdqs::query($_GET['query'], WDQS_CACHE);
    if (count($results->results->bindings) === 0) {
        $errors[] = 'The input query returned no result.';
    }
    else {
        
        $concepts = array();
         foreach ($results->results->bindings as $item) {
            $concepts[] = substr($item->concept->value, 31);
        }
        
        $concepts_meta = array();
        $items = wdqs::query('SELECT ?concept ?conceptLabel ?url ?title {
  hint:Query hint:optimizer "None" .
  VALUES ?concept { wd:'.implode(' wd:', $concepts).' } .
  OPTIONAL { ?concept rdfs:label ?conceptLabel . FILTER(LANG(?conceptLabel) = "'.$language_display.'") }
  OPTIONAL { ?url schema:about ?concept ; schema:inLanguage "'.$language_display.'" ; schema:name ?name ; schema:isPartOf [ wikibase:wikiGroup "wikipedia" ] ; schema:name ?title }
}', WDQS_CACHE)->results->bindings;
        foreach ($items as $item) {
            $concepts_meta[substr($item->concept->value, 31)] = (object) array('label' => $item->conceptLabel->value, 'wikipedia_url' => $item->url->value, 'wikipedia_title' => $item->title->value);
        }
        
        // filters query optimization (also filtered later)
        // TODO: handle languages without codes
        $filter = '';
        if (($languages_filter_action == 'allow') && (!empty($languages_filter))) {
            $filter = '?language wdt:P424 ?code . VALUES ?code { "'.implode('" "', $languages_filter).'" }';
        } elseif (($languages_filter_action == 'block') && (!empty($languages_filter))) {
            $filter = 'FILTER NOT EXISTS { ?language wdt:P424 ?code . VALUES ?code { "'.implode('" "', $languages_filter).'" } }';
        }
        
        $items = wdqs::query('SELECT * {
  hint:Query hint:optimizer "None" .
  ?sense wdt:P5137 ?concept .
  VALUES ?concept { wd:'.implode(' wd:', $concepts).' }
  [] ontolex:sense ?sense ; wikibase:lemma ?lemma ; dct:language ?language .
  '.$filter.'
}', WDQS_CACHE)->results->bindings;
    
        if (count($items) === 0) {
            $errors[] = 'No lexeme found :(';
        }
        else {
            
            // $languages initilization
            $languages = array();
            foreach ($items as $item) {
                $language_qid = substr($item->language->value, 31);
                if (!isset($languages[$language_qid])) {
                    $l = fetch_language($language_qid, $language_display);
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
            
            // sort languages by label
            usort($languages, function($a, $b) {
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
            // sorting
            foreach ($languages as $language) {
                foreach ($concepts as $concept) {
                    usort($senses[$concept][$language->qid], function($a, $b) {
                        return $a->lemma <=> $b->lemma;
                    });
                }
            }
            
            // scores
            $medals = array('gold' => 0, 'silver' => 0, 'bronze' => 0, '' => 0);
            foreach ($languages as $language) {
                $sum = 0;
                foreach ($concepts as $concept) {
                    if (count($senses[$concept][$language->qid]) >= 1) {
                        $sum++;
                    }
                }
                $language->score = 100.0 * $sum / count($concepts);
                if ($language->score === 100.0) {
                    $language->medal = 'gold';
                } elseif ($language->score >= 80) {
                    $language->medal = 'silver';
                } elseif ($language->score >= 50) {
                    $language->medal = 'bronze';
                } else {
                    $language->medal = '';
                }
                $medals[$language->medal]++;
            }
            
        }
        
    }

}

// form
echo '<h2>Query</h2>
<form action="'.SITE_DIR.LEXEMES_SITE_DIR.'" method="get">
<p><label for="title">Title (optional, useful to share your party):</label><br /><input type="text" id="title" name="title" style="width: 40%;" value="'.htmlentities(@$_GET['title']).'" /></p>
<p><label for="query">A SPARQL query that returns a variable named <code>?concept</code> representing Wikidata items:</label><br /><textarea id="query" name="query" style="width: 50%; max-width: 50%;">'.htmlentities(@$_GET['query']).'</textarea></p>
<p><label for="languages_filter">Languages filter (optional, codes from <a href="https://www.wikidata.org/wiki/Property:P424">P424</a>, values separated by a space)</label> to <input type="radio" id="languages_allow" name="languages_filter_action" value="allow" '.(($languages_filter_action === 'allow') ? 'checked="checked" ' : '').'/> <label for="languages_allow">allow</label> <input type="radio" id="languages_block" name="languages_filter_action" value="block" '.(($languages_filter_action === 'block') ? 'checked="checked" ' : '').'/> <label for="languages_block">block</label>:<br /><input type="text" id="languages_filter" name="languages_filter" style="width: 40%;" value="'.(isset($_GET['languages_filter']) ? htmlentities($_GET['languages_filter']) : '').'" /></p>
<p>Display:
<br /><input type="radio" id="languages_rows" name="languages_direction" value="rows" '.(($languages_direction === 'rows') ? 'checked="checked" ' : '').'/> <label for="languages_rows">languages in rows, concepts in columns (best for high number of languages)</label>
<br /><input type="radio" id="languages_columns" name="languages_direction" value="columns" '.(($languages_direction === 'columns') ? 'checked="checked" ' : '').'/> <label for="languages_columns">languages in columns, concepts in rows (best for high number of concepts)</label></p>
<p><label for="language_display">Language:</label><br /><input type="text" id="language_display" name="language_display" style="width: 100px;" value="'.htmlentities($language_display).'" /></p>
<p><input type="submit" value="Search" /></p>
<p>Examples: <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?title=Colors+of+the+rainbow+flag&query=SELECT+%3Fconcept+{+wd%3AQ51401+p%3AP462+[+rdf%3Atype+wikibase%3ABestRank+%3B+ps%3AP462+%3Fconcept+%3B+pq%3AP1545+%3Frank+]+}+ORDER+BY+xsd%3Ainteger(%3Frank)&languages_filter_action=block&languages_filter=&languages_direction=rows">colors of the rainbow flag</a>, <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?title=Planets+of+the+Solar+System&query=SELECT+%3Fconcept+{+VALUES+%3Fconcept+{+wd%3AQ308+wd%3AQ313+wd%3AQ2+wd%3AQ111+wd%3AQ319+wd%3AQ193+wd%3AQ324+wd%3AQ332+}+}&languages_filter_action=block&languages_filter=&languages_direction=rows">planets of the Solar System</a>, <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?title=Animals&query=SELECT+DISTINCT+%3Fconcept+{+%3Fconcept+wdt%3AP31%3F%2Fwdt%3AP279*+wd%3AQ729+%3B+wikibase%3Asitelinks+%3Fsitelinks+}+ORDER+BY+DESC(%3Fsitelinks)+LIMIT+50&languages_filter_action=allow&languages_filter=de+en+fr&languages_direction=columns">animals</a>, <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'?title=Focus+languages&query=SELECT+%3Fconcept+{+VALUES+%3Fconcept+{+wd%3AQ9610+wd%3AQ36236+wd%3AQ56475+wd%3AQ33578+wd%3AQ32238+wd%3AQ1860+}+}&languages_filter_action=allow&languages_filter=bn+ml+ha+ig+dag+en&languages_direction=columns">focus languages</a>.</p>
</form>';

// display main table
if (!empty($senses)) {
    echo '<h2>Results</h2>
<table id="lexemes">';
    // TODO: clean this code ^^
    if ($languages_direction == 'rows') {
        echo '<tr><th colspan="2">Wikidata</th>';
        foreach ($concepts as $concept) {
            echo '<th>'.htmlentities($concepts_meta[$concept]->label).' (<a href="https://www.wikidata.org/wiki/'.$concept.'">'.$concept.'</a>)';
            if (!empty($concepts_meta[$concept]->label)) {
                echo '<br /><a href="https://www.wikidata.org/w/index.php?search='.rawurlencode($concepts_meta[$concept]->label).'&title=Special%3ASearch&profile=advanced&fulltext=1&ns146=1"><img src="'.SITE_STATIC_DIR.'img/search.png" title="Search in Wikidata Lexemes" class="logo" /></a> <a href="https://'.$language_display.'.wiktionary.org/wiki/'.rawurlencode($concepts_meta[$concept]->label).'"><img src="'.SITE_STATIC_DIR.'img/logo-wiktionary.png" title="Search in Wiktionary" class="logo" /></a>';
            }
            echo '</th>';
        }
        echo '</tr><tr><th colspan="2">Wikipedia</th>';
        foreach ($concepts as $concept) {
            echo '<td class="wikipedia">';
            if (!empty($concepts_meta[$concept]->wikipedia_url)) {
                echo '<a href="'.$concepts_meta[$concept]->wikipedia_url.'">'.htmlentities($concepts_meta[$concept]->wikipedia_title).'</a>';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($languages as $language) {
            echo '<tr><td title="'.round($language->score).'%">';
            if (!empty($language->medal)) {
                echo ' <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_'.$language->medal.'_3.png" alt="" />';
            }
            echo '</td><td title="'.round($language->score).'%"><a href="https://www.wikidata.org/wiki/'.$language->qid.'">';
            if ($language->code !== '∅') {
                echo '<span class="language">['.htmlentities($language->code).']</span> ';
            }
            echo htmlentities($language->label).'</a></td>';
            foreach ($concepts as $concept) {
                echo '<td>';
                foreach ($senses[$concept][$language->qid] as $sense) {
                    echo '<a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense->sense).'">'.htmlentities($sense->lemma).'</a> ('.$sense->sense.')<br />';
                }
                echo '</td>';
            }
            echo '</tr>'."\n";
        }
    }
    elseif ($languages_direction == 'columns') {
        echo '<tr><th colspan="3">Wikidata</th><th>Wikipedia</th>';
        foreach ($languages as $language) {
            echo '<th class="column"><a href="https://www.wikidata.org/wiki/'.$language->qid.'">';
            if ($language->code !== '∅') {
                echo '<span class="language">['.htmlentities($language->code).']</span> ';
            }
            echo htmlentities($language->label).'</a><br />';
            if (!empty($language->medal)) {
                echo '<img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_'.$language->medal.'_3.png" alt="" /> ';
            }
            echo '<span class="score">'.round($language->score).'%</span></th>';
        }
        echo '</tr>';
        foreach ($concepts as $concept) {
            echo '<tr><td><a href="https://www.wikidata.org/wiki/'.$concept.'">'.$concept.'</a></td><td>'.htmlentities($concepts_meta[$concept]->label).'</td><td>';
            if (!empty($concepts_meta[$concept]->label)) {
                echo '<a href="https://www.wikidata.org/w/index.php?search='.rawurlencode($concepts_meta[$concept]->label).'&title=Special%3ASearch&profile=advanced&fulltext=1&ns146=1"><img src="'.SITE_STATIC_DIR.'img/search.png" title="Search in Wikidata Lexemes" class="logo" /></a> <a href="https://'.$language_display.'.wiktionary.org/wiki/'.rawurlencode($concepts_meta[$concept]->label).'"><img src="'.SITE_STATIC_DIR.'img/logo-wiktionary.png" title="Search in Wiktionary" class="logo" /></a>';
            }
            echo '</td><td>';
            if (!empty($concepts_meta[$concept]->wikipedia_url) && !empty($concepts_meta[$concept]->wikipedia_title)) {
                echo '<a href="'.$concepts_meta[$concept]->wikipedia_url.'">'.htmlentities($concepts_meta[$concept]->wikipedia_title).'</a>';
            }
            echo '</td>';
            foreach ($languages as $language) {
                echo '<td>';
                foreach ($senses[$concept][$language->qid] as $sense) {
                    echo '<a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense->sense).'">'.htmlentities($sense->lemma).'</a> ('.$sense->sense.')<br />';
                }
                echo '</td>';
            }
            echo '</tr>'."\n";
        }
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

function fetch_language($qid, $language_display) {
    $items = wdqs::query('SELECT ?code ?label {
  OPTIONAL { wd:'.$qid.' wdt:P424 ?code }
  OPTIONAL { wd:'.$qid.' rdfs:label ?label . FILTER(LANG(?label) = "'.$language_display.'") }
}', WDQS_CACHE)->results->bindings;
    if (count($items) > 1) {
        return false;
    }
    $item = $items[0];
    $r = (object) array('qid' => $qid, 'code' => @$item->code->value, 'label' => $item->label->value);
    if ($r->code === null) {
        $r->code = '∅';
    }
    return $r;
}

?>