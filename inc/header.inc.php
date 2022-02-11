<?php

require_once __DIR__.'/load.inc.php';

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php $title = htmlentities(strip_tags(PAGE_TITLE)); echo $title; if ($title != 'Dicare Tools'): echo ' &mdash; Dicare Tools'; endif; ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo SITE_STATIC_DIR; ?>css/common.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php echo page::displayCss(); ?>
    <?php echo page::displayJs(); ?>
    <?php echo page::displayCard($title); ?>
</head>
<body>
<div id="header">
    <h1><?php echo PAGE_TITLE; ?></h1>
</div>
<?php echo page::displayMenu(); ?>
<div id="content">
