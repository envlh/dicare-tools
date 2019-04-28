<?php

require '../../inc/load.inc.php';

$http_404 = false;

$property = null;
if (!empty($_GET['property'])) {
    if (!preg_match('/^[1-9][0-9]*$/', $_GET['property'])) {
        $http_404 = true;
    } else {
        $res = db::query('SELECT `id`, `label`, `cardinality` FROM `property` WHERE `id` = '.$_GET['property']);
        if ($res->num_rows !== 1) {
            $http_404 = true;
        } else {
            $property = $res->fetch_object();
        }
    }
}

$types = array();
$res = db::query('SELECT DISTINCT `type` FROM `property` ORDER BY `type`');
while ($row = $res->fetch_object()) {
    $types[] = $row->type;
}

$selection = array();
if (!empty($_GET['type']) && is_array($_GET['type'])) {
    foreach ($_GET['type'] as $type) {
        if (in_array($type, $types) && !in_array($type, $selection)) {
            $selection[] = $type;
        }
    }
}
sort($selection);

$selectionSQL = '';
$selectionURL = '';
if (!empty($selection) && (count($selection) < count($types))) {
    $selectionSQL = ' IN (\''.implode('\', \'', $selection).'\')';
    $selectionURL = '&amp;type[]='.implode('&amp;type[]=', $selection);
}

function properties_filter_form($types, $property, $selection) {
    echo '<form action="'.SITE_DIR.PROPERTIES_SITE_DIR.'" method="get"><p>';
    if (!empty($property)) {
        echo '<input type="hidden" name="property" value="'.$property->id.'" />';
    }
    echo 'Display properties of the following types:&nbsp;&nbsp;';
    foreach ($types as $type) {
        echo '<span style="white-space: nowrap;"><input type="checkbox" id="type_'.htmlentities($type).'" name="type[]" value="'.htmlentities($type).'" ';
        if (empty($selection) || in_array($type, $selection)) {
            echo ' checked="checked"';
        }
        echo ' /> <label for="type_'.htmlentities($type).'">'.htmlentities($type).'</label></span>&nbsp; ';
    }
    echo '<input type="submit" value="Filter" /></p>';
}

if ($http_404) {
    header('HTTP/1.1 404 Not Found', true, 404);
}

if (!empty($property)) {
    define('PAGE_TITLE', '<a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'">[Wikidata] Related Properties</a> to <em>'.htmlentities($property->label).'</em> (P'.$property->id.')');
} else {
    define('PAGE_TITLE', '<a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'">[Wikidata] Related Properties</a>');
}

require '../../inc/header.inc.php';

if (!empty($property)) {

echo '<p>As of <strong>'.htmlentities(parameter::get('properties_dump')).'</strong>, the Wikidata property <strong><em>'.htmlentities($property->label).'</em></strong> (<a href="https://www.wikidata.org/wiki/Property:P'.$property->id.'">P'.$property->id.'</a>) is used on '.number_format($property->cardinality).' items.</p>';

echo '<p>Closest properties: <a href="#intersection_cardinality">by cardinality of intersection</a>, <a href="#jaccard_index">by Jaccard index</a>.</p>';

properties_filter_form($types, $property, $selection);

$max = null;
echo '<div class="blob"><h2 id="intersection_cardinality">Closest properties by cardinality of intersection</h2>
<table class="p"><tr><th class="data">ID</th><th>Label</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `properties`.`propertyB`, `pB`.`label` AS `labelB`, `properties`.`intersection_cardinality`, `properties`.`jaccard_index` FROM `properties`, `property` `pB` WHERE `properties`.`propertyA` = '.$property->id.' AND `properties`.`propertyB` = `pB`.`id`'.(!empty($selectionSQL) ? ' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `properties`.`intersection_cardinality` DESC, `properties`.`jaccard_index` DESC, `properties`.`propertyB` ASC LIMIT 50');
while ($row = $res->fetch_object()) {
    if ($max === null): $max = $row->intersection_cardinality; endif;
    echo '<tr>
        <td class="data"><a href="https://www.wikidata.org/wiki/Property:P'.$row->propertyB.'">P'.$row->propertyB.'</a></td>
        <td class="label"><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'?property='.$row->propertyB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table></div>';

// $max from previous table
echo '<div class="blob"><h2 id="jaccard_index">Closest properties by Jaccard index</h2>
<table class="p"><th class="data">ID</th><th>Label</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `properties`.`propertyB`, `pB`.`label` AS `labelB`, `properties`.`intersection_cardinality`, `properties`.`jaccard_index` FROM `properties`, `property` `pB` WHERE `properties`.`propertyA` = '.$property->id.' AND `properties`.`propertyB` = `pB`.`id`'.(!empty($selectionSQL) ? ' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `properties`.`jaccard_index` DESC, `properties`.`intersection_cardinality` DESC, `properties`.`propertyB` ASC LIMIT 50');
while ($row = $res->fetch_object()) {
    echo '<tr>
        <td class="data"><a href="https://www.wikidata.org/wiki/Property:P'.$row->propertyB.'">P'.$row->propertyB.'</a></td>
        <td class="label"><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'?property='.$row->propertyB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table></div>';

}
elseif (empty($_GET['property'])) {

echo '<p>The tool <strong><em>[Wikidata] Related Properties</em></strong> provides statistics about the usage of properties in Wikidata items, as main properties of statements (not as qualifiers or in references), and the relations between them.</p><p>As of <strong>'.htmlentities(parameter::get('properties_dump')).'</strong>, Wikidata counts <strong>'.number_format(db::query('SELECT COUNT(*) AS `count` FROM `property`')->fetch_object()->count).'</strong> properties in use.</p>';

echo '<p><a href="#most_used">Most used properties</a> | Closest properties: <a href="#intersection_cardinality">by cardinality of intersection</a>, <a href="#jaccard_index">by Jaccard index</a> | <a href="#download">Downloads</a></p>';

properties_filter_form($types, $property, $selection);

$max = null;
echo '<h2 id="most_used">Most used properties</h2>
<table class="p"><tr><th class="data">ID</th><th>Label</th><th class="data">Cardinality</th></tr>';
$res = db::query('SELECT `id`, `label`, `cardinality` FROM `property`'.(!empty($selectionSQL) ? ' WHERE `type` '.$selectionSQL : '').' ORDER BY `cardinality` DESC LIMIT 50');
while ($row = $res->fetch_object()) {
    if ($max === null): $max = $row->cardinality; endif;
    echo '<tr>
        <td class="data"><a href="https://www.wikidata.org/wiki/Property:P'.$row->id.'">P'.$row->id.'</a></td>
        <td class="label"><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'?property='.$row->id.$selectionURL.'">'.htmlentities($row->label).'</a></td>
        <td class="data">'.number_format($row->cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->cardinality).'%" class="percent" /></td>
    </tr>';
}
echo '</table>';

$max = null;
echo '<h2 id="intersection_cardinality">Closest properties by cardinality of intersection</h2>
<table class="p"><tr><th class="data">ID</th><th>Label</th><th class="data">ID</th><th>Label</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `properties`.`propertyA`, `pA`.`label` AS `labelA`, `properties`.`propertyB`, `pB`.`label` AS `labelB`, `properties`.`intersection_cardinality`, `properties`.`jaccard_index` FROM `properties`, `property` `pA`, `property` `pB` WHERE `properties`.`propertyA` = `pA`.`id` AND `properties`.`propertyB` = `pB`.`id` AND `properties`.`propertyA` < `properties`.`propertyB`'.(!empty($selectionSQL) ? ' AND `pA`.`type` '.$selectionSQL.' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `properties`.`intersection_cardinality` DESC, `properties`.`jaccard_index` DESC, `properties`.`propertyA` ASC, `properties`.`propertyB` ASC LIMIT 50');
while ($row = $res->fetch_object()) {
    if ($max === null): $max = $row->intersection_cardinality; endif;
    echo '<tr>
        <td class="data"><a href="https://www.wikidata.org/wiki/Property:P'.$row->propertyA.'">P'.$row->propertyA.'</a></td>
        <td class="label"><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'?property='.$row->propertyA.$selectionURL.'">'.htmlentities($row->labelA).'</a></td>
        <td class="data"><a href="https://www.wikidata.org/wiki/Property:P'.$row->propertyB.'">P'.$row->propertyB.'</a></td>
        <td class="label"><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'?property='.$row->propertyB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table>';

// $max from previous table
echo '<h2 id="jaccard_index">Closest properties by Jaccard index</h2>
<table class="p"><tr><th class="data">ID</th><th>Label</th><th class="data">ID</th><th>Label</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `properties`.`propertyA`, `pA`.`label` AS `labelA`, `properties`.`propertyB`, `pB`.`label` AS `labelB`, `properties`.`intersection_cardinality`, `properties`.`jaccard_index` FROM `properties`, `property` `pA`, `property` `pB` WHERE `properties`.`propertyA` = `pA`.`id` AND `properties`.`propertyB` = `pB`.`id` AND `properties`.`propertyA` < `properties`.`propertyB`'.(!empty($selectionSQL) ? ' AND `pA`.`type` '.$selectionSQL.' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `properties`.`jaccard_index` DESC, `properties`.`intersection_cardinality` DESC, `properties`.`propertyA` ASC, `properties`.`propertyB` ASC LIMIT 50');
while ($row = $res->fetch_object()) {
    echo '<tr>
        <td class="data"><a href="https://www.wikidata.org/wiki/Property:P'.$row->propertyA.'">P'.$row->propertyA.'</a></td>
        <td class="label"><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'?property='.$row->propertyA.$selectionURL.'">'.htmlentities($row->labelA).'</a></td>
        <td class="data"><a href="https://www.wikidata.org/wiki/Property:P'.$row->propertyB.'">P'.$row->propertyB.'</a></td>
        <td class="label"><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'?property='.$row->propertyB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table>';

echo '<h2 id="download">Downloads</h2>
<ul>
    <li><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'data/properties_'.htmlentities(parameter::get('properties_dump')).'.csv">All Wikidata properties with their cardinalities</a> (CSV file)</li>
    <li><a href="'.SITE_DIR.PROPERTIES_SITE_DIR.'data/properties_relations_'.htmlentities(parameter::get('properties_dump')).'.csv">All relations between Wikidata properties with their intersection cardinalities and Jaccard indexes</a> (CSV file)</li>
</ul>';

}
else {
    echo '<p><strong>P'.htmlentities($_GET['property']).'</strong> is unknown.</p>';
}

require '../../inc/footer.inc.php';

?>