<?php

define('WDQS_CACHE', 3600);
define('LANGUAGE_REGEX', '[a-z]+(-[a-z]+)*');

class LexemeParty {
    
    public $language_display = 'en';
    public $language_display_form = 'auto';
    
    public $languages_filter_action = 'block';
    public $languages_filter = array();
    
    public $languages_direction = 'rows';
    
    public $display_mode = 'compact';
    
    public $items_query_time = null;
    
    public $concepts = array();
    public $concepts_meta = array();
    public $languages = array();
    public $lexemes = array();
    public $senses = array();
    
    public $cells_count = 0;
    public $completion = 0;
    public $medals = array();
    
    public $errors = array();
    
    public function setDisplayMode($display_mode) {
        $this->display_mode = $display_mode;
    }

    public function init() {
        // filters
        $this->languages_filter_action = 'block';
        if (!empty($_GET['languages_filter_action']) && ($_GET['languages_filter_action'] === 'allow')) {
            $this->languages_filter_action = 'allow';
        }
        $this->languages_filter = array();
        if (!empty($_GET['languages_filter'])) {
            preg_match_all('/'.LANGUAGE_REGEX.'/', $_GET['languages_filter'], $matches);
            $this->languages_filter = $matches[0];
        }
        // table direction
        $this->languages_direction = 'rows';
        if (!empty($_GET['languages_direction']) && ($_GET['languages_direction'] === 'columns')) {
            $this->languages_direction = 'columns';
        }
        // display mode
        $this->display_mode = 'compact';
        if (!empty($_GET['display_mode']) && ($_GET['display_mode'] === 'full')) {
            $this->display_mode = 'full';
        }
    }
    
    public function initLanguageDisplay() {
        $this->language_display = 'en';
        $this->language_display_form = 'auto';
        if (!empty($_GET['language_display']) && ($_GET['language_display'] !== 'auto') && preg_match('/^'.LANGUAGE_REGEX.'$/', $_GET['language_display'])) {
            $this->language_display = $_GET['language_display'];
            $this->language_display_form = $_GET['language_display'];
        }
        elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $locale = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $locale = substr($locale, 0, strpos($locale, '_'));
            if (preg_match('/^'.LANGUAGE_REGEX.'$/', $locale)) {
                $this->language_display = $locale;
            }
        }
    }
    
    public function fetchConcepts($query) {
        $results = wdqs::query($query, WDQS_CACHE);
        if (empty($results) || count(@$results->results->bindings) === 0) {
            $this->errors[] = 'The input query returned no result.';
            return;
        }
        foreach ($results->results->bindings as $item) {
            $this->concepts[] = substr($item->concept->value, 31);
        }
    }
    
    public function setConcepts($concepts) {
        $this->concepts = $concepts;
    }

    public function fetchConceptsMeta() {
        $items = wdqs::query('SELECT ?concept ?conceptLabel ?url ?title {
  hint:Query hint:optimizer "None" .
  VALUES ?concept { wd:'.implode(' wd:', $this->concepts).' } .
  OPTIONAL { ?concept rdfs:label ?conceptLabel . FILTER(LANG(?conceptLabel) = "'.$this->language_display.'") }
  OPTIONAL { ?url schema:about ?concept ; schema:inLanguage "'.$this->language_display.'" ; schema:name ?name ; schema:isPartOf [ wikibase:wikiGroup "wikipedia" ] ; schema:name ?title }
}', WDQS_CACHE)->results->bindings;
        foreach ($items as $item) {
            $this->concepts_meta[substr($item->concept->value, 31)] = (object) array('label' => @$item->conceptLabel->value, 'wikipedia_url' => @$item->url->value, 'wikipedia_title' => @$item->title->value);
        }
    }
    
    public function queryItems($cache = WDQS_CACHE) {
        
        // filters query optimization (languages are also filtered later)
        // TODO: handle languages without codes
        $filter = '';
        if (($this->languages_filter_action == 'allow') && (!empty($this->languages_filter))) {
            $filter = '?language wdt:P424 ?code . VALUES ?code { "'.implode('" "', $this->languages_filter).'" }';
        } elseif (($this->languages_filter_action == 'block') && (!empty($this->languages_filter))) {
            $filter = 'FILTER NOT EXISTS { ?language wdt:P424 ?code . VALUES ?code { "'.implode('" "', $this->languages_filter).'" } }';
        }
        
        // add check by code? => ?language wdt:P424 ?code . FILTER (LANG(?lemma) = ?code) .
        $query = 'SELECT * {
  hint:Query hint:optimizer "None" .
  ?sense wdt:P5137 ?concept .
  VALUES ?concept { wd:'.implode(' wd:', $this->concepts).' }
  [] ontolex:sense ?sense ; wikibase:lemma ?lemma ; dct:language ?language .
  '.$filter.'
}';
        
        $items = wdqs::query($query, $cache)->results->bindings;
        $this->items_query_time = wdqs::getLastQueryTime();
    
        if (count($items) === 0) {
            $this->errors[] = 'No lexeme found :(';
        }
        
        return $items;

    }
    
    public function computeItems($items) {
        
        $this->cells_count = 0;
        
        // $languages initilization
        $this->languages = array();
        foreach ($items as $item) {
            $language_qid = substr($item->language->value, 31);
            if (!isset($this->languages[$language_qid])) {
                $l = $this->fetchLanguage($language_qid);
                if ($l !== false) {
                    if ((($this->languages_filter_action === 'allow') && in_array($l->code, $this->languages_filter))
                        || (($this->languages_filter_action === 'block') && !in_array($l->code, $this->languages_filter))) {
                        $this->languages[$language_qid] = $l;
                    }
                } else {
                    $sense = substr($item->sense->value, 31);
                    // TODO: group errors by language
                    $this->errors[] = 'Multiple <a href="https://www.wikidata.org/wiki/Property:P424">P424</a> values for language <a href="https://www.wikidata.org/wiki/'.$language_qid.'">'.$language_qid.'</a> used in <a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense).'">'.$sense.'</a>.';
                }
            }
        }
        
        // sort languages by label
        usort($this->languages, function($a, $b) {
            $r = $a->label <=> $b->label;
            if ($r != 0) {
                return $r;
            }
            $r = $a->code <=> $b->code;
            if ($r != 0) {
                return $r;
            }
            return $a->qid <=> $b->qid;
        });
        
        // items initilization
        foreach ($this->concepts as $concept) {
            foreach ($this->languages as $language) {
                $this->items[$concept][$language->qid] = array();
            }
        }
        foreach ($items as $item) {
            $concept_qid = substr($item->concept->value, 31);
            $language_qid = substr($item->language->value, 31);
            if (isset($this->items[$concept_qid][$language_qid])) {
                $sense = substr($item->sense->value, 31);
                if (!isset($this->items[$concept_qid][$language_qid][$sense])) {
                    $this->items[$concept_qid][$language_qid][$sense] = array();
                }
                $this->items[$concept_qid][$language_qid][$sense][] = $item->lemma->value;
                if (!in_array($sense, $this->senses)) {
                    $this->senses[] = $sense;
                }
                $lexeme = substr($sense, 0, strpos($sense, '-'));
                if (!in_array($lexeme, $this->lexemes)) {
                    $this->lexemes[] = $lexeme;
                }
            }
        }
        // sorting
        foreach ($this->languages as $language) {
            foreach ($this->concepts as $concept) {
                if (!empty($this->items[$concept][$language->qid])) {
                    $this->cells_count++;
                    ksort($this->items[$concept][$language->qid]);
                    foreach ($this->items[$concept][$language->qid] as &$lemmas) {
                        $lemmas = array_unique($lemmas);
                        asort($lemmas);
                    }
                }
            }
        }
        
        // scores
        $this->completion = floor(100 * $this->cells_count / (count($this->languages) * count($this->concepts)));
        $this->medals = array('gold' => 0, 'silver' => 0, 'bronze' => 0, '' => 0);
        foreach ($this->languages as $language) {
            $sum = 0;
            foreach ($this->concepts as $concept) {
                if (count($this->items[$concept][$language->qid]) >= 1) {
                    $sum++;
                }
            }
            $language->score = 100.0 * $sum / count($this->concepts);
            if ($language->score === 100.0) {
                $language->medal = 'gold';
            } elseif ($language->score >= 80) {
                $language->medal = 'silver';
            } elseif ($language->score >= 50) {
                $language->medal = 'bronze';
            } else {
                $language->medal = '';
            }
            $this->medals[$language->medal]++;
        }
        
    }
    
    private function fetchLanguage($qid) {
        $items = wdqs::query('SELECT ?code ?label {
      OPTIONAL { wd:'.$qid.' wdt:P424 ?code }
      OPTIONAL { wd:'.$qid.' rdfs:label ?label . FILTER(LANG(?label) = "'.$this->language_display.'") }
    }', WDQS_CACHE)->results->bindings;
        if (count($items) > 1) {
            return false;
        }
        $item = $items[0];
        $r = (object) array('qid' => $qid, 'code' => @$item->code->value, 'label' => @$item->label->value);
        if ($r->code === null) {
            $r->code = '∅';
        }
        return $r;
    }
    
    public function display() {
        echo '<table id="lexemes">';
        // TODO: clean this code ^^
        if ($this->languages_direction == 'rows') {
            echo '<tr><th colspan="2">Wikidata</th>';
            foreach ($this->concepts as $concept) {
                echo '<th>';
                if (!empty($this->concepts_meta[$concept]->label)) {
                    echo htmlentities($this->concepts_meta[$concept]->label).' ';
                }
                echo '(<a href="https://www.wikidata.org/wiki/'.$concept.'">'.$concept.'</a>)';
                if (!empty($this->concepts_meta[$concept]->label)) {
                    echo '<br /><a href="https://www.wikidata.org/w/index.php?search='.rawurlencode($this->concepts_meta[$concept]->label).'&amp;title=Special%3ASearch&amp;profile=advanced&amp;fulltext=1&amp;ns146=1"><img src="'.SITE_STATIC_DIR.'img/search.png" title="Search in Wikidata Lexemes" class="logo" /></a> <a href="https://'.$this->language_display.'.wiktionary.org/w/index.php?title=Special%3ASearch&amp;search='.rawurlencode($this->concepts_meta[$concept]->label).'"><img src="'.SITE_STATIC_DIR.'img/logo-wiktionary.png" title="Search in Wiktionary" class="logo" /></a>';
                }
                echo '</th>';
            }
            echo '</tr><tr><th colspan="2">Wikipedia</th>';
            foreach ($this->concepts as $concept) {
                echo '<td>';
                if (!empty($this->concepts_meta[$concept]->wikipedia_url)) {
                    echo '<a href="'.$this->concepts_meta[$concept]->wikipedia_url.'">'.htmlentities($this->concepts_meta[$concept]->wikipedia_title).'</a>';
                }
                echo '</td>';
            }
            echo '</tr>';
            foreach ($this->languages as $language) {
                echo '<tr><td title="'.floor($language->score).'%">';
                if (!empty($language->medal)) {
                    echo ' <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_'.$language->medal.'_3.png" alt="" />';
                }
                echo '</td><td title="'.floor($language->score).'%" class="left"><a href="https://www.wikidata.org/wiki/'.$language->qid.'">';
                if ($language->code !== '∅') {
                    echo '<span class="language">['.htmlentities($language->code).']</span> ';
                }
                if (!empty($language->label)) {
                    echo htmlentities($language->label);
                } else {
                    echo $language->qid;
                }
                echo '</a></td>';
                foreach ($this->concepts as $concept) {
                    echo '<td>'.$this->display_cell($this->items[$concept][$language->qid]).'</td>';
                }
                echo '</tr>'."\n";
            }
        }
        elseif ($this->languages_direction == 'columns') {
            echo '<tr><th colspan="3">Wikidata</th><th>Wikipedia</th>';
            foreach ($this->languages as $language) {
                echo '<th class="column"><a href="https://www.wikidata.org/wiki/'.$language->qid.'">';
                if ($language->code !== '∅') {
                    echo '<span class="language">['.htmlentities($language->code).']</span> ';
                }
                if (!empty($language->label)) {
                    echo htmlentities($language->label);
                } else {
                    echo $language->qid;
                }
                echo '</a><br />';
                if (!empty($language->medal)) {
                    echo '<img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_'.$language->medal.'_3.png" alt="" /> ';
                }
                echo '<span class="score">'.floor($language->score).'%</span></th>';
            }
            echo '</tr>';
            foreach ($this->concepts as $concept) {
                echo '<tr><td class="left"><a href="https://www.wikidata.org/wiki/'.$concept.'">'.$concept.'</a></td><td class="left">'.htmlentities($this->concepts_meta[$concept]->label).'</td><td>';
                if (!empty($this->concepts_meta[$concept]->label)) {
                    echo '<a href="https://www.wikidata.org/w/index.php?search='.rawurlencode($this->concepts_meta[$concept]->label).'&amp;title=Special%3ASearch&amp;profile=advanced&amp;fulltext=1&amp;ns146=1"><img src="'.SITE_STATIC_DIR.'img/search.png" title="Search in Wikidata Lexemes" class="logo" /></a> <a href="https://'.$this->language_display.'.wiktionary.org/w/index.php?title=Special%3ASearch&amp;search='.rawurlencode($this->concepts_meta[$concept]->label).'"><img src="'.SITE_STATIC_DIR.'img/logo-wiktionary.png" title="Search in Wiktionary" class="logo" /></a>';
                }
                echo '</td><td class="left">';
                if (!empty($this->concepts_meta[$concept]->wikipedia_url) && !empty($this->concepts_meta[$concept]->wikipedia_title)) {
                    echo '<a href="'.$this->concepts_meta[$concept]->wikipedia_url.'">'.htmlentities($this->concepts_meta[$concept]->wikipedia_title).'</a>';
                }
                echo '</td>';
                foreach ($this->languages as $language) {
                    echo '<td>'.$this->display_cell($this->items[$concept][$language->qid]).'</td>';
                }
                echo '</tr>'."\n";
            }
        }
        echo '</table>
    <p>'.count($this->languages).' languages
    &nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_gold_3.png" alt="" class="medal" title="100%" /> '.$this->medals['gold'].'
    &nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_silver_3.png" alt="" class="medal" title="≥ 80%" /> '.$this->medals['silver'].'
    &nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_bronze_3.png" alt="" class="medal" title="≥ 50%" /> '.$this->medals['bronze'].'
    &nbsp;&nbsp;&nbsp;&nbsp; &#8709; '.$this->medals[''];
        echo '</p>';
    }
    
    private function display_cell($items) {
        $r = '';
        if ($this->display_mode === 'compact') {
            foreach ($items as $sense => $lemmas) {
                $r .= '<a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense).'">'.htmlentities(implode(' / ', $lemmas)).'</a><br />';
            }
        } else {
            foreach ($items as $sense => $lemmas) {
                $r .= htmlentities(implode(' / ', $lemmas)).' (<a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense).'">'.$sense.'</a>)<br />';
            }
        }
        return $r;
    }
    
    public static function diff($current, $reference) {
        $diff = $current - $reference;
        if ($diff > 0) {
            return '<span class="pos">+'.$diff.'</span>';
        } elseif ($diff == 0) {
            return '=';
        } elseif ($diff < 0) {
            return '<span class="neg">'.$diff.'</span>';
        }
    }
    
    public static function diff_array($current, $reference) {
        $r = array();
        $intersect = array_intersect($reference, $current);
        if (count($intersect) < count($reference)) {
            $r[] = '<span class="neg">'.(count($intersect) - count($reference)).'</span>';
        }
        if (count($intersect) < count($current)) {
            $r[] = '<span class="pos">+'.(count($current) - count($intersect)).'</span>';
        }
        if (empty($r)) {
            return '=';
        }
        return implode(', ', $r);
    }
    
    public static function diff_party($currentParty, $referenceParty = null) {
        $r = '<ul><li><strong>'.count($currentParty->languages).'</strong> language'.(count($currentParty->languages) > 1 ? 's' : '');
        if ($referenceParty !== null) {
            $r .= ' ('.self::diff_array(array_keys($currentParty->languages), array_keys($referenceParty->languages)).')';
        }
        $r .= '</li><li><strong>'.count($currentParty->lexemes).'</strong> lexeme'.(count($currentParty->lexemes) > 1 ? 's' : '');
        if ($referenceParty !== null) {
            $r .= ' ('.self::diff_array($currentParty->lexemes, $referenceParty->lexemes).')';
        }
        $r .= '</li><li><strong>'.count($currentParty->senses).'</strong> sense'.(count($currentParty->senses) > 1 ? 's' : '');
        if ($referenceParty !== null) {
            $r .= ' ('.self::diff_array($currentParty->senses, $referenceParty->senses).')';
        }
        $r .= '</li><li><strong>'.$currentParty->completion.'%</strong> completion';
        if ($referenceParty !== null) {
            $r .= ' ('.self::diff($currentParty->completion, $referenceParty->completion).')';
        }
        $r .= '</li><li><strong>'.($currentParty->medals['gold'] * 3 + $currentParty->medals['silver'] * 2 + $currentParty->medals['bronze']).'</strong> medals';
        if ($referenceParty !== null) {
            $r .= ' ('.self::diff($currentParty->medals['gold'] * 3 + $currentParty->medals['silver'] * 2 + $currentParty->medals['bronze'], $referenceParty->medals['gold'] * 3 + $referenceParty->medals['silver'] * 2 + $referenceParty->medals['bronze']).')';
        }
        $r .= '</li></ul>';
        return $r;
    }
    
}

?>