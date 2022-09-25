<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.CWIKIDAYS_SITE_DIR.'">#100wikidays</a>');

require '../../inc/header.inc.php';

$projects = array('wikipedia', 'wiktionary', 'wikiquote', 'wikinews', 'wikisource', 'wikibooks', 'wikiversity', 'wikivoyage', 'wikimedia', 'wikidata');
sort($projects);

$username = null;
if (!empty($_GET['username'])) {
    $username = trim($_GET['username']);
}

$prefix = 'en';
if (!empty($_GET['prefix']) && preg_match('/^[a-z]+(-[a-z]+)*$/', $_GET['prefix'])) {
    $prefix = $_GET['prefix'];
}

$project = 'wikipedia';
if (!empty($_GET['project']) && in_array($project, $projects)) {
    $project = $_GET['project'];
}

$namespace = 0;
if (!empty($_GET['namespace']) && preg_match('/^[1-9][0-9]*$/', $_GET['namespace'])) {
    $namespace = $_GET['namespace'];
}

$redirects = false;
if (!empty($_GET['redirects']) && ($_GET['redirects'] == 'true')) {
    $redirects = true;
}

$timezone = 'wiki';
$timezones = DateTimeZone::listIdentifiers();
if (!empty($_GET['timezone']) && (($_GET['timezone'] == 'UTC') || in_array($_GET['timezone'], $timezones))) {
    $timezone = $_GET['timezone'];
}

$limit = 500;
if (!empty($_GET['limit']) && preg_match('/^[1-9][0-9]*$/', $_GET['limit'])) {
    $limit = $_GET['limit'];
}

$challenge = new CWikiDays($username, $prefix, $project, $namespace, $redirects, $timezone, $limit);

echo '<h2>Search</h2>
<p>This tool helps you tracking your progress on the challenge <a href="https://meta.wikimedia.org/wiki/100wikidays">#100wikidays</a>.</p>';
$challenge->displayForm($projects);

if (!empty($username)) {
    echo '<h2>Results</h2>';
    try {
        $challenge->processData();
        $challenge->displayResults();
    } catch (Exception $e) {
        echo '<p>'.$e->getMessage().'</p>';
    }
}

require '../../inc/footer.inc.php';

?>