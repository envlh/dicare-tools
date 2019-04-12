<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.BATEAUX_SITE_DIR.'">Listes de bateaux</a>');
page::addJs('sorttable/sorttable.js');

require '../../inc/header.inc.php';

echo '<form method="post" action="'.SITE_DIR.BATEAUX_SITE_DIR.'">
<p>Cet outil génère, à partir des données <a href="https://www.wikidata.org/">Wikidata</a>, des listes de bateaux (instances, ou instances sous-classe, d\'<em><a href="https://www.wikidata.org/wiki/Q1229765">embarcation</a></em>) selon différents critères. Au moins un critère doit être utilisé.</p>
<p><label for="id">Identifiant Wikidata d\'un évènement auquel le bateau a participé</label> (exemple : Q20971895 pour <em><a href="https://www.wikidata.org/wiki/Q20971895">Brest 2016</a></em>) :<br /><input type="text" id="id" name="id" value="'.htmlentities(page::getParameter('id')).'" /></p>
<p>
    <input type="checkbox" name="bip" id="bip" value="true"'.(page::getParameter('bip') === 'true' ? ' checked="checked"' : '').' /> <label for="bip">Bateaux d\'intérêt patrimonial</label><br />
    <input type="checkbox" name="palissy" id="palissy" value="true"'.(page::getParameter('palissy') === 'true' ? ' checked="checked"' : '').' /> <label for="palissy">Monuments historiques</label><br />
</p>
<p><input type="submit" value="Lister" /></p>
</form>';

$id = null;
if (!empty($_POST['id']) && preg_match('/^Q[0-9]+$/', $_POST['id'])) {
    $id = $_POST['id'];
}

$bip = false;
if (!empty($_POST['bip']) && ($_POST['bip'] == 'true')) {
    $bip = true;
}

$palissy = false;
if (!empty($_POST['palissy']) && ($_POST['palissy'] == 'true')) {
    $palissy = true;
}

if (($id != null) || $bip || $palissy) {
    
    $query = '
SELECT ?item ?itemLabel
(GROUP_CONCAT(DISTINCT ?imo ; SEPARATOR = ",") AS ?imo)
(GROUP_CONCAT(DISTINCT ?mmsi ; SEPARATOR = ",") AS ?mmsi)
(GROUP_CONCAT(DISTINCT ?bip ; SEPARATOR = ",") AS ?bip)
(GROUP_CONCAT(DISTINCT ?palissy ; SEPARATOR = ",") AS ?palissy)
(GROUP_CONCAT(DISTINCT ?registration ; SEPARATOR = ",") AS ?registration)
(GROUP_CONCAT(DISTINCT ?pennant ; SEPARATOR = ",") AS ?pennant)
(GROUP_CONCAT(DISTINCT ?callsign ; SEPARATOR = ",") AS ?callsign)
(GROUP_CONCAT(DISTINCT ?commons ; SEPARATOR = " ; ") AS ?commons)
(GROUP_CONCAT(DISTINCT ?website ; SEPARATOR = " ; ") AS ?website)
WHERE {
    ?item wdt:P31/wdt:P279* wd:Q1229765 .'.(($id != null) ? '
    ?item wdt:P1344 wd:'.$id.' .' : '').'
    OPTIONAL { ?item wdt:P458 ?imo . }
    OPTIONAL { ?item wdt:P587 ?mmsi . }
    '.(!$bip ? 'OPTIONAL { ' : '').'?item wdt:P2952 ?bip .'.(!$bip ? ' }' : '').'
    '.(!$palissy ? 'OPTIONAL { ' : '').'?item wdt:P481 ?palissy .'.(!$palissy ? ' }' : '').'
    OPTIONAL { ?item wdt:P2802 ?registration . }
    OPTIONAL { ?item wdt:P879 ?pennant . }
    OPTIONAL { ?item wdt:P2317 ?callsign . }
    OPTIONAL { ?commons schema:about ?item ; schema:isPartOf <https://commons.wikimedia.org/> . }
    OPTIONAL { ?item wdt:P856 ?website . }
    SERVICE wikibase:label { bd:serviceParam wikibase:language "fr" . }
}
GROUP BY ?item ?itemLabel
ORDER BY ?itemLabel
';
    
    $items = wdqs::query($query);
    
    echo '<h2>'.count($items->results->bindings).' résultats [<a href="'.SITE_DIR.BATEAUX_SITE_DIR.'?id='.urlencode(page::getParameter('id')).'&amp;bip='.($bip ? 'true' : 'false').'&amp;palissy='.($palissy ? 'true' : 'false').'">Permalien</a>]</h2>';
    if (count($items->results->bindings) === 0) {
        echo '<p>Aucun résultat.</p>';
    }
    else {
        echo '<table class="sortable">
<thead><tr><th>Libellé</th><th><abbr title="International Maritime Organization">IMO</abbr></th><th><abbr title="Maritime Mobile Service Identity">MMSI</abbr></th><th><abbr title="Bateau d\'intérêt patrimonial">BIP</abbr></th><th><abbr title="Palissy">Pal.</abbr></th><th><abbr title="Immatriculation">Imm.</abbr></th><th><abbr title="Fanion">Fan.</abbr></th><th><abbr title="Radio">Rad.</a></th><th>Commons</th><th>Site web</th></tr>
<tbody>'."\n";
        foreach ($items->results->bindings as $item) {
            echo '<tr>';
            
            $itemId = substr($item->item->value, 32);
            echo '<td><a href="https://www.wikidata.org/wiki/Q'.$itemId.'">';
            if (!empty($item->itemLabel->value)) {
                echo htmlentities($item->itemLabel->value);
            } else {
                echo 'Q'.$itemId;
            }
            echo '</a></td>';
            
            if (empty($item->imo->value)) {
                $imo = '';
            } else {
                $imo = explode(',', $item->imo->value);
                sort($imo, SORT_NUMERIC);
                foreach ($imo as &$val) {
                    $val = '<a href="https://www.marinetraffic.com/ais/details/ships/'.urlencode($val).'">'.htmlentities($val).'</a>';
                }
                $imo = implode(', ', $imo);
            }
            echo '<td>'.$imo.'</td>';
            
            if (empty($item->mmsi->value)) {
                $mmsi = '';
            } else {
                $mmsi = explode(',', $item->mmsi->value);
                sort($mmsi, SORT_NUMERIC);
                foreach ($mmsi as &$val) {
                    $val = '<a href="https://www.marinetraffic.com/ais/details/ships/'.urlencode($val).'">'.htmlentities($val).'</a>';
                }
                $mmsi = implode(', ', $mmsi);
            }
            echo '<td>'.$mmsi.'</td>';
            
            if (empty($item->bip->value)) {
                $bip = '';
            } else {
                $bip = explode(',', $item->bip->value);
                sort($bip, SORT_NUMERIC);
                foreach ($bip as &$val) {
                    $val = '<a href="http://www.patrimoine-maritime-fluvial.org/BDDBIP/Public/ConsPublic.php?fiche='.urlencode($val).'">'.htmlentities($val).'</a>';
                }
                $bip = implode(', ', $bip);
            }
            echo '<td>'.$bip.'</td>';
            
            if (empty($item->palissy->value)) {
                $palissy = '';
            } else {
                $palissy = explode(',', $item->palissy->value);
                sort($palissy, SORT_STRING);
                foreach ($palissy as &$val) {
                    $val = '<a href="http://www.culture.gouv.fr/public/mistral/palissy_fr?ACTION=CHERCHER&FIELD_1=REF&amp;VALUE_1='.urlencode($val).'">'.htmlentities($val).'</a>';
                }
                $palissy = implode(', ', $palissy);
            }
            echo '<td>'.$palissy.'</td>';
            
            if (empty($item->registration->value)) {
                $registration = '';
            } else {
                $registration = explode(',', $item->registration->value);
                foreach ($registration as &$val) {
                    $val = preg_replace('/[^A-Z0-9]/', '', strtoupper($val));
                }
                sort($registration, SORT_STRING);
                $registration = implode(', ', $registration);
            }
            echo '<td>'.htmlentities($registration).'</td>';
            
            if (empty($item->pennant->value)) {
                $pennant = '';
            } else {
                $pennant = explode(',', $item->pennant->value);
                foreach ($pennant as &$val) {
                    $val = preg_replace('/[^A-Z0-9]/', '', strtoupper($val));
                }
                sort($pennant, SORT_STRING);
                $pennant = implode(', ', $pennant);
            }
            echo '<td>'.htmlentities($pennant).'</td>';
            
            if (empty($item->callsign->value)) {
                $callsign = '';
            } else {
                $callsign = explode(',', $item->callsign->value);
                sort($callsign, SORT_STRING);
                $callsign = implode(', ', $callsign);
            }
            echo '<td>'.htmlentities($callsign).'</td>';
            
            if (empty($item->commons->value)) {
                $commons = '';
            } else {
                $commons = explode(' ; ', $item->commons->value);
                sort($commons, SORT_STRING);
                foreach ($commons as &$val) {
                    $val = substr($val, 44);
                    $val = '<a href="https://commons.wikimedia.org/wiki/Category:'.str_replace(' ', '_', urldecode($val)).'">'.htmlentities(str_replace('_', ' ', urldecode($val))).'</a>';
                }
                $commons = implode(', ', $commons);
            }
            echo '<td>'.$commons.'</td>';
            
            if (empty($item->website->value)) {
                $website = '';
            } else {
                $website = explode(' ; ', $item->website->value);
                sort($website, SORT_STRING);
                foreach ($website as &$val) {
                    $val = '<a href="'.urldecode($val).'">'.htmlentities(preg_replace('/\\/$/', '', preg_replace('/^https?\\:\\/\\/(www\\.)?/i', '', urldecode($val)))).'</a>';
                }
                $website = implode(', ', $website);
            }
            echo '<td>'.$website.'</td>';
            
            echo '</tr>'."\n";
        }
        echo '</tbody></table>';
    }
    
}

wdqs::displayQueries();

require '../../inc/footer.inc.php';

?>