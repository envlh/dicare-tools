<?php

require '../inc/load.inc.php';

function guessProjectType($project) {
    switch ($project->code->value) {
        case 'commonswiki':
            return 'commons';
        break;
        case 'incubatorwiki':
            return 'incubator';
        break;
        case 'mediawikiwiki':
            return 'mediawiki';
        break;
        case 'metawiki':
            return 'meta';
        break;
        case 'specieswiki':
            return 'wikispecies';
        break;
        case 'wikidatawiki':
            return 'wikidata';
        break;
        case 'wikimaniawiki':
            return 'wikimania';
        break;
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q22001316') !== false) {
        return 'wikibooks';
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q20671729') !== false) {
        return 'wikinews';
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q10876391') !== false) {
        return 'wikipedia';
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q22001361') !== false) {
        return 'wikiquote';
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q15156455') !== false) {
        return 'wikisource';
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q22001390') !== false) {
        return 'wikiversity';
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q19826567') !== false) {
        return 'wikivoyage';
    }
    if (strpos($project->instanceOf->value, 'http://www.wikidata.org/entity/Q22001389') !== false) {
        return 'wiktionary';
    }
    throw new Exception('Unknown project type for "'.$project->code->value.'".');
}

# data from dump

db::query('TRUNCATE TABLE `import_project`');
db::query('LOAD DATA LOCAL INFILE \'/tmp/project.csv\' INTO TABLE `import_project` FIELDS TERMINATED BY \',\'');

db::query('TRUNCATE TABLE `import_projects`');
db::query('LOAD DATA LOCAL INFILE \'/tmp/projects.csv\' INTO TABLE `import_projects` FIELDS TERMINATED BY \',\'');

$dump = file_get_contents('/tmp/dump.csv');
parameter::set('projects_dump', $dump);

# data processing

db::query('DELETE FROM `project`');
db::query('DELETE FROM `projects`');

$projects = wdqs::query('SELECT ?item ?label ?code (GROUP_CONCAT(DISTINCT ?instanceOf) AS ?instanceOf) (GROUP_CONCAT(DISTINCT ?url) AS ?url) { ?item wdt:P31 ?instanceOf ; wdt:P856 ?url ; wdt:P1800 ?code ; rdfs:label ?label . FILTER(LANG(?label) = "en") . } GROUP BY ?item ?label ?code', 0);
foreach ($projects->results->bindings as $project) {
    $project_code = $project->code->value;
    $res = db::query('SELECT `cardinality` FROM `import_project` WHERE `code` = \''.db::sec($project_code).'\'');
    if ($res->num_rows === 1) {
        $cardinality = $res->fetch_object()->cardinality;
        db::query('INSERT INTO `project`(`code`, `type`, `label`, `url`, `cardinality`) VALUES(\''.db::sec($project_code).'\', \''.db::sec(guessProjectType($project)).'\', \''.db::sec($project->label->value).'\', \''.db::sec($project->url->value).'\', '.$cardinality.')');
    }
}

db::query('INSERT INTO `projects` SELECT `import_projects`.`projectA`, `import_projects`.`projectB`, `import_projects`.`intersection_cardinality`, (`import_projects`.`intersection_cardinality` / (`pA`.`cardinality` + `pB`.`cardinality` - `import_projects`.`intersection_cardinality`)) FROM `import_projects`, `project` AS `pA`, `project` AS `pB` WHERE `import_projects`.`projectA` = `pA`.`code` AND `import_projects`.`projectB` = `pB`.`code`');
// reverse
db::query('INSERT INTO `projects` SELECT `projectB`, `projectA`, `intersection_cardinality`, `jaccard_index` FROM `projects`');

# data export to CSV files

$data = 'code,type,cardinality'."\n";
$res = db::query('SELECT `code`, `type`, `cardinality` FROM `project` ORDER BY `code`');
while ($row = $res->fetch_object()) {
    $data .= $row->code.','.$row->type.','.$row->cardinality."\n";
}
file_put_contents('../www/projects/data/projects_'.$dump.'.csv', $data);

$data = 'projectA,projectB,intersection_cardinality,jaccard_index'."\n";
$res = db::query('SELECT `projectA`, `projectB`, `intersection_cardinality`, `jaccard_index` FROM `projects` WHERE `projectA` < `projectB` ORDER BY `projectA` ASC, `projectB` ASC');
while ($row = $res->fetch_object()) {
    $data .= $row->projectA.','.$row->projectB.','.$row->intersection_cardinality.','.$row->jaccard_index."\n";
}
file_put_contents('../www/projects/data/projects_relations_'.$dump.'.csv', $data);

# commit

db::commit();

?>