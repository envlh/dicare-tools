<?php

require '../../inc/load.inc.php';

$title = '<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenges-archive.php">Lexemes Challenges Archive</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');
page::setCard('Lexemes Challenges Archive', 'Statistics about Lexemes Challenges', SITE_STATIC_DIR.'img/lexemes-challenge.png');

require '../../inc/header.inc.php';

echo '<h2>Lexemes Challenges History</h2>
<p>This table counts all lexemes which were:</p>
<ul>
<li>linked to the items of the challenge with at least one of the following properties at the start or at the end of the challenge: '.LexemeChallenge::getPropertiesList().',</li>
<li>edited during the challenge.</li>
</ul>
<table id="lexemes_challenge_archive">
<tr><th>Date</th><th>Challenge</th><th class="stat">Lexemes improved</th><th class="stat">Languages improved</th><th class="stat">Editors</th></tr>';
$challenges_count = 0;
$lexemes_count = 0;
$languages_count = 0;
$editors_count = 0;
$res = db::query('SELECT `id`, `date_start`, `title`, `lexemes_improved`, `languages_improved`, `distinct_editors` FROM `lexemes_challenge` WHERE `date_start` IS NOT NULL ORDER BY `date_start` DESC');
while ($challenge = $res->fetch_object('LexemeChallenge')) {
    echo '<tr><td>'.substr($challenge->date_start, 0, 10).'</td><td class="challenge"><a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$challenge->id.'">'.htmlentities($challenge->title).'</a></td>';
    if (isset($challenge->lexemes_improved)) {
        $challenges_count++;
        $lexemes_count += $challenge->lexemes_improved;
        $languages_count += $challenge->languages_improved;
        $editors_count += $challenge->distinct_editors;
        echo '<td>'.$challenge->lexemes_improved.'</td><td>'.$challenge->languages_improved.'</td><td>'.$challenge->distinct_editors.'</td>';
    } else {
        echo '<td colspan="3"><em>not yet available for this challenge</em></td>';
    }
    echo '</tr>';
}
echo '<tr><td></td><td><strong>'.$challenges_count.' challenges completed</strong></td><td>sum = '.$lexemes_count.'<br />average = '.round($lexemes_count / $challenges_count).'</td><td>average = '.round($languages_count / $challenges_count).'</td><td>average = '.round($editors_count / $challenges_count).'</td></tr>
</table>';

require '../../inc/footer.inc.php';

?>