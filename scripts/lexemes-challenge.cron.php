<?php

require '../inc/load.inc.php';

$nextChallenge = LexemeChallenge::findNewChallenge();
if ($nextChallenge === null) {
    echo 'No new challenge to start.'."\n";
} else {
    echo 'Starting challenge #'.$nextChallenge->id.'...'."\n";
    $currentChallenge = LexemeChallenge::getCurrentChallenge();
    if ($currentChallenge !== null) {
        echo 'Closing challenge #'.$currentChallenge->id.'...'."\n";
        $currentChallenge->close();
        echo 'Challenge #'.$currentChallenge->id.' closed.'."\n";
    }
    $nextChallenge->open();
    echo 'Challenge #'.$nextChallenge->id.' started.'."\n";
    db::commit();
    echo 'Commited.'."\n";
}

?>