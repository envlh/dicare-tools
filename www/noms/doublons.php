<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.NOMS_SITE_DIR.'doublons.php">Doublons</a>');
page::setMenu('noms');

require '../../inc/header.inc.php';

$lists = array(
    array('label' => 'noms de famille', 'qid' => 'Q101352'),
    array('label' => 'prénoms', 'qid' => 'Q202444'),
    array('label' => 'prénoms épicènes', 'qid' => 'Q3409032'),
    array('label' => 'prénoms féminins', 'qid' => 'Q11879590'),
    array('label' => 'prénoms masculins', 'qid' => 'Q12308941'),
);

foreach ($lists as $list) {
    $query = '
SELECT DISTINCT ?item ?str ?lang
WHERE {
  ?item wdt:P31/wdt:P279* wd:'.$list['qid'].' ; wdt:P1705 ?str .
  BIND (LANG(?str) AS ?lang) .
}
';
    $items = wdqs::query($query, 3600);
    $names = array();
    foreach ($items->results->bindings as $item) {
        $id = substr($item->item->value, 32);
        $str = $item->str->value;
        $lang = @$item->lang->value;
        if ($lang != 'ja') {
            $names[$str][] = array('id' => $id, 'lang' => $lang);
        }
    }
    $count = 0;
    echo '<h2>'.ucfirst($list['label']).' (<a href="https://www.wikidata.org/wiki/'.$list['qid'].'">'.$list['qid'].'</a>)</h2>
<p><strong>'.display::formatInt(count($names)).'</strong> '.$list['label'].' avec la propriété <a href="https://www.wikidata.org/wiki/Property:P1705">P1705</a> renseignée avec une valeur dont la langue est différente de « ja ».</p>
<p>Doublons :</p>
<ul>';
    foreach ($names as $key => $name) {
        if (count($name) >= 2) {
            $count++;
            echo '<li><strong>'.$key.'</strong>';
            foreach ($name as $n) {
                echo ' <a href="https://www.wikidata.org/wiki/Q'.$n['id'].'">Q'.$n['id'].'</a> ('.$n['lang'].')';
            }
            echo '</li>';
        }
    }
    echo '</ul>
<p>&rarr; <strong>'.display::formatInt($count).'</strong> doublon'.($count >= 2 ? 's' : '').' ['.wdqs::getQueryTime($query).'].</p>';
}

wdqs::displayQueries();

require '../../inc/footer.inc.php';

?>