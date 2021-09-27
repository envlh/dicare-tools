<?php

require '../inc/load.inc.php';

$nextChallenge = LexemeChallenge::findNewChallenge();
if ($nextChallenge === null) {
    echo date('Y-m-d H:i:s').' No new challenge to start.'."\n";
} else {
    echo date('Y-m-d H:i:s').' Starting challenge #'.$nextChallenge->id.'...'."\n";
    $currentChallenge = LexemeChallenge::getCurrentChallenge();
    if ($currentChallenge !== null) {
        echo date('Y-m-d H:i:s').' Closing challenge #'.$currentChallenge->id.'...'."\n";
        $currentChallenge->close();
        echo date('Y-m-d H:i:s').' Challenge #'.$currentChallenge->id.' closed.'."\n";
    }
    $nextChallenge->open();
    echo date('Y-m-d H:i:s').' Challenge #'.$nextChallenge->id.' started.'."\n";
    db::commit();
    echo date('Y-m-d H:i:s').' Commited.'."\n";
}

?>