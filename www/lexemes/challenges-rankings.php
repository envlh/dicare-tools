<?php

require '../../inc/load.inc.php';

$title = '<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenges-rankings.php">Lexemes Challenges Rankings</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');

require '../../inc/header.inc.php';

$res = db::query('SELECT `language_id`, SUM(`completion`) AS `completion`, SUM(`removed`) AS `removed`, SUM(`added`) AS `added` FROM `lexemes_challenge_statistics` GROUP BY `language_id` ORDER BY SUM(`removed` + `added`) DESC, `completion` DESC LIMIT 10');
echo '<h2>Most improved languages during challenges</h2>'.display_table($res);

$res = db::query('SELECT `language_id`, SUM(`completion`) AS `completion`, SUM(`removed`) AS `removed`, SUM(`added`) AS `added` FROM `lexemes_challenge_statistics` GROUP BY `language_id` ORDER BY `completion` DESC, SUM(`removed` + `added`) DESC LIMIT 10');
echo '<h2>Most complete languages at the end of challenges</h2>'.display_table($res);

function display_table($res) {
    $r = '<table class="lexemes_stats">
<tr><th class="position">Pos.</th><th>Language</th><th>Concepts linked</th><th>Lexemes linked</th><th>Lexemes unlinked</th><th>Lexemes improved</th></tr>';
    $pos = 1;
    $previousScore = '';
    while ($row = $res->fetch_object()) {
        $score = $row->completion.'#'.$row->removed.'#'.$row->added;
        $r .= '<tr><td class="position">'.(($score !== $previousScore) ? $pos.'.' : '').'</td><td class="lang"><a href="https://www.wikidata.org/wiki/Q'.$row->language_id.'">'.htmlentities(get_language_label('Q'.$row->language_id)).'</a></td><td>'.$row->completion.'</td><td>'.($row->added > 0 ? '<span class="pos">+'.$row->added.'</span>' : '').'</td><td>'.($row->removed > 0 ? '<span class="neg">-'.$row->removed.'</span>' : '').'</td><td>'.($row->removed + $row->added).'</td></tr>';
        $pos++;
        $previousScore = $score;
    }
    $r .= '</table>';
    return $r;
}

function get_language_label($qid) {
    return wdqs::query('SELECT ?conceptLabel { VALUES ?concept { wd:'.$qid.' } . SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . } }', 86400)->results->bindings[0]->conceptLabel->value;
}

require '../../inc/footer.inc.php';

?>