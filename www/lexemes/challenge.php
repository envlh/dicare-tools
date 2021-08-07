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
    $referenceParty = new LexemeParty();
    $referenceParty->setConcepts(explode(' ', $challenge->concepts));
    $items = unserialize($challenge->results_start);
    $referenceParty->computeItems($items);
    echo '<h2>Challenge started on '.$challenge->date_start.(!empty($challenge->date_end) ? ' and ended on '.$challenge->date_end : '').'</h2>';
    if (!empty($challenge->date_end)) {
        echo '<p><strong><a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php">&rarr; A new challenge is available!</a></strong></p>';
    }
    else {
        echo '<p>You can help by <a href="https://www.wikidata.org/wiki/Special:MyLanguage/Wikidata:Lexicographical_data">creating new lexemes</a> and linking senses to Wikidata items using <a href="https://www.wikidata.org/wiki/Property:P5137">P5137</a>. Usefull tool: <a href="https://lexeme-forms.toolforge.org/">Wikidata Lexeme Forms</a>.</p>';
    }
    echo '<div class="party_diff"><p>Start of the challenge:</p>'.LexemeParty::diff_party($referenceParty, null).'</div>';
    if (!empty($challenge->date_end)) {
        // final results
        $finalParty = new LexemeParty();
        $finalParty->setConcepts(explode(' ', $challenge->concepts));
        $items = unserialize($challenge->results_end);
        $finalParty->computeItems($items);
        echo '<div class="party_diff"><p>End of the challenge:</p>'.LexemeParty::diff_party($finalParty, $referenceParty).'</div>';
    }
    else {
    }
    // current results
    $currentParty = new LexemeParty();
    $currentParty->initLanguageDisplay();
    $currentParty->setConcepts(explode(' ', $challenge->concepts));
    $items = $currentParty->queryItems();
    $currentParty->computeItems($items);
    echo '<div class="party_diff"><p>Current progress:</p>'.LexemeParty::diff_party($currentParty, $referenceParty).'</div>
    <form action="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php" method="get" class="party_diff_clear">
    <p><input type="hidden" name="id" value="'.$challenge->id.'" /><label for="language_display">Display language:</label> <select name="language_display">
        <option value="auto">Automatic'.(($currentParty->language_display_form === 'auto') ? ' (detected: '.htmlentities($currentParty->language_display).')' : '').'</option>';
$res = wdqs::query('SELECT DISTINCT ?code ?label WHERE { ?language wdt:P218 ?code ; rdfs:label ?label . FILTER (LANG(?label) = ?code) } ORDER BY ?code', 86400)->results->bindings;
foreach ($res as $language) {
    echo '<option value="'.htmlentities($language->code->value).'"'.(($currentParty->language_display_form !== 'auto') && ($currentParty->language_display === $language->code->value) ? ' selected="selected"' : '').'>['.htmlentities($language->code->value).'] '.htmlentities($language->label->value).'</option>';
}
echo '</select> <input type="submit" value="Change" /></p>
</form>';
    $party = &$currentParty;
    if (!empty($_GET['table'])) {
        if ($_GET['table'] === 'reference') {
            $party = &$referenceParty;
        } elseif (($_GET['table'] === 'final') && (!empty($finalParty))) {
            $party = &$finalParty;
        }
    }
    $party->fetchConceptsMeta();
    $party->setDisplayMode('compact');
    $party->display();
}

require '../../inc/footer.inc.php';

?>