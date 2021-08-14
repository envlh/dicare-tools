<?php

require '../../inc/load.inc.php';

$title = '<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenges-archive.php">Lexemes Challenges Archive</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');

require '../../inc/header.inc.php';

echo '<h2>History</h2>
<ul>';
$res = db::query('SELECT * FROM `lexemes_challenge` WHERE `date_start` IS NOT NULL ORDER BY `date_start` DESC');
while ($challenge = $res->fetch_object('LexemeChallenge')) {
    echo '<li><a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$challenge->id.'">'.htmlentities($challenge->title).'</a></li>';
}
echo '</ul>';

require '../../inc/footer.inc.php';

?>