<?php

require '../../inc/load.inc.php';

$challenge = null;
$error = null;

// specific challenge
if (!empty($_GET['id']) && preg_match('/^[1-9][0-9]*$/', $_GET['id'])) {
    $id = $_GET['id'];
    $challenge = LexemeChallenge::getChallenge($id);
    if (($challenge === null) || ($challenge->date_start === null)) {
        $error = 'Challenge not found!';
    }
}

// current challenge, starting a new one if necessary
if ($challenge === null) {
    $currentChallenge = LexemeChallenge::getCurrentChallenge();
    $nextChallenge = LexemeChallenge::findNewChallenge();
    if ($nextChallenge !== null) {
        if ($currentChallenge !== null) {
            $currentChallenge->close();
        }
        $nextChallenge->open();
        $challenge = $nextChallenge;
        db::commit();
    } else {
        $challenge = $currentChallenge;
    }
}
if ($challenge === null) {
    $error = 'No active challenge!';
}

$title = (!empty($challenge->title) ? htmlentities($challenge->title).' — ' : '').'<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php">Lexemes Challenge</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');

require '../../inc/header.inc.php';

if (!empty($error)) {
    echo '<h2>Error</h2><p>'.$error.'</p>';
}
else {
    // initial results
    $party = new LexemeParty();
    $party->setConcepts(explode(' ', $challenge->concepts));
    $items = unserialize($challenge->results_start);
    $party->computeItems($items);
    // current results
    $currentParty = new LexemeParty();
    $currentParty->initLanguageDisplay();
    $currentParty->setConcepts(explode(' ', $challenge->concepts));
    $currentParty->fetchConceptsMeta();
    $items = $currentParty->queryItems();
    $currentParty->computeItems($items);
    $currentParty->display('Challenge started on '.$challenge->date_start, $party);
}

require '../../inc/footer.inc.php';

?>