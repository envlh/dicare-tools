<?php

define('LEXEMES_WDQS_CACHE', 3600);
define('LEXEMES_META_WDQS_CACHE', 86400);
define('LANGUAGE_REGEX', '[a-z]+(-[a-z]+)*');

class LexemeParty {
    
    public $path = 'wdt:P5137';
    public $property_form = 'P5137';
    
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
    public $lexicalCategories = array();
    
    public $cells_count = 0;
    public $completion = 0;
    public $medals = array();
    
    public $errors = array();
    
    public function setDisplayMode($display_mode) {
        $this->display_mode = $display_mode;
    }

    public function init() {
        // path
        $this->path = 'wdt:P5137';
        $this->property_form = 'P5137';
        if (!empty($_GET['property'])) {
            preg_match_all('/(P[1-9][0-9]*)/', $_GET['property'], $matches);
            $this->path = 'wdt:'.implode('|wdt:', $matches[1]);
            $this->property_form = $_GET['property'];
        }
        // filters
        $this->languages_filter_action = 'allow';
        if (!empty($_GET['languages_filter_action']) && ($_GET['languages_filter_action'] === 'block')) {
            $this->languages_filter_action = 'block';
        }
        $this->languages_filter = array();
        if (!empty($_GET['languages_filter'])) {
            foreach (explode(' ', $_GET['languages_filter']) as $filter) {
                if (($filter === 'auto') || preg_match('/^'.LANGUAGE_REGEX.'$/', $filter) || preg_match('/^Q[1-9][0-9]*$/', $filter)) {
                    $this->languages_filter[] = $filter;
                }
            }
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
        $this->language_display = $this->parseHttpAcceptLanguage();
        $this->language_display_form = 'auto';
        if (!empty($_GET['language_display']) && ($_GET['language_display'] !== 'auto') && preg_match('/^'.LANGUAGE_REGEX.'$/', $_GET['language_display'])) {
            $this->language_display = $_GET['language_display'];
            $this->language_display_form = $_GET['language_display'];
        }
    }
    
    private function parseHttpAcceptLanguage() {
        $r = 'en';
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $locale = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            if (preg_match('/^([a-z]+)/', $locale, $match)) {
                $r = $match[1];
            }
        }
        return $r;
    }
    
    public function fetchConcepts($query) {
        $results = wdqs::query($query, LEXEMES_WDQS_CACHE);
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

    public function setPath($path) {
        $this->path = $path;
    }

    public function fetchConceptsMeta() {
        $items = wdqs::query('SELECT ?concept ?conceptLabel ?url ?title {
  hint:Query hint:optimizer "None" .
  VALUES ?concept { wd:'.implode(' wd:', $this->concepts).' } .
  OPTIONAL { ?concept rdfs:label ?conceptLabel . FILTER(LANG(?conceptLabel) = "'.$this->language_display.'") }
  OPTIONAL { ?url schema:about ?concept ; schema:inLanguage "'.$this->language_display.'" ; schema:name ?name ; schema:isPartOf [ wikibase:wikiGroup "wikipedia" ] ; schema:name ?title }
}', LEXEMES_META_WDQS_CACHE)->results->bindings;
        foreach ($items as $item) {
            $this->concepts_meta[substr($item->concept->value, 31)] = (object) array('label' => @$item->conceptLabel->value, 'wikipedia_url' => @$item->url->value, 'wikipedia_title' => @$item->title->value);
        }
    }
    
    private function fetchLexicalCategories() {
        if (count($this->lexicalCategories) >= 1) {
            asort($this->lexicalCategories);
            $items = wdqs::query('SELECT ?lexicalCategory ?lexicalCategoryLabel {
      VALUES ?lexicalCategory { wd:'.implode(' wd:', $this->lexicalCategories).' } .
      SERVICE wikibase:label { bd:serviceParam wikibase:language "'.$this->language_display.'" }
    }', LEXEMES_META_WDQS_CACHE)->results->bindings;
            foreach ($items as $item) {
                if (isset($item->lexicalCategoryLabel->value)) {
                    $this->lexicalCategories[substr($item->lexicalCategory->value, 31)] = $item->lexicalCategoryLabel->value;
                }
            }
        }
    }
    
    public function queryItems($cache = LEXEMES_WDQS_CACHE) {
        
        $filters = array();
        foreach ($this->languages_filter as $filter) {
            $qid = $this->findLanguageQid($filter);
            if (!empty($qid)) {
                $filters[] = $qid;
            }
        }
        $filter = '';
        if (!empty($filters)) {
            if ($this->languages_filter_action == 'allow') {
                $filter = 'FILTER (?language IN (wd:'.implode(', wd:', $filters).'))';
            } elseif ($this->languages_filter_action == 'block') {
                $filter = 'FILTER (?language NOT IN (wd:'.implode(', wd:', $filters).'))';
            }
        }
        
        $query = 'SELECT DISTINCT ?sense ?concept ?lexeme ?lemma ?language ?lexicalCategory {
  hint:Query hint:optimizer "None" .
  ?sense '.$this->path.' ?concept .
  VALUES ?concept { wd:'.implode(' wd:', $this->concepts).' }
  ?lexeme ontolex:sense ?sense ; wikibase:lemma ?lemma ; dct:language ?language ; wikibase:lexicalCategory ?lexicalCategory .
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
                $this->languages[$language_qid] = $this->fetchLanguage($language_qid);
            }
        }
        
        // sort languages by label
        usort($this->languages, function($a, $b) {
            if (isset($a->label) && isset($b->label)) {
                $r = $a->label <=> $b->label;
                if ($r != 0) {
                    return $r;
                }
            }
            elseif (isset($a->label)) {
                return -1;
            }
            elseif (isset($b->label)) {
                return 1;
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
                $lexicalCategory_qid = null;
                if (isset($item->lexicalCategory->value)) {
                    $lexicalCategory_qid = substr($item->lexicalCategory->value, 31);
                    if (!isset($this->lexicalCategories[$lexicalCategory_qid])) {
                        $this->lexicalCategories[$lexicalCategory_qid] = $lexicalCategory_qid;
                    }
                }
                $sense = substr($item->sense->value, 31);
                if (!isset($this->items[$concept_qid][$language_qid][$sense])) {
                    $this->items[$concept_qid][$language_qid][$sense] = array();
                }
                $this->items[$concept_qid][$language_qid][$sense][] = $item->lemma->value;
                if (!isset($this->senses[$sense])) {
                    $this->senses[$sense] = $lexicalCategory_qid;
                }
                $lexeme = substr($sense, 0, strpos($sense, '-'));
                if (!isset($this->lexemes[$lexeme])) {
                    $this->lexemes[$lexeme] = $language_qid;
                }
            }
        }
        
        // scores
        foreach ($this->languages as $language) {
            foreach ($this->concepts as $concept) {
                if (!empty($this->items[$concept][$language->qid])) {
                    $this->cells_count++;
                }
            }
        }
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
    
    private function findLanguageQid($code) {
        // QID
        if (preg_match('/^Q[1-9][0-9]*$/', $code)) {
            return $code;
        }
        // auto
        $parsed_code = $code;
        if ($code === 'auto') {
            $parsed_code = $this->parseHttpAcceptLanguage();
        }
        // P218
        $items = wdqs::query('SELECT DISTINCT ?language { ?language wdt:P218 "'.$parsed_code.'" }', LEXEMES_META_WDQS_CACHE)->results->bindings;
        if (count($items) === 1) {
            return substr($items[0]->language->value, 31);
        }
        elseif (count($items) > 1) {
            $this->errors[] = 'Several languages found for P218 / ISO 639-1 = "'.$parsed_code.'".';
        }
        // P220
        $items = wdqs::query('SELECT DISTINCT ?language { ?language wdt:P220 "'.$parsed_code.'" }', LEXEMES_META_WDQS_CACHE)->results->bindings;
        if (count($items) === 1) {
            return substr($items[0]->language->value, 31);
        }
        elseif (count($items) > 1) {
            $this->errors[] = 'Several languages found for P220 / ISO 639-3 = "'.$parsed_code.'".';
        }
        // error
        $this->errors[] = 'No language found for code "'.$code.'".';
        return null;
    }
    
    private function fetchLanguage($qid) {
        $language = new stdClass;
        $language->qid = $qid;
        // P218 / ISO 639-1
        $items = wdqs::query('SELECT DISTINCT ?code { wd:'.$qid.' wdt:P218 ?code }', LEXEMES_META_WDQS_CACHE)->results->bindings;
        if (count($items) === 1) {
            $language->code = $items[0]->code->value;
            $language->iso_639_1 = $items[0]->code->value;
        }
        elseif (count($items) > 1) {
            $this->errors[] = 'Multiple P218 / ISO 639-1 for '.$qid.'.';
        }
        // P220 / ISO 639-3
        if (!isset($language->code)) {
            $items = wdqs::query('SELECT DISTINCT ?code { wd:'.$qid.' wdt:P220 ?code }', LEXEMES_META_WDQS_CACHE)->results->bindings;
            if (count($items) === 1) {
                $language->code = $items[0]->code->value;
                $language->iso_639_3 = $items[0]->code->value;
            }
            elseif (count($items) > 1) {
                $this->errors[] = 'Multiple P220 / ISO 639-3 for '.$qid.'.';
            }
        }
        // default code
        if (empty($language->code)) {
            $language->code = '∅';
        }
        // label
        $items = wdqs::query('SELECT DISTINCT ?label { wd:'.$qid.' rdfs:label ?label . FILTER(LANG(?label) = "'.$this->language_display.'") }', LEXEMES_META_WDQS_CACHE)->results->bindings;
        if (count($items) === 1) {
            $language->label = $items[0]->label->value;
        }
        return $language;
    }
    
    // used for rankings
    private static function fetchLanguageLabel($qid) {
        return wdqs::query('SELECT ?conceptLabel { VALUES ?concept { wd:'.$qid.' } . SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . } }', LEXEMES_META_WDQS_CACHE)->results->bindings[0]->conceptLabel->value;
    }

    public function display() {
        $this->fetchLexicalCategories();
        // sorting
        foreach ($this->languages as $language) {
            foreach ($this->concepts as $concept) {
                if (!empty($this->items[$concept][$language->qid])) {
                    // alphabetical order of lemmas
                    foreach ($this->items[$concept][$language->qid] as &$lemmas) {
                        $lemmas = array_unique($lemmas);
                        asort($lemmas);
                    }
                    $l = $this->items[$concept][$language->qid];
                    $s = $this->senses;
                    $lc = $this->lexicalCategories;
                    uksort($this->items[$concept][$language->qid], function ($a, $b) use ($l, $s, $lc) {
                        // alphabetical order of group of lemmas
                        $str_a = implode(' / ', $l[$a]);
                        $str_b = implode(' / ', $l[$b]);
                        $r = strcmp($str_a, $str_b);
                        if ($r !== 0) {
                            return $r;
                        }
                        // alphabetical order of lexical categories
                        $r = strcmp($lc[$s[$a]], $lc[$s[$b]]);
                        if ($r !== 0) {
                            return $r;
                        }
                        // alphabetical order of senses ids
                        return strcmp($a, $b);
                    });
                }
            }
        }
        echo '<table id="lexemes">';
        // TODO: clean this code ^^
        if ($this->languages_direction == 'rows') {
            echo '<tr><th colspan="3">Wikidata</th>';
            foreach ($this->concepts as $concept) {
                echo '<th>';
                if (!empty($this->concepts_meta[$concept]->label)) {
                    echo htmlentities($this->concepts_meta[$concept]->label).' ';
                }
                echo '(<a href="https://www.wikidata.org/wiki/'.$concept.'">'.$concept.'</a>)';
                if (!empty($this->concepts_meta[$concept]->label)) {
                    echo '<br /><a href="https://www.wikidata.org/w/index.php?search='.rawurlencode($this->concepts_meta[$concept]->label).'&amp;title=Special%3ASearch&amp;profile=advanced&amp;fulltext=1&amp;ns146=1"><img src="'.SITE_STATIC_DIR.'img/search.png" title="Search in Wikidata Lexemes" class="logo" /></a> <a href="https://'.$this->language_display.'.wiktionary.org/w/index.php?title=Special%3ASearch&amp;search='.rawurlencode($this->concepts_meta[$concept]->label).'"><img src="'.SITE_STATIC_DIR.'img/logo-wiktionary.png" title="Search in Wiktionary" class="logo" /></a> <a href="'.str_replace('"', '%22', 'https://query.wikidata.org/embed.html#%23defaultView%3ABarChart%0ASELECT %3Flemma_str (COUNT(DISTINCT %3FlanguageLabel) AS %3Fcount) (GROUP_CONCAT(DISTINCT %3FlanguageLabel %3B separator%3D"%2C ") AS %3Flanguages) {%0A%20 SELECT %3Flemma_str %3FlanguageLabel {%0A%20%20%20 %3Flexeme dct%3Alanguage %3Flanguage %3B wikibase%3Alemma %3Flemma %3B ontolex%3Asense %3Fsense .%0A%20%20%20 %3Fsense '.(str_replace(':', '%3A', $this->path)).' wd%3A'.$concept.' .%0A%20%20%20 BIND(STR(%3Flemma) AS %3Flemma_str) .%0A%20%20%20 SERVICE wikibase%3Alabel { bd%3AserviceParam wikibase%3Alanguage "[AUTO_LANGUAGE]%2Cen" }%0A%20 }%0A}%0AGROUP BY %3Flemma_str%0AORDER BY DESC(%3Fcount) %3Flemma_str').'"><img src="'.SITE_STATIC_DIR.'img/famfamfam/chart_bar.png" title="Bar chart" class="logo" /></a>';
                }
                echo '</th>';
            }
            echo '</tr><tr><th colspan="3">Wikipedia</th>';
            foreach ($this->concepts as $concept) {
                echo '<td>';
                if (!empty($this->concepts_meta[$concept]->wikipedia_url)) {
                    echo '<a href="'.$this->concepts_meta[$concept]->wikipedia_url.'">'.htmlentities($this->concepts_meta[$concept]->wikipedia_title).'</a>';
                }
                echo '</td>';
            }
            echo '</tr>';
            foreach ($this->languages as $language) {
                echo '<tr id="'.$language->qid.'"><td class="anchorlink"><a href="#'.$language->qid.'">#</a></td><td title="'.floor($language->score).'%">';
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
		$minutesElapsed = floor((time() - strtotime($this->items_query_time)) / 60);
        echo '</table>
    <p>'.count($this->languages).' languages
    &nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_gold_3.png" alt="" class="medal" title="100%" /> '.$this->medals['gold'].'
    &nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_silver_3.png" alt="" class="medal" title="≥ 80%" /> '.$this->medals['silver'].'
    &nbsp;&nbsp;&nbsp;&nbsp; <img src="'.SITE_STATIC_DIR.'img/famfamfam/medal_bronze_3.png" alt="" class="medal" title="≥ 50%" /> '.$this->medals['bronze'].'
    &nbsp;&nbsp;&nbsp;&nbsp; &#8709; '.$this->medals[''].'</p>
	<p>Last data update: '.$this->items_query_time.' ('.($minutesElapsed > 0 ? $minutesElapsed.' minute'.($minutesElapsed > 1 ? 's' : '').' ago' : 'now').').</p>';
    }
    
    private function display_cell($items) {
        $r = '';
        if ($this->display_mode === 'compact') {
            foreach ($items as $sense => $lemmas) {
                $r .= '<span class="lp_item"><a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense).'">'.htmlentities(implode(' / ', $lemmas)).'</a><div class="hovercard"><div class="hovercard-container"><div class="hovercard-box"><div class="hovercard-body">'.($this->lexicalCategories[$this->senses[$sense]] != $this->senses[$sense] ? htmlentities($this->lexicalCategories[$this->senses[$sense]]). ' ('.$this->senses[$sense].')' : $this->senses[$sense]).'</div></div></div></div></span><br />';
            }
        } else {
            foreach ($items as $sense => $lemmas) {
                $r .= htmlentities(implode(' / ', $lemmas)).' (<a href="https://www.wikidata.org/wiki/Lexeme:'.str_replace('-', '#', $sense).'">'.$sense.'</a>)<br />';
            }
        }
        return $r;
    }
    
    private static function diff($current, $reference) {
        $diff = $current - $reference;
        if ($diff > 0) {
            return '<span class="pos">+'.$diff.'</span>';
        } elseif ($diff == 0) {
            return '=';
        } elseif ($diff < 0) {
            return '<span class="neg">'.$diff.'</span>';
        }
    }
    
    private static function diff_array($current, $reference) {
        $r = array();
        $intersect = array_intersect(array_keys($reference), array_keys($current));
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
            $r .= ' ('.self::diff_array($currentParty->languages, $referenceParty->languages).')';
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

    public static function generateRankings($startParty, $endParty) {
        $rankings = array();
        $languages = array();
        foreach ($startParty->languages as $language) {
            $languages[] = $language->qid;
        }
        foreach ($endParty->languages as $language) {
            $languages[] = $language->qid;
        }
        $languages = array_unique($languages);
        foreach ($languages as $language) {
            $ranking = new stdClass();
            $ranking->language_qid = $language;
            $ranking->completion = 0; // concepts with at least one lexeme at the end of the challenge
            $ranking->removed = 0; // lexemes removed during the challenge
            $ranking->added = 0; // lexemes added during the challene
            foreach ($startParty->concepts as $concept_qid) {
                if (isset($startParty->items[$concept_qid][$ranking->language_qid])) {
                    $senses_start = array_keys($startParty->items[$concept_qid][$ranking->language_qid]);
                } else {
                    $senses_start = array();
                }
                if (isset($endParty->items[$concept_qid][$ranking->language_qid])) {
                    $senses_end = array_keys($endParty->items[$concept_qid][$ranking->language_qid]);
                } else {
                    $senses_end = array();
                }
                if (!empty($senses_end)) {
                    $ranking->completion++;
                }
                // senses -> lexemes
                $lexemes_start = array();
                foreach ($senses_start as $sense) {
                    $lexemes_start[] = substr($sense, 0, strpos($sense, '-'));
                }
                $lexemes_start = array_unique($lexemes_start);
                $lexemes_end = array();
                foreach ($senses_end as $sense) {
                    $lexemes_end[] = substr($sense, 0, strpos($sense, '-'));
                }
                $lexemes_end = array_unique($lexemes_end);
                // stats
                $intersect = array_intersect($lexemes_start, $lexemes_end);
                if (count($intersect) < count($lexemes_start)) {
                    $ranking->removed += abs(count($intersect) - count($lexemes_start));
                }
                if (count($intersect) < count($lexemes_end)) {
                    $ranking->added += count($lexemes_end) - count($intersect);
                }
            }
            $rankings[] = $ranking;
        }
        return $rankings;
    }
    
    public static function displayRankings($rankings, $numberOfConcepts, $anchorLinks = false) {
        echo '<table class="lexemes_stats">
    <tr>'.($anchorLinks ? '<th class="anchorlink"></th>' : '').'<th class="position">Pos.</th><th class="lang">Language</th><th>Lexemes linked</th><th>Lexemes unlinked</th><th>Lexemes improved</th><th>Completion</th></tr>';
        $pos = 1;
        $previousScore = '';
        foreach ($rankings as $ranking) {
            $score = $ranking->completion.'#'.$ranking->removed.'#'.$ranking->added;
            echo '<tr>'.($anchorLinks ? '<td class="anchorlink"><a href="#'.$ranking->language_qid.'">&uarr;</a></td>' : '').'<td class="position">'.(($score !== $previousScore) ? $pos.'.' : '').'</td><td class="lang"><a href="https://www.wikidata.org/wiki/'.$ranking->language_qid.'">'.htmlentities(self::fetchLanguageLabel($ranking->language_qid)).'</a></td><td>'.($ranking->added > 0 ? '<span class="pos">+'.$ranking->added.'</span>' : '').'</td><td>'.($ranking->removed > 0 ? '<span class="neg">-'.$ranking->removed.'</span>' : '').'</td><td>'.($ranking->removed + $ranking->added).'</td><td>'.$ranking->completion.' / '.$numberOfConcepts.'</td></tr>';
            $pos++;
            $previousScore = $score;
        }
        echo '</table>';
    }
    
}

?>