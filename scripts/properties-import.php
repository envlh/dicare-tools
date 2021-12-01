<?php

require '../inc/load.inc.php';

# data from dump

db::query('TRUNCATE TABLE `import_property`');
db::query('LOAD DATA LOCAL INFILE \''.DICARE_TMP_DIR.'property.csv\' INTO TABLE `import_property` FIELDS TERMINATED BY \',\'');

db::query('TRUNCATE TABLE `import_properties`');
db::query('LOAD DATA LOCAL INFILE \''.DICARE_TMP_DIR.'properties.csv\' INTO TABLE `import_properties` FIELDS TERMINATED BY \',\'');

$dump = file_get_contents(DICARE_TMP_DIR.'dump.csv');
parameter::set('properties_dump', $dump);

# data processing

db::query('DELETE FROM `property`');
db::query('DELETE FROM `properties`');

$properties = wdqs::query('SELECT DISTINCT ?property ?propertyLabel ?propertyType { ?property wdt:P31/wdt:P279* wd:Q18616576 ; wikibase:propertyType ?propertyType . SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . } }', 0);
foreach ($properties->results->bindings as $property) {
    $property_id = substr($property->property->value, 32);
    $res = db::query('SELECT `cardinality` FROM `import_property` WHERE `id` = '.$property_id);
    if ($res->num_rows === 1) {
        $cardinality = $res->fetch_object()->cardinality;
        db::query('INSERT INTO `property`(`id`, `type`, `label`, `cardinality`) VALUES('.$property_id.', \''.db::sec(substr($property->propertyType->value, 26)).'\', \''.db::sec($property->propertyLabel->value).'\', '.$cardinality.')');
    }
}

db::query('INSERT INTO `properties` SELECT `import_properties`.`propertyA`, `import_properties`.`propertyB`, `import_properties`.`intersection_cardinality`, (`import_properties`.`intersection_cardinality` / (`pA`.`cardinality` + `pB`.`cardinality` - `import_properties`.`intersection_cardinality`)) FROM `import_properties`, `property` AS `pA`, `property` AS `pB` WHERE `import_properties`.`propertyA` = `pA`.`id` AND `import_properties`.`propertyB` = `pB`.`id`');
// reverse
db::query('INSERT INTO `properties` SELECT `propertyB`, `propertyA`, `intersection_cardinality`, `jaccard_index` FROM `properties`');

# data export to CSV files

$data = 'id,type,cardinality'."\n";
$res = db::query('SELECT `id`, `type`, `cardinality` FROM `property` ORDER BY `id`');
while ($row = $res->fetch_object()) {
    $data .= 'P'.$row->id.','.$row->type.','.$row->cardinality."\n";
}
file_put_contents('../www/properties/data/properties_'.$dump.'.csv', $data);

$data = 'propertyA,propertyB,intersection_cardinality,jaccard_index'."\n";
$res = db::query('SELECT `propertyA`, `propertyB`, `intersection_cardinality`, `jaccard_index` FROM `properties` WHERE `propertyA` < `propertyB` ORDER BY `propertyA` ASC, `propertyB` ASC');
while ($row = $res->fetch_object()) {
    $data .= 'P'.$row->propertyA.',P'.$row->propertyB.','.$row->intersection_cardinality.','.$row->jaccard_index."\n";
}
file_put_contents('../www/properties/data/properties_relations_'.$dump.'.csv', $data);

# commit

db::commit();

?>