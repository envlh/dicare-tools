<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.NOMS_SITE_DIR.'prenom.php">Ajout en masse d\'un prénom</a>');
page::addJs('js/noms.js');
page::setMenu('noms');

require '../../inc/header.inc.php';

echo '<p>Cet outil génère le code <a href="https://tools.wmflabs.org/quickstatements/">QuickStatements</a> pour ajouter rapidement un prénom à des éléments <a href="https://www.wikidata.org/">Wikidata</a> n\'ayant pas la propriété <a href="https://www.wikidata.org/wiki/Property:P735">P735</a> renseignée.</p>
<form method="post" action="'.SITE_DIR.NOMS_SITE_DIR.'prenom.php">
<p><label for="id">Identifiant Wikidata d\'un prénom</label> (exemple : Q14626626 pour <em>Michel</em>) :<br /><input type="text" id="id" name="id" value="'.htmlentities(page::getParameter('id')).'" /></p>
<p><label for="countries">Identifiants Wikidata d\'un ou plusieurs pays</label> (exemple : Q142 pour la France) :<br /><input type="text" id="countries" name="countries" value="'.htmlentities(page::getParameter('countries', 'Q142')).'" /></p>
<p><input type="submit" value="Lister" /></p>
</form>';

$id = null;
if (!empty($_POST['id']) && preg_match('/^Q[0-9]+$/', $_POST['id'])) {
    $id = $_POST['id'];
}

$countries = array();
if (!empty($_POST['countries'])) {
    preg_match_all('/Q[1-9][0-9]*/', $_POST['countries'], $matches);
    $countries = array_unique($matches[0]);
}
if (count($countries) === 0) {
    $countries = array('Q142');
}

if ($id != null) {
    
    // get name string
    
    $query = '
SELECT ?nameLabel WHERE {
    wd:'.$id.' wdt:P31/wdt:P279* wd:Q202444 ; rdfs:label ?nameLabel .
    FILTER (LANG(?nameLabel) = "fr")
}
GROUP BY ?nameLabel
# '.time().'
';
    
    $items = wdqs::query($query);
    
    if ((count($items->results->bindings) !== 1) || empty($items->results->bindings[0]->nameLabel->value)) {
        echo '<p>Impossible de récupérer le libellé en français de '.$id.' (est-ce un prénom ?).</p>';
        exit;
    }
    
    $name = $items->results->bindings[0]->nameLabel->value;
    if (!preg_match('/^[a-zA-ZàâäçéèêëîïôöûüÿÀÂÄÉÈÊËÎÏÔÖÛÜŸœŒ\' -]+$/', $name)) {
        echo '<p>Le libellé du prénom contient des caractères spéciaux non valides.</p>';
        exit;
    }
    
    // get persons
    
    $countriesConditions = array();
    foreach ($countries as $country) {
        $countriesConditions[] = '?nation = wd:'.$country;
    }

    $query = '
SELECT ?person
(GROUP_CONCAT(DISTINCT ?personLabel ; separator = ",") AS ?personLabel)
(GROUP_CONCAT(DISTINCT ?birthname ; separator = ",") AS ?birthname)
(GROUP_CONCAT(DISTINCT ?pseudo ; separator = ",") AS ?pseudo)
(GROUP_CONCAT(DISTINCT ?alias ; separator = ",") AS ?alias)
WHERE {
    ?person wdt:P31 wd:Q5 .
    ?person wdt:P27 ?nation .
    FILTER ('.implode(' || ', $countriesConditions).')
    FILTER NOT EXISTS { ?person wdt:P735 ?anything }
    ?person rdfs:label ?personLabel
    FILTER (LANG(?personLabel) = "fr" && (STRSTARTS(?personLabel, "'.$name.' ") || STRSTARTS(?personLabel, "'.$name.',")))
    OPTIONAL { ?person wdt:P1477 ?birthname . }
    OPTIONAL { ?person wdt:P742 ?pseudo . }
    OPTIONAL { ?person wdt:P1449 ?alias . }
}
GROUP BY ?person
ORDER BY ?personLabel
';

    $items = wdqs::query($query);
    
    echo '<h2>Résultats [<a href="'.SITE_DIR.NOMS_SITE_DIR.'prenom.php?id='.urlencode(page::getParameter('id')).'&amp;countries='.urlencode(page::getParameter('countries', 'Q142')).'">Permalien</a>]</h2>';
    if (count($items->results->bindings) === 0) {
        echo '<p>Aucun résultat.</p>';
    }
    else {
        echo '<table id="results"><tr><th>?</th><th>Identifiant</th><th>Libellé</th><th>Autres libellés</th></tr>';
        foreach ($items->results->bindings as $item) {
            $itemId = substr($item->person->value, 32);
            $aliases = array();
            if (!empty($item->birthname->value)) {
                $aliases = array_merge($aliases, explode(',', $item->birthname->value));
            }
            if (!empty($item->pseudo->value)) {
                $aliases = array_merge($aliases, explode(',', $item->pseudo->value));
            }
            if (!empty($item->alias->value)) {
                $aliases = array_merge($aliases, explode(',', $item->alias->value));
            }
            $aliases = array_unique($aliases);
            $aliases = array_diff($aliases, array($item->personLabel->value));
            sort($aliases);
            echo '<tr><td><input type="checkbox" checked="checked" id="person_'.$itemId.'" /></td><td><a href="'.$item->person->value.'">Q'.$itemId.'</a></td><td><label for="person_'.$itemId.'">'.htmlentities($item->personLabel->value).'</label></td><td>'.htmlentities(implode(', ', $aliases)).'</td></tr>';
        }
        echo '</table><p><input type="button" value="Générer" onclick="generate(\''.$id.'\', \'P735\');" /></p><div id="generated"></div>';
    }
    
}

wdqs::displayQueries();

require '../../inc/footer.inc.php';

?>