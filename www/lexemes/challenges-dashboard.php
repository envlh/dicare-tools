<?php

require '../../inc/load.inc.php';

$title = '<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenges-dashboard.php">Lexemes Challenges Dashboard</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');

require '../../inc/header.inc.php';

$concepts_count = db::query('SELECT SUM(`concepts_count`) FROM `lexemes_challenge` WHERE `date_start` IS NOT NULL AND `date_end` IS NOT NULL')->fetch_row()[0];

echo '<p>These tables count only lexemes for which the property <a href="https://www.wikidata.org/wiki/Property:P5137"><em>item for this sense</em> (P5137)</a> was added or removed during the challenges.</p>';

$res = db::query('SELECT `language_id`, SUM(`completion`) AS `completion`, SUM(`removed`) AS `removed`, SUM(`added`) AS `added` FROM `lexemes_challenge_statistics` GROUP BY `language_id` ORDER BY SUM(`removed` + `added`) DESC, `completion` DESC LIMIT 10');
echo '<h2>Most improved languages during challenges</h2>';
$rankings = array();
while ($ranking = $res->fetch_object()) {
    $ranking->language_qid = 'Q'.$ranking->language_id;
    $rankings[] = $ranking;
}
LexemeParty::displayRankings($rankings, $concepts_count);

$res = db::query('SELECT `language_id`, SUM(`completion`) AS `completion`, SUM(`removed`) AS `removed`, SUM(`added`) AS `added` FROM `lexemes_challenge_statistics` GROUP BY `language_id` ORDER BY `completion` DESC, SUM(`removed` + `added`) DESC LIMIT 10');
echo '<h2>Most complete languages at the end of challenges</h2>';
$rankings = array();
while ($ranking = $res->fetch_object()) {
    $ranking->language_qid = 'Q'.$ranking->language_id;
    $rankings[] = $ranking;
}
LexemeParty::displayRankings($rankings, $concepts_count);

require '../../inc/footer.inc.php';

?>