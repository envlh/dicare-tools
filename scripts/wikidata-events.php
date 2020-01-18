<?php

require '../inc/load.inc.php';

define('MIN_SITELINKS', 17);
$date2 = new DateTime(date('Y-m-d'));
$dates = array();

$results1 = @wdqs::query('SELECT ?item ?date ?sitelinks {
  ?item p:P585 [ rdf:type wikibase:BestRank ; psv:P585 [ wikibase:timeValue ?date ; wikibase:timePrecision ?precision ] ] ; wikibase:sitelinks ?sitelinks .
  FILTER(?precision >= 11 && YEAR(?date) >= 1400 && ?sitelinks >= '.MIN_SITELINKS.') .
  MINUS { ?item wdt:P31 wd:Q47150325 } . # simple date
  MINUS { ?item wdt:P31 wd:Q16913666 } . # Academy Awards ceremony
  MINUS { ?item wdt:P31/wdt:P279 wd:Q65742449 } . # Formula One race
  MINUS { ?item wdt:P31/wdt:P279 wd:Q858439 } . # presidential election
}
ORDER BY ?date ?sitelinks
#', 86400)->results->bindings;
if (empty($results1)) {
    die('Wikidata query failed.'."\n");
}

foreach ($results1 as $event1) {
    $dates[substr($event1->date->value, 0, 10)] = $event1;
}

echo '"Event 0","URL 0","Date 0","Sitelinks 0","Event 1","URL 1","Date 1","Sitelinks 1","Score"'."\n";
foreach ($dates as $event1) {
    $date1 = new DateTime(substr($event1->date->value, 0, 10));
    $interval = $date1->diff($date2);
    $date0 = clone $date1;
    $date0 = $date0->sub($interval);
    if (isset($dates[$date0->format('Y-m-d')])) {
        $event0 = $dates[$date0->format('Y-m-d')];
        $qid0 = substr($event0->item->value, 31);
        $qid1 = substr($event1->item->value, 31);
        echo '"'.getLabel($qid0).'","https://www.wikidata.org/wiki/'.$qid0.'","'.substr($event0->date->value, 0, 10).'",'.$event0->sitelinks->value.',"'.getLabel($qid1).'","https://www.wikidata.org/wiki/'.$qid1.'","'.substr($event1->date->value, 0, 10).'",'.$event1->sitelinks->value.','.getScore($event0, $event1)."\n";
        if (!isset($selected_event0) || (getScore($selected_event0, $selected_event1) < getScore($event0, $event1))) {
            $selected_event0 = $event0;
            $selected_event1 = $event1;
        }
    }
}

function getScore($event0, $event1) {
    return ($event0->sitelinks->value + $event1->sitelinks->value) * sqrt(1 + (substr($event1->date->value, 0, 4) - substr($event0->date->value, 0, 4)) / 25);
}

function getLabel($qid) {
    $r = wdqs::query('SELECT ?label { wd:'.$qid.' rdfs:label ?label . FILTER(LANG(?label) = "en") }', 86400)->results->bindings;
    if (count($r) == 1) {
        return $r[0]->label->value;
    }
    return $qid;
}

function getArticle($qid) {
    $r = wdqs::query('SELECT ?article { ?article schema:about wd:'.$qid.' ; schema:isPartOf <https://en.wikipedia.org/> . }', 86400)->results->bindings;
    if (count($r) == 1) {
        return $r[0]->article->value;
    }
    return 'https://www.wikidata.org/wiki/'.$qid;
}

if (isset($selected_event0)) {
    $status = 'Random events from Wikidata: the '.getLabel(substr($selected_event1->item->value, 31)).' is now as close to the present as to the '.getLabel(substr($selected_event0->item->value, 31)).'.'."\n".substr($selected_event0->date->value, 0, 10).' '.getArticle(substr($selected_event0->item->value, 31))."\n".substr($selected_event1->date->value, 0, 10).' '.getArticle(substr($selected_event1->item->value, 31))."\n".$date2->format('Y-m-d').' https://en.wikipedia.org/wiki/Main_Page';
    echo $status."\n";
    twitterapi::postTweet($status);
}

?>