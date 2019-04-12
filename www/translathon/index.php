<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.TRANSLATHON_SITE_DIR.'">Transl-a-thon</a>');

require '../../inc/header.inc.php';

echo '<div style="padding: 16px; border: 1px solid red; background: #FEE;"><strong>A more powerful tool exists and you should use it: <a href="https://tools.wmflabs.org/wikidata-terminator/">Wikidata Terminator</a> ;-)</strong></div>';

$results = wdqs::query('
SELECT ?code ?label
WHERE {
  ?language wdt:P218 ?code .
  ?language rdfs:label ?label .
  FILTER (lang(?label) = ?code) .
}
ORDER BY ?code
', 86400)->results->bindings;

$languages = array();
foreach ($results as $result) {
    $languages[$result->code->value] = (object) array('code' => $result->code->value, 'label' => $result->label->value);
}

$texts = array('label' => 'rdfs:label', 'description' => 'schema:description');
$text = 'label';
$from = 'en';
$to = 'fr';
$instance = '';
$country = '';
$properties = false;
if (!empty($_GET['text']) && isset($texts[$_GET['text']])) {
    $text = $_GET['text'];
}
if (!empty($_GET['from']) && isset($languages[$_GET['from']])) {
    $from = $_GET['from'];
}
if (!empty($_GET['to']) && isset($languages[$_GET['to']])) {
    $to = $_GET['to'];
}
if (!empty($_GET['instance']) && preg_match('/^Q[1-9][0-9]*$/', $_GET['instance'])) {
    $instance = $_GET['instance'];
}
if (!empty($_GET['country']) && preg_match('/^Q[1-9][0-9]*$/', $_GET['country'])) {
    $country = $_GET['country'];
}
if (!empty($_GET['properties'])) {
    $properties = true;
}

echo '<form method="get" action="'.SITE_DIR.TRANSLATHON_SITE_DIR.'">
<p>Label and description exist in: <select name="from">'.displaySelectLanguageOptions($languages, $from).'</select></p>
<p><label for="label">Label</label> <input type="radio" id="label" name="text" value="label"'.($text == 'label' ? ' checked="checked"' : '').' /> or <label for="description">Description</label> <input type="radio" id="description" name="text" value="description"'.($text == 'description' ? ' checked="checked"' : '').' /> doesn\'t exist in: <select name="to">'.displaySelectLanguageOptions($languages, $to).'</select></p>
<p>Keep only items instance or subclass of: <input type="text" name="instance" value="'.$instance.'" /> (example: Q55488)</p>
<p>Country: <input type="text" name="country" value="'.$country.'" /> (example: Q142 for France)</p>
<p><input type="checkbox" id="properties" name="properties" value="1"'.($properties ? ' checked="checked"' : '').' /> <label for="properties">Properties only</label></p>
<p><input type="submit" value="Find items to translate" /></p>
</form>';

function displaySelectLanguageOptions($languages, $default) {
    $r = '';
    foreach ($languages as $language) {
        $r .= '<option value="'.htmlentities($language->code).'"';
        if ($language->code == $default) {
            $r .= ' selected="selcted"';
        }
        $r .= '>'.htmlentities($language->code).' - '.htmlentities($language->label).'</option>';
    }
    return $r;
}

$results = wdqs::query('
SELECT ?item ?label ?description
WHERE {
  '.(!empty($instance) ? '?item wdt:P31/wdt:P279* wd:'.$instance.' .' : '').'
  '.(!empty($country) ? '?item wdt:P17 wd:'.$country.' .' : '').'
  '.($properties ? '?item rdf:type wikibase:Property .' : '').'
  ?item rdfs:label ?label .
  FILTER (LANG(?label) = "'.$from.'") .
  ?item schema:description ?description .
  FILTER (LANG(?description) = "'.$from.'") .
  FILTER NOT EXISTS {
    ?item '.$texts[$text].' ?no .
    FILTER (LANG(?no) = "'.$to.'") .
  }
}
LIMIT 100
')->results->bindings;

if (count($results) >= 1) {
    echo '<table><tr><th>Qid</th><th>Label</th><th>Description</th></tr>';
    foreach ($results as $result) {
        $id = substr($result->item->value, 31);
        $type = substr($id, 0, 1);
        $namespace = ($type == 'P') ? 'Property:' : '' ;
        $label = $result->label->value;
        $description = $result->description->value;
        echo '<tr><td><a href="https://www.wikidata.org/wiki/'.$namespace.$id.'">'.$id.'</a></td><td>'.htmlentities($label).'</td><td><em>'.htmlentities($description).'</em></td>';
    }
    echo '</table>';
}

wdqs::displayQueries('en');

require '../../inc/footer.inc.php';

?>