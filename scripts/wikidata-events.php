<?php

require '../inc/load.inc.php';

define('MIN_SITELINKS', 17);
$date2 = new DateTime(date('Y-m-d'));
$dates = array();

$results1 = @wdqs::query('SELECT ?item ?date {
  ?item p:P585 [ rdf:type wikibase:BestRank ; psv:P585 [ wikibase:timeValue ?date ; wikibase:timePrecision ?precision ] ] ; wikibase:sitelinks ?sitelinks .
  FILTER(?precision >= 11 && YEAR(?date) >= 1500 && ?sitelinks >= '.MIN_SITELINKS.') .
}
ORDER BY ?sitelinks', 86400)->results->bindings;
if (empty($results1)) {
    die('Wikidata query failed.');
}

foreach ($results1 as $event1) {
    $dates[substr($event1->date->value, 0, 10)] = $event1;
}

foreach ($dates as $event1) {
    $date1 = new DateTime(substr($event1->date->value, 0, 10));
    $interval = $date1->diff($date2);
    $date0 = clone $date1;
    $date0 = $date0->sub($interval);
    if (isset($dates[$date0->format('Y-m-d')])) {
        $event0 = $dates[$date0->format('Y-m-d')];
        break;
    }
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
        return "\n".$r[0]->article->value;
    }
    return '';
}

if (isset($event0)) {
    $status = 'Random events from Wikidata: the '.getLabel(substr($event1->item->value, 31)).' ('.$date1->format('Y-m-d').') is now as close to the present as to the '.getLabel(substr($event0->item->value, 31)).' ('.$date0->format('Y-m-d').').'.getArticle(substr($event1->item->value, 31)).getArticle(substr($event0->item->value, 31));
    echo $status."\n";
    twitterapi::postTweet($status);
}

?>