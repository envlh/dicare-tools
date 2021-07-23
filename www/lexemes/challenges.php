<?php

require '../../inc/load.inc.php';

$title = 'History — <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php">Lexemes Challenge</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');

require '../../inc/header.inc.php';

echo '<ul>';
$res = db::query('SELECT * FROM `lexeme_challenge` WHERE `date_end` IS NOT NULL ORDER BY `date_end`');
while ($challenge = $res->fetch_object('LexemeChallenge')) {
    echo '<li><a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$challenge->id.'">'.htmlentities($challenge->title).'</a></li>';
}
echo '</ul>';

require '../../inc/footer.inc.php';

?>