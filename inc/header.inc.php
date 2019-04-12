<?php

require_once __DIR__.'/load.inc.php';

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php $title = htmlentities(strip_tags(PAGE_TITLE)); echo $title; if ($title != 'Dicare Tools'): echo ' &mdash; Dicare Tools'; endif; ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo SITE_STATIC_DIR; ?>css/common.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="<?php echo $title; ?>" />
    <meta name="twitter:description" content="Some Dicare tool that does something." />
    <meta name="twitter:image" content="<?php echo SITE_STATIC_DIR; ?>img/logo.jpg" />
    <meta property="og:title" content="<?php echo $title; ?>" />
    <meta property="og:description" content="Some Dicare tool that does something." />
    <meta property="og:image" content="<?php echo SITE_STATIC_DIR; ?>img/logo.jpg" />
    <?php echo page::displayCss(); ?>
    <?php echo page::displayJs(); ?>
</head>
<body>
<div id="header">
    <h1><?php echo PAGE_TITLE; ?></h1>
</div>
<?php echo page::displayMenu(); ?>
<div id="content">
