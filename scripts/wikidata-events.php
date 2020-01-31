<?php

require '../inc/load.inc.php';

define('MIN_YEAR', 1000);

$date2 = new DateTime(date('Y-m-d'));
$events = array();

# events
echo 'Events...'."\n";
$res = @wdqs::query('SELECT ?item ?date ?sitelinks {
  ?item p:P585 [ rdf:type wikibase:BestRank ; psv:P585 [ wikibase:timeValue ?date ; wikibase:timePrecision ?precision ] ] ; wikibase:sitelinks ?sitelinks .
  FILTER(?precision >= 11 && YEAR(?date) >= '.MIN_YEAR.' && ?sitelinks >= 20 && ?item != wd:Q668984) .
  MINUS { ?item wdt:P31 wd:Q47150325 } . # simple date
  MINUS { ?item wdt:P31 wd:Q16913666 } . # Academy Awards ceremony
  MINUS { ?item wdt:P31 wd:Q276 } . # Eurovision
  MINUS { ?item wdt:P31/wdt:P279 wd:Q65742449 } . # Formula One race
  MINUS { ?item wdt:P31/wdt:P279 wd:Q858439 } . # presidential election
}', 86400)->results->bindings;
if (empty($res)) {
    die('Wikidata query failed.'."\n");
}
foreach ($res as $event) {
    addEvent($events, $event, 'event');
}

# discoveries
echo 'Discoveries...'."\n";
$res = @wdqs::query('SELECT ?item ?date ?sitelinks {
  ?item p:P575 [ rdf:type wikibase:BestRank ; psv:P575 [ wikibase:timeValue ?date ; wikibase:timePrecision ?precision ] ] ; wikibase:sitelinks ?sitelinks .
  FILTER(?precision >= 11 && YEAR(?date) >= '.MIN_YEAR.' && ?sitelinks >= 50) .
  #MINUS { ?item wdt:P31/wdt:P279* wd:Q17444909 } . # astronomical object
  #MINUS { ?item wdt:P31/wdt:P279*/wdt:P31 wd:Q17444909 } . # astronomical object
}', 86400)->results->bindings;
if (empty($res)) {
    die('Wikidata query failed.'."\n");
}
foreach ($res as $event) {
    addEvent($events, $event, 'discovery');
}

# TODO
# https://www.wikidata.org/wiki/Q631114#P1619

echo '"Event 0","URL 0","Date 0","Sitelinks 0","Event 1","URL 1","Date 1","Sitelinks 1","Score"'."\n";
foreach ($events as $event1) {
    $date1 = new DateTime($event1->date);
    $interval = $date1->diff($date2);
    $date0 = clone $date1;
    $date0 = $date0->sub($interval);
    if (isset($events[$date0->format('Y-m-d')])) {
        $event0 = $events[$date0->format('Y-m-d')];
        echo '"'.getLabel($event0).'","https://www.wikidata.org/wiki/'.$event0->qid.'","'.$event0->date.'",'.$event0->sitelinks.',"'.getLabel($event1).'","https://www.wikidata.org/wiki/'.$event1->qid.'","'.$event1->date.'",'.$event1->sitelinks.','.getScore($event0, $event1)."\n";
        if (!isset($selected_event0) || (getScore($selected_event0, $selected_event1) < getScore($event0, $event1))) {
            $selected_event0 = $event0;
            $selected_event1 = $event1;
        }
    }
}

if (isset($selected_event0)) {
    $status = 'Random events from Wikidata: the '.getLabel($selected_event1).' is now as close to the present as to the '.getLabel($selected_event0).'.'."\n".$selected_event0->date.' '.getArticle($selected_event0->qid)."\n".$selected_event1->date.' '.getArticle($selected_event1->qid)."\n".$date2->format('Y-m-d').' https://en.wikipedia.org/wiki/Main_Page';
    echo $status."\n";
    twitterapi::postTweet($status);
}

function addEvent(&$events, $event, $type) {
    $date = substr($event->date->value, 0, 10);
    if (!isset($events[$date]) || ($events[$date]->sitelinks < $event->sitelinks->value)) {
        $events[$date] = (object) array('qid' => substr($event->item->value, 31), 'date' => $date, 'sitelinks' => $event->sitelinks->value, 'type' => $type);
    }
}

function getScore($event0, $event1) {
    return ($event0->sitelinks + $event1->sitelinks) * sqrt(1 + (substr($event1->date, 0, 4) - substr($event0->date, 0, 4)) / 25);
}

function getLabel($event) {
    $label = $event->qid;
    $res = wdqs::query('SELECT ?label { wd:'.$event->qid.' rdfs:label ?label . FILTER(LANG(?label) = "en") }', 86400)->results->bindings;
    if (count($res) == 1) {
        $label = $res[0]->label->value;
    }
    if ($event->type == 'discovery') {
        $label = 'discovery of '.$label;
    }
    return $label;
}

function getArticle($qid) {
    $r = wdqs::query('SELECT ?article { ?article schema:about wd:'.$qid.' ; schema:isPartOf <https://en.wikipedia.org/> . }', 86400)->results->bindings;
    if (count($r) == 1) {
        return $r[0]->article->value;
    }
    return 'https://www.wikidata.org/wiki/'.$qid;
}

?>