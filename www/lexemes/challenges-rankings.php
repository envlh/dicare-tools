<?php

require '../../inc/load.inc.php';

$title = '<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenges-rankings.php">Lexemes Challenges Rankings</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');

require '../../inc/header.inc.php';

$res = db::query('SELECT `language_id`, SUM(`completion`) AS `completion`, SUM(`removed`) AS `removed`, SUM(`added`) AS `added` FROM `lexemes_challenge_statistics` GROUP BY `language_id` ORDER BY SUM(`removed` + `added`) DESC, `completion` DESC LIMIT 10');
echo '<h2>Most improved languages during challenges</h2>';
$rankings = array();
while ($ranking = $res->fetch_object()) {
    $ranking->language_qid = 'Q'.$ranking->language_id;
    $rankings[] = $ranking;
}
LexemeParty::displayRankings($rankings);

$res = db::query('SELECT `language_id`, SUM(`completion`) AS `completion`, SUM(`removed`) AS `removed`, SUM(`added`) AS `added` FROM `lexemes_challenge_statistics` GROUP BY `language_id` ORDER BY `completion` DESC, SUM(`removed` + `added`) DESC LIMIT 10');
echo '<h2>Most complete languages at the end of challenges</h2>';
$rankings = array();
while ($ranking = $res->fetch_object()) {
    $ranking->language_qid = 'Q'.$ranking->language_id;
    $rankings[] = $ranking;
}
LexemeParty::displayRankings($rankings);

require '../../inc/footer.inc.php';

?>