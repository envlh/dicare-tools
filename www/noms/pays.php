<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.NOMS_SITE_DIR.'pays.php">Statistiques par pays</a>');
page::addJs('sorttable/sorttable.js');
page::setMenu('noms');

require '../../inc/header.inc.php';

echo '<p>Le tableau ci-dessous donne, par pays de nationalité, le nombre de personnes ayant les propriétés <em>nom de famille</em> (<a href="https://www.wikidata.org/wiki/Property:P734">P734</a>) et <em>prénom</em> (<a href="https://www.wikidata.org/wiki/Property:P735">P735</a>) renseignées dans <a href="https://www.wikidata.org/">Wikidata</a>.</p>';

$countries = name::getStatsByCountry();

echo '<p>Données du '.date('d/m/Y à H:i:s', strtotime(wdqs::getQueryTime(wdqs::getQueries()[0]))).'.</p>';

echo '<table class="stats sortable">
<thead><tr><th class="label">Pays</th><th>Total de personnes</th><th>Avec nom de famille</th><th>Taux (%)</th><th class="ratio"></th><th>Avec prénom</th><th>Taux (%)</th></tr></thead>
<tbody>';
$total_total = 0;
$total_lastname = 0;
$total_firstname = 0;
foreach ($countries as $qid => &$value) {
    if ($value['total'] >= 1) {
        $total_total += $value['total'];
        $total_lastname += $value['lastname'];
        $total_firstname += $value['firstname'];
        $ratio_lastname = 100 / $value['total'] * $value['lastname'];
        $ratio_firstname = 100 / $value['total'] * $value['firstname'];
        echo '<tr><td class="label">'.htmlentities($value['label']).'</td><td>'.htmlentities(display::formatInt($value['total'])).'</td><td>'.htmlentities(display::formatInt($value['lastname'])).'</td><td>'.number_format($ratio_lastname, 1, ',', ' ').'</td><td class="ratio"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==" style="height: 12px; width:'.round($ratio_lastname * 2).'px;" /></td><td>'.htmlentities(display::formatInt($value['firstname'])).'</td><td>'.number_format($ratio_firstname, 1, ',', ' ').'</td></tr>'."\n";
    }
}
echo '</tbody>
<tfoot><tr><td class="label"><strong>Total</strong></td><td><strong>'.display::formatInt($total_total).'</strong></td><td><strong>'.display::formatInt($total_lastname).'</strong></td><td><strong>'.number_format(100 / $total_total * $total_lastname, 1, ',', ' ').'</strong></td><td></td><td><strong>'.display::formatInt($total_firstname).'</strong></td><td><strong>'.number_format(100 / $total_total * $total_firstname, 1, ',', ' ').'</strong></td></tr></tfoot>
</table>';

require '../../inc/footer.inc.php';

?>