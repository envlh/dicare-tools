<?php

require '../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.'">Dicare Tools</a>');

require '../inc/header.inc.php';

?>

<p><strong>Dicare Tools</strong> are a set of tools for contributing and gather statistics about Wikimedia projects.</p>

<h2>Tools list</h2>
<ul>
<li><span class="language">[en]</span> <strong><a href="<?php echo SITE_DIR.TRANSLATHON_SITE_DIR; ?>">Transl-a-thon</a></strong>: find items needing to be translated in Wikidata</li>
<li><span class="language">[en]</span> <strong><a href="<?php echo SITE_DIR.PROPERTIES_SITE_DIR; ?>">[Wikidata] Related Properties</a></strong>: explore Wikidata properties and their related siblings</li>
<li><span class="language">[en]</span> <strong><a href="<?php echo SITE_DIR.PROJECTS_SITE_DIR; ?>">[Wikimedia] Related Projects</a></strong>: find which projects are the closest to each other, given their sitelinks usage</li>
<li><span class="language">[en]</span> <strong><a href="<?php echo SITE_DIR.DIFF_SITE_DIR; ?>">Wikidata Diff</a></strong>: compare the properties of two Wikidata items</li>
<li><span class="language">[en]</span> <strong><a href="<?php echo SITE_DIR.DD_SITE_DIR; ?>">Dedicated Dashboard</a></strong>: another tool to improve the data quality in Wikidata</li>
<li><span class="language">[en]</span> <strong><a href="<?php echo SITE_DIR.BNF_SITE_DIR; ?>">BnF To Wikidata</a></strong>: import sourced data from BnF into Wikidata</li>
<li><span class="language">[en]</span> <strong><a href="<?php echo SITE_DIR.IDREF_SITE_DIR; ?>">IdRef To Wikidata</a></strong>: import sourced data from IdRef into Wikidata</li>
<li><span class="language">[fr]</span> <strong><a href="<?php echo SITE_DIR.DEPUTES_SITE_DIR; ?>">Députés</a></strong> : statistiques et listes de députés français de la 5<sup>e</sup> République</li>
<li><span class="language">[fr]</span> <strong><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>">Noms</a></strong>
    <ul>
        <li><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>homonymie.php">Génération d'une page d'homonymie pour la Wikipédia en français</a></li>
        <li><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>nom-de-famille.php">Ajout en masse d'un nom de famille sur Wikidata</a></li>
        <li><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>prenom.php">Ajout en masse d'un prénom sur Wikidata</a></li>
        <li><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>suggestions.php">Suggestion de noms de famille manquants dans Wikidata</a></li>
        <li><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>pays.php">Statistiques par pays sur les noms de famille et les prénoms</a></li>
        <li><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>departements.php">Statistiques par département français sur les noms de famille et les prénoms</a></li>
    </ul>
</li>
<li><span class="language">[fr]</span> <strong><a href="<?php echo SITE_DIR.BATEAUX_SITE_DIR; ?>">Listes de bateaux</a></strong></li>
</ul>

<h2>About</h2>
<p>You can send me an email at the following address:<br />webmaster -at- dicare.org</p>

<?php

require '../inc/footer.inc.php';

?>