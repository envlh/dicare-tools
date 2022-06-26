<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.DEPUTES_SITE_DIR.'">Députés français de la 5<sup>e</sup> République</a>');

require '../../inc/header.inc.php';

$cache = 86400;
if (!empty($_GET['action']) && ($_GET['action'] == 'purge')) {
    $cache = 1;
}
define('PAGE_SPARQL_CACHE', $cache);

echo '<style type="text/css">
td { text-align: center; }
.list td { width: 25%; text-align: right; }
td.label { text-align: left; }
</style>
<h2>Présentation</h2>
<ul>
    <li>Cet outil aide à contrôler la cohérence des données <a href="https://www.wikidata.org/">Wikidata</a> au sujet des députés français de la 5<sup>e</sup> République.</li>
    <li>Attention aux sources qui ont tendance, entre autres erreurs, à faire démarrer les mandats avant le début des législatures correspondantes, en particulier la base Sycomore.</li>
    <li>Seuls les députés pour lesquels la législature (P2937) d\'un mandat est renseignée sont pris en compte (Wikidata dispose en réalité de bien plus d\'informations sur les députés).</li>
    <li>Les statistiques sont actualisées une fois par jour, les listes sont actualisées en temps réel (avec un délai possible de quelques minutes après une modification de Wikidata).</li>
    <li>Voir aussi : <a href="https://www.wikidata.org/wiki/Wikidata:WikiProject_France/Politicians">WikiProject France/Politicians</a> et <a href="https://www.wikidata.org/wiki/Wikidata:WikiProject_Parliaments">WikiProject Parliaments</a>.</li>
</ul>';

$results = wdqs::query('
PREFIX q: <http://www.wikidata.org/prop/qualifier/>
PREFIX v: <http://www.wikidata.org/prop/statement/>
SELECT ?legislature ?number ?start ?end
WHERE {
  ?legislature p:P31 ?i .
  ?i v:P31 wd:Q15238777 .
  ?i q:P1545 ?number .
  ?legislature wdt:P361 wd:Q200686 .
  ?legislature wdt:P580 ?start .
  OPTIONAL { ?legislature wdt:P582 ?end . }
}
ORDER BY ?start
', PAGE_SPARQL_CACHE)->results->bindings;
foreach ($results as $result) {
    $legislatures[$result->number->value] = (object) array('qid' => substr($result->legislature->value, 31), 'number' => $result->number->value, 'start' => substr($result->start->value, 0, 10), 'end' => substr(@$result->end->value, 0, 10), 'label' => $result->number->value.'<sup>'.($result->number->value === '1' ? 'r' : '').'e</sup>');
}

$lists = (object) array(
    'all' => (object) array('label' => 'Tous les députés', 'sparql' => ''),
    'all_legislature_start' => (object) array('label' => 'Députés en début de législature', 'sparql' => '?mandate q:P580 ?mandateStart . ?legislature wdt:P580 ?legislatureStart . FILTER (?mandateStart = ?legislatureStart) .'),
    'all_legislature_end' => (object) array('label' => 'Députés en fin de législature', 'sparql' => '?mandate q:P582 ?mandateEnd . ?legislature wdt:P582 ?legislatureEnd . FILTER (?mandateEnd = ?legislatureEnd) .'),
    'gender' => (object) array('label' => 'Députés sans sexe (P21)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P21 ?gender } .'),
    'country' => (object) array('label' => 'Députés sans pays de nationalité = France (P27 = Q142)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P27 wd:Q142 } .'),
    'language' => (object) array('label' => 'Députés sans langues parlées, écrites ou signées = français (P1412 = Q150)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P1412 wd:Q150 } .'),
    'occupation' => (object) array('label' => 'Députés sans occupation = personnalité politique (P106 = Q82955)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P106 wd:Q82955 } .'),
    'firstname' => (object) array('label' => 'Députés sans prénom (P735)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P735 ?firstname } .'),
    'lastname' => (object) array('label' => 'Députés sans nom de famille (P734)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P734 ?lastname } .'),
    'firstname_lastname' => (object) array('label' => 'Députés avec un libéllé incohérent (différent de la concaténation du prénom et du nom de famille)', 'sparql' => '?depute rdfs:label ?label . FILTER(LANG(?label) = "fr") . ?depute wdt:P735/rdfs:label ?firstname . FILTER(LANG(?firstname) = "fr") . ?depute wdt:P734/rdfs:label ?lastname . FILTER(LANG(?lastname) = "fr") . FILTER(?label != CONCAT(?firstname, " "@fr, ?lastname)) .'),
    'birthdate' => (object) array('label' => 'Députés sans date de naissance (P569)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P569 ?birthdate } .'),
    'birthdate_day' => (object) array('label' => 'Députés sans date de naissance précise au jour près (P569)', 'sparql' => '?depute p:P569 [ rdf:type wikibase:BestRank ; psv:P569/wikibase:timePrecision ?birthdatePrecision ] . FILTER (?birthdatePrecision < 11) .'),
    'birthplace' => (object) array('label' => 'Députés sans lieu de naissance (P19)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P19 ?birthplace } .'),
    'deathdate_nobirthplace' => (object) array('label' => 'Députés avec une date de décès et sans lieu de décès (P570, P20)', 'sparql' => '?depute wdt:P570 ?deathdate . FILTER NOT EXISTS { ?depute wdt:P20 ?deathplace } .'),
    'deathplace_nodeathdate' => (object) array('label' => 'Députés avec un lieu de décès et sans date de décès (P20, P570)', 'sparql' => '?depute wdt:P20 ?deathplace . FILTER NOT EXISTS { ?depute wdt:P570 ?deathdate } .'),
    'start' => (object) array('label' => 'Députés avec un mandat sans date de début (P580)', 'sparql' => 'FILTER NOT EXISTS { ?mandate q:P580 ?start } .'),
    'start_precision' => (object) array('label' => 'Députés avec un mandat sans date de début précise au jour près (P580)', 'sparql' => '?mandate pqv:P580/wikibase:timePrecision ?startPrecision . FILTER (?startPrecision < 11) .'),
    'end' => (object) array('label' => 'Députés avec un mandat sans date de fin (P582)', 'sparql' => 'FILTER NOT EXISTS { ?mandate q:P582 ?start } .'),
    'end_precision' => (object) array('label' => 'Députés avec un mandat sans date de fin précise au jour près (P580)', 'sparql' => '?mandate pqv:P580/wikibase:timePrecision ?endPrecision . FILTER (?endPrecision < 11) .'),
    'circonscription' => (object) array('label' => 'Députés avec un mandat sans circonscription électorale (P768)', 'sparql' => 'FILTER NOT EXISTS { ?mandate q:P768 ?circonscription . ?circonscription wdt:P31/wdt:P279* wd:Q15620943 . } .'),
    'legislature_date' => (object) array('label' => 'Députés avec un mandat qui commence après son terme (P580 > P582)', 'sparql' => '?mandate q:P580 ?mandateStart . ?mandate q:P582 ?mandateEnd . FILTER (?mandateStart > ?mandateEnd) .'),
    'start_after_death' => (object) array('label' => 'Députés avec un mandat débutant avant sa naissance', 'sparql' => '?depute wdt:P569 ?birthdate . ?mandate q:P580 ?mandateStart . FILTER (?mandateStart < ?birthdate) .'),
    'end_after_death' => (object) array('label' => 'Députés avec un mandat se terminant après sa mort', 'sparql' => '?depute wdt:P570 ?deathdate . ?mandate q:P582 ?mandateEnd . FILTER (?mandateEnd > ?deathdate) .'),
    'legislature_out_before' => (object) array('label' => 'Députés avec un mandat qui commence avant le début de la législature correspondante', 'sparql' => '?mandate q:P580 ?mandateStart . ?legislature wdt:P580 ?legislatureStart . FILTER (?mandateStart < ?legislatureStart) .'),
    'legislature_out_after' => (object) array('label' => 'Députés avec un mandat qui se termine après la fin de la législature correspondante', 'sparql' => '?mandate q:P582 ?mandateEnd . ?legislature wdt:P582 ?legislatureEnd . FILTER (?mandateEnd > ?legislatureEnd) .'),
    'deputes_both' => (object) array('label' => 'Députés avec chevauchement d\'un mandat avec un autre député', 'sparql' => 'FILTER (?legislature != wd:Q3552944) . ?mandate q:P768 ?constituency . ?depute2 wdt:P31 wd:Q5 . ?depute2 p:P39 ?mandate2 . ?mandate2 v:P39 wd:Q3044918 . ?mandate2 q:P2937 ?legislature . ?mandate2 q:P768 ?constituency . ?mandate q:P580 ?mandateStart . ?mandate q:P582 ?mandateEnd . ?mandate2 q:P580 ?mandate2Start . FILTER (?depute != ?depute2) . FILTER (?mandateStart <= ?mandate2Start) . FILTER (?mandate2Start < ?mandateEnd) .'),
    'wikipedia_fr' => (object) array('label' => 'Députés sans article Wikipédia en français', 'sparql' => 'MINUS { ?sitelink schema:about ?depute . ?sitelink schema:inLanguage "fr" } .'),
    'sycomore' => (object) array('label' => 'Députés sans identifiant Sycomore (P1045)', 'sparql' => 'FILTER NOT EXISTS { ?depute wdt:P1045 ?sycomore } .'),
    'assembleeNationale' => (object) array('label' => 'Députés sans identifiant Assemblée nationale (P4123)', 'sparql' => 'FILTER (?legislature NOT IN (wd:Q3154303, wd:Q3146705, wd:Q3146694, wd:Q2380278, wd:Q3555150, wd:Q3552959, wd:Q3552950, wd:Q3552944, wd:Q3147021, wd:Q3570849)) . FILTER NOT EXISTS { ?depute wdt:P4123 ?assembleeNationale } .'),
);

echo '<h2>Statistiques</h2>
<table><tr><th>Liste \ Législature</th>';
foreach ($legislatures as $legislature) {
    echo '<th><span title="'.$legislature->start.' &rarr; '.$legislature->end.'">'.$legislature->label.'</span></th>';
}
echo '</tr>';
foreach ($lists as $key =>  $list) {
    echo '<tr><td class="label">'.htmlentities($list->label).'</td>';
    foreach ($legislatures as $legislature) {
        $count = dptListCount($list, $legislature);
        echo '<td'.((($key == @$_GET['list']) && ($legislature->number == @$_GET['legislature'])) ? ' class="highlight"' : '').'><a href="'.SITE_DIR.DEPUTES_SITE_DIR.'?list='.$key.'&amp;legislature='.$legislature->number.'#liste">';
        if ($count == 0) {
            echo '&mdash;';
        } else {
            echo $count;
        }
        echo '</a></td>';
    }
    echo '</tr>'."\n";
}
echo '</table>';

if (!empty($_GET['list']) && isset($lists->{$_GET['list']}) && !empty($_GET['legislature']) && isset($legislatures[$_GET['legislature']])) {
    $list = $lists->{$_GET['list']};
    $legislature = $legislatures[$_GET['legislature']];
    echo '<h2 id="liste">[<span title="'.$legislature->start.' &rarr; '.$legislature->end.'">'.$legislature->label.' législature</span>] '.htmlentities($lists->{$_GET['list']}->label).'</h2>';
    $deputes = dptList($list, $legislature);
    if (count($deputes) == 0) {
        echo '<p>Liste vide !</p>';
    } else {
        echo '<p>'.count($deputes).' résultat'.(count($deputes) > 1 ? 's' : '').'.</p><table class="list"><tr><th>Député</th><th>Wikidata</th><th>Base Sycomore</th><th>Assemblée nationale</th></tr>';
        foreach ($deputes as $depute) {
            $id = substr($depute->depute->value, 31);
            echo '<tr><td class="label">';
            if (!empty($depute->sitelink->value)) {
                echo '<a href="'.$depute->sitelink->value.'">';
            }
            echo htmlentities($depute->deputeLabel->value);
            if (!empty($depute->sitelink->value)) {
                echo '</a>';
            }
            echo '</td><td><a href="https://www.wikidata.org/wiki/'.$id.'">'.$id.'</a></td><td>';
            if (!empty($depute->sycomore->value)) {
                echo '<a href="http://www2.assemblee-nationale.fr/sycomore/fiche/(num_dept)/'.$depute->sycomore->value.'">'.$depute->sycomore->value.'</a>';
            }
            echo '</td><td>';
            if (!empty($depute->assembleeNationale->value)) {
                echo '<a href="http://www2.assemblee-nationale.fr/deputes/fiche/OMC_PA'.$depute->assembleeNationale->value.'">'.$depute->assembleeNationale->value.'</a> (<a href="http://www.assemblee-nationale.fr/11/tribun/fiches_id/'.$depute->assembleeNationale->value.'.asp">11<sup>e</sup></a>, <a href="http://www.assemblee-nationale.fr/12/tribun/fiches_id/'.$depute->assembleeNationale->value.'.asp">12<sup>e</sup></a>, <a href="http://www.assemblee-nationale.fr/13/tribun/fiches_id/'.$depute->assembleeNationale->value.'.asp">13<sup>e</sup></a>)';
            }
            echo '</td></tr>'."\n";
        }
        echo '</table>';
    }
}

function dptList($list, $legislature) {
    $query = '
PREFIX q: <http://www.wikidata.org/prop/qualifier/>
PREFIX v: <http://www.wikidata.org/prop/statement/>
SELECT DISTINCT ?depute ?deputeLabel ?sycomore ?assembleeNationale ?sitelink
WHERE {
  BIND(wd:'.$legislature->qid.' AS ?legislature) .
  ?depute wdt:P31 wd:Q5 .
  ?depute p:P39 ?mandate .
  ?mandate v:P39 wd:Q3044918 .
  ?mandate q:P2937 ?legislature .
  '.$list->sparql.'
  OPTIONAL { ?depute wdt:P1045 ?sycomore . }
  OPTIONAL { ?depute wdt:P4123 ?assembleeNationale . }
  OPTIONAL {
    ?sitelink schema:about ?depute .
    ?sitelink schema:isPartOf <https://fr.wikipedia.org/> .
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "fr" . }
}
';
    return wdqs::query($query)->results->bindings;
}

function dptListCount($list, $legislature) {
    global $cache;
    $query = '
PREFIX q: <http://www.wikidata.org/prop/qualifier/>
PREFIX v: <http://www.wikidata.org/prop/statement/>
SELECT (COUNT(*) AS ?count) {
  SELECT ?depute
  WHERE {
    BIND(wd:'.$legislature->qid.' AS ?legislature) .
    ?depute wdt:P31 wd:Q5 .
    ?depute p:P39 ?mandate .
    ?mandate v:P39 wd:Q3044918 .
    ?mandate q:P2937 ?legislature .
    '.$list->sparql.'
  }
  GROUP BY ?depute
}';
    return wdqs::query($query, PAGE_SPARQL_CACHE)->results->bindings[0]->count->value;
}

require '../../inc/footer.inc.php';

?>