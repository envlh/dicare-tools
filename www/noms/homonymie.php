<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.NOMS_SITE_DIR.'homonymie.php">Génération d\'une page d\'homonymie</a>');
page::setMenu('noms');

require '../../inc/header.inc.php';

echo '<p>Cet outil génère, à partir des données <a href="https://www.wikidata.org/">Wikidata</a>, le wikicode d\'une page d\'homonymie pour la <a href="https://fr.wikipedia.org/">Wikipédia en français</a>.</p>
<form method="post" action="'.SITE_DIR.NOMS_SITE_DIR.'homonymie.php">
<p><label for="id">Identifiant Wikidata d\'un nom de famille</label> (exemple : Q23793569 pour <em>Tardy</em>) :<br /><input type="text" id="id" name="id" value="'.htmlentities(page::getParameter('id')).'" /></p>
<p><label for="fallback">Langues utilisées pour le modèle {{Lien}}</label> :<br /><input type="text" id="fallback" name="fallback" value="'.htmlentities(page::getParameter('fallback', NOMS_LANG_FALLBACK)).'" /></p>
<p><input type="submit" value="Générer" /></p>
</form>';

$id = null;
if (!empty($_POST['id']) && preg_match('/^Q[0-9]+$/', $_POST['id'])) {
    $id = $_POST['id'];
}

$fallback = array();
if (!empty($_POST['fallback'])) {
    preg_match_all('/[a-z]{2}/', $_POST['fallback'], $matches);
    $fallback = array_unique($matches[0]);
}

if ($id != null) {
    
    $query = 'SELECT ?person
(GROUP_CONCAT(DISTINCT ?modified_ ; separator = ",") AS ?modified)
(GROUP_CONCAT(DISTINCT ?personLabel_ ; separator = ",") AS ?personLabel)
(GROUP_CONCAT(DISTINCT ?personDescription_ ; separator = ",") AS ?personDescription)
(GROUP_CONCAT(DISTINCT ?birthname_ ; separator = ",") AS ?birthname)
(GROUP_CONCAT(DISTINCT ?pseudo_ ; separator = ",") AS ?pseudo)
(GROUP_CONCAT(DISTINCT ?alias_ ; separator = ",") AS ?alias)
(GROUP_CONCAT(DISTINCT ?gender_ ; separator = ",") AS ?gender)
(GROUP_CONCAT(DISTINCT ?occupationLabel_ ; separator = ", ") AS ?occupationLabel)
(GROUP_CONCAT(DISTINCT ?birthdate_ ; separator = ",") AS ?birthdate)
(GROUP_CONCAT(DISTINCT ?birthdate_precision_ ; separator = ",") AS ?birthdate_precision)
(GROUP_CONCAT(DISTINCT ?birthdate_quality_ ; separator = ",") AS ?birthdate_quality)
(GROUP_CONCAT(DISTINCT ?deathdate_ ; separator = ",") AS ?deathdate)
(GROUP_CONCAT(DISTINCT ?deathdate_precision_ ; separator = ",") AS ?deathdate_precision)
(GROUP_CONCAT(DISTINCT ?deathdate_quality_ ; separator = ",") AS ?deathdate_quality)
(GROUP_CONCAT(DISTINCT ?wikipedia_fr_ ; separator = ",") AS ?wikipedia_fr)';
    foreach ($fallback as $lang) {
        $query .= "\n".'(GROUP_CONCAT(DISTINCT ?wikipedia_'.$lang.'_ ; separator = ",") AS ?wikipedia_'.$lang.')';
    }
    $query .= '
WHERE {
    ?person schema:dateModified ?modified_ .
    ?person wdt:P734 wd:'.$id.' .
    OPTIONAL { ?person rdfs:label ?personLabel_ FILTER (LANG(?personLabel_) = "fr") . }
    OPTIONAL { ?person schema:description ?personDescription_ FILTER (LANG(?personDescription_) = "fr") . }
    OPTIONAL { ?person wdt:P1477 ?birthname_ . }
    OPTIONAL { ?person wdt:P742 ?pseudo_ . }
    OPTIONAL { ?person wdt:P1449 ?alias_ . }
    OPTIONAL { ?person wdt:P21 ?gender_ . }
    OPTIONAL {
        ?person wdt:P106 ?occupation .
        ?occupation rdfs:label ?occupationLabel_ FILTER (LANG(?occupationLabel) = "fr") .
    }
    OPTIONAL {
        ?person wdt:P569 ?birthdate_ .
        ?person p:P569 ?birthdate_all .
        ?birthdate_all ps:P569 ?birthdate_all_value .
        FILTER(?birthdate_all_value = ?birthdate_) .
        ?birthdate_all psv:P569/wikibase:timePrecision ?birthdate_precision_ .
        OPTIONAL { ?birthdate_all pq:P1480 ?birthdate_quality_ . }
    }
    OPTIONAL {
        ?person wdt:P570 ?deathdate_ .
        ?person p:P570 ?deathdate_all .
        ?deathdate_all ps:P570 ?deathdate_all_value .
        FILTER(?deathdate_all_value = ?deathdate_) .
        ?deathdate_all psv:P570/wikibase:timePrecision ?deathdate_precision_ .
        OPTIONAL { ?deathdate_all pq:P1480 ?deathdate_quality_ . }
    }
    OPTIONAL {
        ?wikipedia_fr_ schema:about ?person .
        ?wikipedia_fr_ schema:inLanguage "fr" .
        FILTER (SUBSTR(STR(?wikipedia_fr_), 1, 25) = "https://fr.wikipedia.org/")
    }
';
        foreach ($fallback as $lang) {
            $query .= '    OPTIONAL {
        ?wikipedia_'.$lang.'_ schema:about ?person .
        ?wikipedia_'.$lang.'_ schema:inLanguage "'.$lang.'" .
        FILTER (SUBSTR(STR(?wikipedia_'.$lang.'_), 1, 25) = "https://'.$lang.'.wikipedia.org/")
    }
';
    }
    $query .= '}
GROUP BY ?person
ORDER BY ?personLabel ?birthdate
';
    
    $items = wdqs::query($query);
    
    echo '<h2>Wikicode [<a href="'.SITE_DIR.NOMS_SITE_DIR.'homonymie.php?id='.urlencode(page::getParameter('id')).'&amp;fallback='.urlencode(page::getParameter('fallback', NOMS_LANG_FALLBACK)).'">Permalien</a>, <a href="'.SITE_DIR.NOMS_SITE_DIR.'nom-de-famille.php?id='.$id.'">Ajout en masse du nom de famille</a>]</h2><p>{{Patronymie}}<br />{{Nom de famille}}<br />';
    $time = time();
    foreach ($items->results->bindings as $item) {
        echo '* ';
        $modified = strtotime($item->modified->value);
        if ($modified + 86400 >= $time) {
            $modfiedRecently = true;
        } else {
            $modfiedRecently = false;
        }
        if ($modfiedRecently) {
            echo '<strong>';
        }
        $label = @$item->personLabel->value;
        $page = @$item->wikipedia_fr->value;
        $female = (!empty($item->gender->value) && $item->gender->value == 'http://www.wikidata.org/entity/Q6581072') ? true : false;
        echo '<a href="'.$item->person->value.'" title="dernière modification : '.date('Y-m-d H:i:s', $modified).'">';
        if ($page != null) {
            $page = str_replace('_', ' ', rawurldecode(substr($page, 30)));
            if ($page != $label) {
                echo '[['.$page.'|'.htmlentities($label).']]';
            } else {
                echo '[['.$label.']]';
            }
        } else {
            $found = false;
            foreach ($fallback as $lang) {
                if (!empty($item->{'wikipedia_'.$lang}->value)) {
                    $found = true;
                    echo '{{Lien|langue='.$lang.'|trad='.rawurldecode(substr($item->{'wikipedia_'.$lang}->value, 30)).'|fr='.htmlentities($label).'|texte='.htmlentities($label).'}}';
                    break;
                }
            }
            if (!$found) {
                if (!empty($label)) {
                    echo htmlentities($label);
                } else {
                    echo substr($item->person->value, 31);
                }
            }
        }
        echo '</a>';
        if ($modfiedRecently) {
            echo '</strong>';
        }
        if (!empty($item->birthdate->value) || !empty($item->deathdate->value)) {
            echo ' (';
            if (empty($item->birthdate->value)) {
                echo '?';
            } else {
                if (!empty($item->birthdate_quality->value) && ($item->birthdate_quality->value == 'http://www.wikidata.org/wiki/Q5727902')) {
                    echo 'circa ';
                }
                echo display::formatDatesWithPrecision(@$item->birthdate->value, @$item->birthdate_precision->value);
            }
            echo ' - ';
            if (!empty($item->deathdate_quality->value) && ($item->deathdate_quality->value == 'http://www.wikidata.org/entity/Q5727902')) {
                echo 'circa ';
            }
            echo display::formatDatesWithPrecision(@$item->deathdate->value, @$item->deathdate_precision->value).')';
        }
        if (!empty($item->birthname->value) && ($item->birthname->value != $item->personLabel->value) && !preg_match('/^t[0-9]+$/', $item->birthname->value)) {
            echo ', né'.($female ? 'e' : '').' \'\'\''.htmlentities($item->birthname->value).'\'\'\'';
        }
        $aliases = array();
        if (!empty($item->pseudo->value)) {
            $aliases = array_merge($aliases, explode(',', $item->pseudo->value));
        }
        if (!empty($item->alias->value)) {
            $aliases = array_merge($aliases, explode(',', $item->alias->value));
        }
        $aliases = array_unique($aliases);
        $aliases = array_diff($aliases, array($item->personLabel->value));
        sort($aliases);
        if (!empty($aliases)) {
            echo ', également connu'.($female ? 'e' : '').' comme « '.htmlentities(implode(' », « ', $aliases)).' »';
        }
        if (!empty($item->personDescription->value)) {
            echo ', '.htmlentities($item->personDescription->value);
        } elseif (!empty($item->occupationLabel->value)) {
            echo ', <span class="occupation">'.htmlentities($item->occupationLabel->value).'</span>';
        }
        echo '<br />';
    }
    echo '</p>';
    
}

wdqs::displayQueries();

require '../../inc/footer.inc.php';

?>