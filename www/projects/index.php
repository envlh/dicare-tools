<?php

require '../../inc/load.inc.php';

$http_404 = false;

$project = null;
if (!empty($_GET['project'])) {
    if (!preg_match('/^[a-z_]+$/', $_GET['project'])) {
        $http_404 = true;
    } else {
        $res = db::query('SELECT `code`, `type`, `label`, `url`, `cardinality` FROM `project` WHERE `code` = \''.db::sec($_GET['project']).'\'');
        if ($res->num_rows !== 1) {
            $http_404 = true;
        } else {
            $project = $res->fetch_object();
        }
    }
}

$types = array();
$res = db::query('SELECT DISTINCT `type` FROM `project` ORDER BY `type`');
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

function projects_filter_form($types, $project, $selection) {
    echo '<form action="'.SITE_DIR.PROJECTS_SITE_DIR.'" method="get"><p>';
    if (!empty($project)) {
        echo '<input type="hidden" name="project" value="'.$project->code.'" />';
    }
    echo 'Display projects of the following types:&nbsp;&nbsp;';
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

if (!empty($project)) {
    define('PAGE_TITLE', '<a href="'.SITE_DIR.PROJECTS_SITE_DIR.'">[Wikimedia] Related projects</a> to <em>'.htmlentities($project->label).'</em> ('.$project->code.')');
} else {
    define('PAGE_TITLE', '<a href="'.SITE_DIR.PROJECTS_SITE_DIR.'">[Wikimedia] Related projects</a>');
}

require '../../inc/header.inc.php';

if (!empty($project)) {

echo '<p>As of <strong>'.htmlentities(parameter::get('projects_dump')).'</strong>, the Wikimedia project <strong><em>'.htmlentities($project->label).'</em></strong> <a href="'.htmlentities($project->url).'" title="'.$project->code.'" class="p"><img src="'.SITE_STATIC_DIR.'img/logo-'.$project->type.'.png" alt="" class="logo" /></a> has sitelinks on '.number_format($project->cardinality).' pages.</p>';

echo '<p>Closest projects: <a href="#intersection_cardinality">by cardinality of intersection</a>, <a href="#jaccard_index">by Jaccard index</a>.</p>';

projects_filter_form($types, $project, $selection);

$max = null;
echo '<div class="blob"><h2 id="intersection_cardinality">Closest projects by cardinality of intersection</h2>
<table class="p"><tr><th>Label</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `projects`.`projectB`, `pB`.`code` AS `codeB`, `pB`.`type` AS `typeB`, `pB`.`label` AS `labelB`, `pB`.`url` AS `urlB`, `projects`.`intersection_cardinality`, `projects`.`jaccard_index` FROM `projects`, `project` `pB` WHERE `projects`.`projectA` = \''.db::sec($project->code).'\' AND `projects`.`projectB` = `pB`.`code`'.(!empty($selectionSQL) ? ' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `projects`.`intersection_cardinality` DESC, `projects`.`jaccard_index` DESC, `projects`.`projectB` ASC LIMIT 100');
while ($row = $res->fetch_object()) {
    if ($max === null): $max = $row->intersection_cardinality; endif;
    echo '<tr>
        <td class="label"><a href="'.htmlentities($row->urlB).'" title="'.$row->codeB.'"><img src="'.SITE_STATIC_DIR.'img/logo-'.$row->typeB.'.png" alt="" class="logo" /></a> <a href="'.SITE_DIR.PROJECTS_SITE_DIR.'?project='.$row->projectB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table></div>';

// $max from previous table
echo '<div class="blob"><h2 id="jaccard_index">Closest projects by Jaccard index</h2>
<table class="p"><th>Label</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `projects`.`projectB`, `pB`.`code` AS `codeB`, `pB`.`type` AS `typeB`, `pB`.`label` AS `labelB`, `pB`.`url` AS `urlB`, `projects`.`intersection_cardinality`, `projects`.`jaccard_index` FROM `projects`, `project` `pB` WHERE `projects`.`projectA` = \''.db::sec($project->code).'\' AND `projects`.`projectB` = `pB`.`code`'.(!empty($selectionSQL) ? ' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `projects`.`jaccard_index` DESC, `projects`.`intersection_cardinality` DESC, `projects`.`projectB` ASC LIMIT 100');
while ($row = $res->fetch_object()) {
    echo '<tr>
        <td class="label"><a href="'.htmlentities($row->urlB).'" title="'.$row->codeB.'"><img src="'.SITE_STATIC_DIR.'img/logo-'.$row->typeB.'.png" alt="" class="logo" /></a> <a href="'.SITE_DIR.PROJECTS_SITE_DIR.'?project='.$row->projectB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table></div>';

}
elseif (empty($_GET['project'])) {

echo '<p>The tool <strong><em>[Wikimedia] Related projects</em></strong> provides statistics about Wikimedia projects and the relations between them, using the number of sitelinks they have in common.</p><p>As of <strong>'.htmlentities(parameter::get('projects_dump')).'</strong>, Wikimedia counts <strong>'.number_format(db::query('SELECT COUNT(*) AS `count` FROM `project`')->fetch_object()->count).'</strong> projects with sitelinks stored on Wikidata.</p>';

echo '<p><a href="#most_used">Projects with the most sitelinks</a> | Closest projects: <a href="#intersection_cardinality">by cardinality of intersection</a>, <a href="#jaccard_index">by Jaccard index</a> | <a href="#download">Downloads</a></p>';

projects_filter_form($types, $project, $selection);

$max = null;
echo '<h2 id="most_used">Projects with the most sitelinks</h2>
<table class="p"><tr><th>Project</th><th class="data">Cardinality</th></tr>';
$res = db::query('SELECT `code`, `type`, `label`, `url`, `cardinality` FROM `project`'.(!empty($selectionSQL) ? ' WHERE `type` '.$selectionSQL : '').' ORDER BY `cardinality` DESC LIMIT 100');
while ($row = $res->fetch_object()) {
    if ($max === null): $max = $row->cardinality; endif;
    echo '<tr>
        <td class="label"><a href="'.htmlentities($row->url).'" title="'.$row->code.'"><img src="'.SITE_STATIC_DIR.'img/logo-'.$row->type.'.png" alt="" class="logo" /></a> <a href="'.SITE_DIR.PROJECTS_SITE_DIR.'?project='.$row->code.$selectionURL.'">'.htmlentities($row->label).'</a></td>
        <td class="data">'.number_format($row->cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->cardinality).'%" class="percent" /></td>
    </tr>';
}
echo '</table>';

$max = null;
echo '<h2 id="intersection_cardinality">Closest projects by cardinality of intersection</h2>
<table class="p"><tr><th>Project</th><th>Project</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `projects`.`projectA`, `pA`.`code` AS `codeA`, `pA`.`type` AS `typeA`, `pA`.`label` AS `labelA`, `pA`.`url` AS `urlA`, `projects`.`projectB`, `pB`.`code` AS `codeB`, `pB`.`type` AS `typeB`, `pB`.`label` AS `labelB`, `pB`.`url` AS `urlB`, `projects`.`intersection_cardinality`, `projects`.`jaccard_index` FROM `projects`, `project` `pA`, `project` `pB` WHERE `projects`.`projectA` = `pA`.`code` AND `projects`.`projectB` = `pB`.`code` AND `projects`.`projectA` < `projects`.`projectB`'.(!empty($selectionSQL) ? ' AND `pA`.`type` '.$selectionSQL.' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `projects`.`intersection_cardinality` DESC, `projects`.`jaccard_index` DESC, `projects`.`projectA` ASC, `projects`.`projectB` ASC LIMIT 100');
while ($row = $res->fetch_object()) {
    if ($max === null): $max = $row->intersection_cardinality; endif;
    echo '<tr>
        <td class="label"><a href="'.htmlentities($row->urlA).'" title="'.$row->codeA.'"><img src="'.SITE_STATIC_DIR.'img/logo-'.$row->typeA.'.png" alt="" class="logo" /></a> <a href="'.SITE_DIR.PROJECTS_SITE_DIR.'?project='.$row->projectA.$selectionURL.'">'.htmlentities($row->labelA).'</a></td>
        <td class="label"><a href="'.htmlentities($row->urlB).'" title="'.$row->codeB.'"><img src="'.SITE_STATIC_DIR.'img/logo-'.$row->typeB.'.png" alt="" class="logo" /></a> <a href="'.SITE_DIR.PROJECTS_SITE_DIR.'?project='.$row->projectB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table>';

// $max from previous table
echo '<h2 id="jaccard_index">Closest projects by Jaccard index</h2>
<table class="p"><tr><th>Project</th><th>Project</th><th class="data">Intersection cardinality</th><th class="data">Jaccard index</th></tr>';
$res = db::query('SELECT `projects`.`projectA`, `pA`.`code` AS `codeA`, `pA`.`type` AS `typeA`, `pA`.`label` AS `labelA`, `pA`.`url` AS `urlA`, `projects`.`projectB`, `pB`.`code` AS `codeB`, `pB`.`type` AS `typeB`, `pB`.`label` AS `labelB`, `pB`.`url` AS `urlB`, `projects`.`intersection_cardinality`, `projects`.`jaccard_index` FROM `projects`, `project` `pA`, `project` `pB` WHERE `projects`.`projectA` = `pA`.`code` AND `projects`.`projectB` = `pB`.`code` AND `projects`.`projectA` < `projects`.`projectB`'.(!empty($selectionSQL) ? ' AND `pA`.`type` '.$selectionSQL.' AND `pB`.`type` '.$selectionSQL : '').' ORDER BY `projects`.`jaccard_index` DESC, `projects`.`intersection_cardinality` DESC, `projects`.`projectA` ASC, `projects`.`projectB` ASC LIMIT 100');
while ($row = $res->fetch_object()) {
    echo '<tr>
        <td class="label"><a href="'.htmlentities($row->urlA).'" title="'.$row->codeA.'"><img src="'.SITE_STATIC_DIR.'img/logo-'.$row->typeA.'.png" alt="" class="logo" /></a> <a href="'.SITE_DIR.PROJECTS_SITE_DIR.'?project='.$row->projectA.$selectionURL.'">'.htmlentities($row->labelA).'</a></td>
        <td class="label"><a href="'.htmlentities($row->urlB).'" title="'.$row->codeB.'"><img src="'.SITE_STATIC_DIR.'img/logo-'.$row->typeB.'.png" alt="" class="logo" /></a> <a href="'.SITE_DIR.PROJECTS_SITE_DIR.'?project='.$row->projectB.$selectionURL.'">'.htmlentities($row->labelB).'</a></td>
        <td class="data">'.number_format($row->intersection_cardinality).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8v5ThPwAG9AKluA28GQAAAABJRU5ErkJggg==" style="width: '.floor(100 / $max * $row->intersection_cardinality).'%" class="percent" /></td>
        <td class="data">'.number_format($row->jaccard_index, 4).'<br /><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkaGD4DwACiQGBU29HsgAAAABJRU5ErkJggg==" style="width: '.floor($row->jaccard_index * 100).'%" class="percent" /></td>
    </tr>';
}
echo '</table>';

echo '<h2 id="download">Downloads</h2>
<ul>
    <li><a href="'.SITE_DIR.PROJECTS_SITE_DIR.'data/projects_'.htmlentities(parameter::get('projects_dump')).'.csv">All Wikidata projects with their cardinalities</a> (CSV file)</li>
    <li><a href="'.SITE_DIR.PROJECTS_SITE_DIR.'data/projects_relations_'.htmlentities(parameter::get('projects_dump')).'.csv">All relations between Wikidata projects with their intersection cardinalities and Jaccard indexes</a> (CSV file)</li>
</ul>';

}
else {
    echo '<p><strong>The project '.htmlentities($_GET['project']).'</strong> is unknown.</p>';
}

require '../../inc/footer.inc.php';

?>