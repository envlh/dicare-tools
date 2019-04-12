<?php

require '../../inc/load.inc.php';

$title = '<a href="'.SITE_DIR.DIFF_SITE_DIR.'">Wikidata Diff</a>';
$go = false;

$language = page::getParameter('language', 'en');
if (!empty($_GET['language']) && preg_match('/^[a-z]+(-[a-z]+)?$/', $_GET['language'])) {
    $language = $_GET['language'];
}

$qidsStr = 'Q3218809 Q2920825';
if (!empty($_GET['qids'])) {
    preg_match_all('/Q[1-9][0-9]*/', $_GET['qids'], $matches);
    $qids = $matches[0];
    if ((count($qids) == 2) && ($qids[0] != $qids[1])) {
        $go = true;
        $qidsStr = $qids[0].' '.$qids[1];
        $title .= ' <a href="'.SITE_DIR.DIFF_SITE_DIR.'?qids='.urlencode($qidsStr).'&amp;language='.urlencode($language).'">between '.$qids[0].' and '.$qids[1].'</a>';
    }
}

define('PAGE_TITLE', $title);

require '../../inc/header.inc.php';

echo '
<style type="text/css">
input#language { width: 60px; }
table { text-align: center; }
.p { text-align: right; }
.e { background: #DFD; }
.m { background: #FDD; }
.d { background: #FFD; }
tr:hover { background: transparent; }
.qid { font-weight: normal; }
</style>
<p>This tool compares properties of two <a href="https://www.wikidata.org/">Wikidata</a> items. It is only a proof of concept and does not handle qualifiers nor references.</p>
<form action="'.SITE_DIR.DIFF_SITE_DIR.'" method="get">
<p>
    <label for="qids">Items:</label> <input type="text" name="qids" value="'.htmlentities($qidsStr).'" id="qids" />
    <label for="language">Language:</label> <input type="text" name="language" value="'.htmlentities($language).'" id="language" />
    <input type="submit" value="Compare" />
</p>
</form>';

$labels = array();

function displayClaim($claim) {
    global $labels;
    $r = '<span title="Rank: '.$claim->rank.'">';
    switch ($claim->datatype) {
        case 'commonsMedia':
            $r .= '<a href="https://commons.wikimedia.org/wiki/File:'.htmlentities($claim->value).'">'.htmlentities($claim->value).'</a>';
            break;
        case 'url':
            $r .= '<a href="'.htmlentities($claim->value).'">'.htmlentities($claim->value).'</a>';
            break;
        case 'wikibase-item':
        case 'wikibase-property':
            if (isset($claim->value)) {
                $r .= '<a href="https://www.wikidata.org/wiki/'.$claim->value.'">'.$labels[$claim->value].'</a>';
            } else {
                $r .= '<em>no value</em>';
            }
            break;
        default:
            $r .= htmlentities($claim->value);
            break;
    }
    $r .= '</span>';
    return $r;
}

if ($go === true) {
    
    try {
        
        $labels[$qids[0]] = $qids[0];
        $labels[$qids[1]] = $qids[1];
        
        $entities = json_decode(file_get_contents('http://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=claims&ids='.$qids[0].'|'.$qids[1].''))->entities;
        foreach ($qids as $qid) {
            if (isset($entities->$qid->missing)) {
                throw new Exception('<a href="https://www.wikidata.org/wiki/'.$qid.'">'.$qid.'</a> is missing.');
            }
        }
        
        $qids = array();
        foreach (get_object_vars($entities) as $qid => &$object) {
            $qids[] = substr($qid, 1);
        }
        
        $pids = array();
        
        $claims = array();
        foreach ($qids as &$qid) {
            $claims[$qid] = array();
            foreach (get_object_vars($entities->{'Q'.$qid}->claims) as $pid => &$claimArray) {
                $labels[$pid] = $pid;
                $pid = substr($pid, 1);
                $pids[] = $pid;
                foreach ($claimArray as $claim) {
                    if (!isset($claim->mainsnak->datavalue)) {
                        $value = null;
                    } else {
                        switch ($claim->mainsnak->datatype) {
                            case 'commonsMedia':
                            case 'external-id':
                            case 'string':
                            case 'url':
                                $value = $claim->mainsnak->datavalue->value;
                                break;
                            case 'globe-coordinate':
                                $value = $claim->mainsnak->datavalue->value->latitude.'/'.$claim->mainsnak->datavalue->value->longitude.'/'.$claim->mainsnak->datavalue->value->altitude.'/'.$claim->mainsnak->datavalue->value->precision.'@'.$claim->mainsnak->datavalue->value->globe;
                                break;
                            case 'monolingualtext':
                                $value = $claim->mainsnak->datavalue->value->text.'@'.$claim->mainsnak->datavalue->value->language;
                                break;
                            case 'quantity':
                                $value = $claim->mainsnak->datavalue->value->amount.'@'.$claim->mainsnak->datavalue->value->unit;
                                break;
                            case 'time':
                                $value = $claim->mainsnak->datavalue->value->time.'/'.$claim->mainsnak->datavalue->value->precision.'@'.$claim->mainsnak->datavalue->value->calendarmodel;
                                break;
                            case 'wikibase-item':
                            case 'wikibase-property':
                                $value = $claim->mainsnak->datavalue->value->id;
                                $labels[$value] = $value;
                                break;
                            default:
                                throw new Exception('Unknown datatype: '.$claim->mainsnak->datatype);
                        }
                    }
                    $theClaim = new stdClass;
                    $theClaim->pid = $pid;
                    $theClaim->datatype = $claim->mainsnak->datatype;
                    $theClaim->value = $value;
                    $theClaim->rank = $claim->rank;
                    $claims[$qid][$pid][md5($claim->rank.'_'.$pid.'_'.$value)] = $theClaim;
                }
            }
        }
        
        $pids = array_unique($pids, SORT_NUMERIC);
        sort($pids);
        
        $keys = array_chunk(array_keys($labels), 50);
        echo '<!-- '.count($labels).' labels -->';
        foreach ($keys as &$chunk) {
            $file = file_get_contents('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=labels&languages='.$language.'&ids='.implode($chunk, '|'));
            $entities = json_decode($file)->entities;
            foreach (get_object_vars($entities) as $oid => &$object) {
                if (isset($object->labels->$language->value)) {
                    $labels[$oid] = $object->labels->$language->value;
                }
            }
        }
        
        echo '<table><tr><th>Property</th><th><a href="https://www.wikidata.org/wiki/Q'.$qids[0].'">';
        if ($labels['Q'.$qids[0]] != 'Q'.$qids[0]) {
            echo htmlentities($labels['Q'.$qids[0]]);
        } else {
            echo '<em>no label</em>';
        }
        echo '</a> <span class="qid">(Q'.$qids[0].')</span></th><th><a href="https://www.wikidata.org/wiki/Q'.$qids[1].'">';
        if ($labels['Q'.$qids[1]] != 'Q'.$qids[1]) {
            echo htmlentities($labels['Q'.$qids[1]]);
        } else {
            echo '<em>no label</em>';
        }
        echo '</a> <span class="qid">(Q'.$qids[1].')</span></th></tr>
        ';
        
        foreach ($pids as $pid) {
            $rows = array();
            if (isset($claims[$qids[0]][$pid])) {
                foreach ($claims[$qids[0]][$pid] as $cid => &$claim) {
                    if (isset($claims[$qids[1]][$pid][$cid])) {
                        $rows[] = '<td colspan="2" class="e">'.displayClaim($claim).'</td>';
                    }
                }
                foreach ($claims[$qids[0]][$pid] as $cid => &$claim) {
                    if (!isset($claims[$qids[1]][$pid])) {
                        $rows[] = '<td class="m">'.displayClaim($claim).'</td><td></td>';
                    }
                }
            }
            if (isset($claims[$qids[1]][$pid])) {
                foreach ($claims[$qids[1]][$pid] as $cid => &$claim) {
                    if (!isset($claims[$qids[0]][$pid])) {
                        $rows[] = '<td></td><td class="m">'.displayClaim($claim).'</td>';
                    }
                }
            }
            if (isset($claims[$qids[0]][$pid]) && isset($claims[$qids[1]][$pid])) {
                $l = array();
                $r = array();
                foreach ($claims[$qids[0]][$pid] as $cid => &$claim) {
                    if (!isset($claims[$qids[1]][$pid][$cid])) {
                        $l[] = displayClaim($claim);
                    }
                }
                foreach ($claims[$qids[1]][$pid] as $cid => &$claim) {
                    if (!isset($claims[$qids[0]][$pid][$cid])) {
                        $r[] = displayClaim($claim);
                    }
                }
                if ((count($r) >= 1) || (count($l) >= 1)) {
                    $rows[] = '<td class="d">'.implode($l, '<br />').'</td><td class="d">'.implode($r, '<br />').'</td>';
                }
            }
            echo '<tr><td rowspan="'.count($rows).'" class="p"><a href="https://www.wikidata.org/wiki/Property:P'.$pid.'">'.$labels['P'.$pid].'</a></td>'.implode($rows, '</tr><tr>').'</tr>'."\n";
        }
        
        echo '</table>
<h2>Help</h2>
<ul>
<li>Colors meanings: <span class="e">the value is the same for both items</span>, <span class="d">the value exists in only one item and the property is used in both items</span>, <span class="m">the value exists in only one item and the property is used in only one item</span>.</li>
<li>You can hover over a value to see its rank.</li>
</ul>';
        
    } catch (Exception $e) {
        echo '<h2>Error</h2><p>'.$e->getMessage().'</p>';
    }
    
}

echo '<!--<p>&rarr; <a href="'.SITE_DIR.DIFF_SITE_DIR.'?qids='.urlencode($qidsStr).'&amp;language='.urlencode($language).'"><strong>Permalink</strong></a> to this comparison.</p>-->';

require '../../inc/footer.inc.php';

?>