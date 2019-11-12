<?php

/*

Input examples

https://data.bnf.fr/fr/11907966/victor_hugo/
https://catalogue.bnf.fr/ark:/12148/cb11907966z

https://data.bnf.fr/fr/10719575/dieudonne_de_saint-jean-sur-mayenne/
https://catalogue.bnf.fr/ark:/12148/cb107195751

https://catalogue.bnf.fr/ark:/12148/cb120790362

*/

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.BNF_SITE_DIR.'">BnF To Wikidata</a>');

require '../../inc/header.inc.php';

echo '<p>Given the URL of a notice in the catalog of the BnF (<em>Bibliothèque nationale de France</em>, French national Library), this tool produces QuickStatements commands to create a new Wikidata item about a person, with statements referenced by the BnF.</p>
<form action="'.SITE_DIR.BNF_SITE_DIR.'" method="post"><p><label for="input">URL of the notice of a person in the <a href="https://catalogue.bnf.fr/">general catalog of the BnF</a> (the last part of the ARK id after <code>cb</code> is enough) or URL of the notice in <a href="https://data.bnf.fr/">data.bnf.fr</a>:</label><br /><input type="text" id="input" name="input" value="'.htmlentities(@$_POST['input']).'" style="width: 50%;" autofocus="autofocus" /> <input type="submit" value="Search" /></p></form>';

// TODO handle more date formats
function date2qs($date) {
    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
        return '+'.$date.'T00:00:00Z/11';
    }
    elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $date)) {
        return '+'.$date.'-01T00:00:00Z/10';
    }
    elseif (preg_match('/^[0-9]{4}$/', $date)) {
        return '+'.$date.'-01-01T00:00:00Z/9';
    }
    return null;
}

if (!empty($_POST['input'])) {
    
    if (preg_match('@(\d{8}[0-9bcdfghjkmnpqrstvwxz])$@', $_POST['input'], $match)) {
        $bnfId = $match[1];
    }
    elseif (preg_match('@^https://data\.bnf\.fr/([a-z]+/)?[0-9]+/[a-z0-9_-]+/$@', $_POST['input'])) {
        $data = file_get_contents($_POST['input']);
        if (preg_match('@<meta name="DC.identifier" content="ark:/12148/cb(\d{8}[0-9bcdfghjkmnpqrstvwxz])" />@', $data, $match)) {
            $bnfId = $match[1];
        }
    }
    
    if (empty($bnfId)) {
        echo '<p><strong style="color: red;">Unable to find the BnF id with your input :(</strong></p>';
    }
    else {
        
        $warnings = array();
        
        $qid = null;
        
        $res = wdqs::query('SELECT ?item { ?item wdt:P268 \''.$bnfId.'\' } LIMIT 1')->results->bindings;
        if (count($res) >= 1) {
            $qid = substr($res[0]->item->value, 31);
            echo '<p><strong style="color: red;">Warning: there is already one or more Wikidata items with this BnF id (<a href="https://www.wikidata.org/wiki/'.$qid.'">'.$qid.'</a>).</strong></p>';
        }
        else {
            $qid = 'LAST';
        }
        
        $res = bnf::query('SELECT ?page ?label ?type ?gender ?birth ?death ?country
WHERE {
    <http://data.bnf.fr/ark:/12148/cb'.$bnfId.'> <http://www.w3.org/2004/02/skos/core#prefLabel> ?label ; <http://xmlns.com/foaf/0.1/focus> ?focus .
    OPTIONAL { ?focus <http://xmlns.com/foaf/0.1/page> ?page } .
    OPTIONAL { ?focus <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?type } .
    OPTIONAL { ?focus <http://xmlns.com/foaf/0.1/gender> ?gender } .
    OPTIONAL { ?focus <http://vocab.org/bio/0.1/birth> ?birth } .
    OPTIONAL { ?focus <http://vocab.org/bio/0.1/death> ?death } .
    OPTIONAL { ?focus <http://rdvocab.info/ElementsGr2/countryAssociatedWithThePerson> ?country } .
}')->results->bindings;
// TODO handle several lines
        if (count($res) >= 1) {
            $page = @$res[0]->page->value;
            $label = @$res[0]->label->value;
            $type = @$res[0]->type->value;
            $gender = @$res[0]->gender->value;
            $birth = date2qs(@$res[0]->birth->value);
            $death = date2qs(@$res[0]->death->value);
            $country = @$res[0]->country->value;
        }
        
        echo '<p>BnF id: '.$bnfId.' [<a href="'.htmlentities($page).'">data</a>, <a href="https://catalogue.bnf.fr/ark:/12148/cb'.$bnfId.'">catalogue</a>].';
        
        echo '<p><textarea rows="20" style="width: 100%; max-width: 100%;">';
        if ($qid == 'LAST') {
            echo 'CREATE';
        }
        echo "\n";
        
        $source = "\t".'S248'."\t".'Q15222191'."\t".'S268'."\t".'"'.$bnfId.'"'."\t".'S813'."\t".'+'.date('Y-m-d').'T00:00:00Z/11'."\t".'S854'."\t".'"https://catalogue.bnf.fr/ark:/12148/cb'.$bnfId.'"';
        
        // labels
        if (!empty($label)) {
            $label = trim(preg_replace('/(.*)\(.*\)/', '$1', $label));
            echo $qid."\t".'Lfr'."\t".'"'.$label.'"'."\n";
            echo $qid."\t".'Len'."\t".'"'.$label.'"'."\n";
        }
        // human
        if (!empty($type) && ($type === 'http://xmlns.com/foaf/0.1/Person')) {
            echo $qid."\t".'P31'."\t".'Q5'.$source."\n";
        } else {
            $warnings[] = 'Are you sure that the URL you provided is about a human (and not about a work)?';
        }
        // gender
        if (!empty($gender)) {
            echo $qid."\t".'P21'."\t".($gender == 'male' ? 'Q6581097' : 'Q6581072').$source."\n";
        }
        // date of birth
        if (!empty($birth)) {
            echo $qid."\t".'P569'."\t".$birth.$source."\n";
        }
        // date of death
        if (!empty($death)) {
            echo $qid."\t".'P570'."\t".$death.$source."\n";
        }
        // country
        if (!empty($country)) {
            $country_code = substr($country, strrpos($country, '/') + 1);
            if (!empty($country_code)) {
                $wd_res = wdqs::query('SELECT DISTINCT ?item { ?item wdt:P4801 "countries/'.$country_code.'" }', 86400)->results->bindings;
                if (count($wd_res) === 0) {
                    $warnings[] = 'Unknwon MARC vocabulary ID "countries/'.htmlentities($country_code).'" in Wikidata.';
                } elseif (count($wd_res) === 1) {
                    echo $qid."\t".'P27'."\t".substr($wd_res[0]->item->value, 31).$source."\n";
                } else {
                    $warnings[] = 'MARC vocabulary ID "countries/'.htmlentities($country_code).'" is used in several Wikidata items.';
                }
            }
        }
        // languages
        $res = bnf::query('SELECT DISTINCT ?lang { <http://data.bnf.fr/ark:/12148/cb'.$bnfId.'> <http://xmlns.com/foaf/0.1/focus>/<http://rdvocab.info/ElementsGr2/languageOfThePerson> ?lang }')->results->bindings;
        foreach ($res as $value) {
            $lang = substr($value->lang->value, 38);
            $wd_res = wdqs::query('SELECT DISTINCT ?item { ?item wdt:P219 "'.$lang.'" }', 86400)->results->bindings;
            if (count($wd_res) === 0) {
                $warnings[] = 'Unknwon ISO 639-2 code "'.htmlentities($country_code).'" in Wikidata.';
            } elseif (count($wd_res) === 1) {
                echo $qid."\t".'P1412'."\t".substr($wd_res[0]->item->value, 31).$source."\n";
            } else {
                $warnings[] = 'ISO 639-2 code "'.htmlentities($lang).'" is used in several Wikidata items.';
            }
        }
        // bnf id
        echo $qid."\t".'P268'."\t".'"'.$bnfId.'"'."\t".$source."\n";
        // other ids
        //$res = bnf::query('SELECT ?value WHERE { <http://data.bnf.fr/ark:/12148/cb'.$bnfId.'> <http://www.w3.org/2004/02/skos/core#exactMatch> ?value }')->results->bindings;
        $res = bnf::query('SELECT ?value WHERE {
  { SELECT ?value WHERE { <http://data.bnf.fr/ark:/12148/cb'.$bnfId.'#about> <http://www.w3.org/2002/07/owl#sameAs> ?value } }
  UNION
  { SELECT ?value WHERE { <http://data.bnf.fr/ark:/12148/cb'.$bnfId.'> <http://isni.org/ontology#identifierValid> ?isni . BIND (CONCAT("http://isni.org/isni/", ?isni) AS ?value) } }
}')->results->bindings;
        foreach ($res as $value) {
            $uri = trim($value->value->value);
            // P213: ISNI ID
            if (preg_match('@^http://isni.org/isni/(\d{15}[0-9X])$@', $uri, $match)) {
                echo $qid."\t".'P213'."\t".'"'.trim(chunk_split($match[1], 4, ' ')).'"'.$source."\n";
            }
            // P214: VIAF ID
            elseif (preg_match('@^http://viaf\.org/viaf/(\d+)$@', $uri, $match)) {
                echo $qid."\t".'P214'."\t".'"'.$match[1].'"'.$source."\n";
            }
            // P269: IDRef / SUDOC
            elseif (preg_match('@^http://www\.idref\.fr/(\d{8}[\dX])/id$@', $uri, $match)) {
                echo $qid."\t".'P269'."\t".'"'.$match[1].'"'.$source."\n";
            }
            // P434: MusicBrainz artist ID
            elseif (preg_match('@^https://musicbrainz\.org/artist/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$@', $uri, $match)) {
                echo $qid."\t".'P434'."\t".'"'.$match[1].'"'.$source."\n";
            }
            // P3599: archival creator authority record at the Archives nationales
            elseif (preg_match('@^https://www\.siv\.archives-nationales.culture\.gouv\.fr/siv/NP/(FRAN_NP_\d{6})$@', $uri, $match)) {
                echo $qid."\t".'P3599'."\t".'"'.$match[1].'"'.$source."\n";
            }
            // bnf
            elseif (preg_match('@^http://data\.bnf\.fr/@', $uri)) {
                // nothing
            }
            // dbpedia
            elseif (preg_match('@^http://fr\.dbpedia\.org/@', $uri)) {
                // nothing
            }
            // wikidata
            elseif (preg_match('@^http://wikidata\.org/@', $uri)) {
                // nothing
            }
            // wikipedia
            elseif (preg_match('@^http://fr\.wikipedia\.org/@', $uri)) {
                // nothing
            }
            // unknown
            else {
                $warnings[] = 'Unknown URI: <'.htmlentities($uri).'>';
            }
        }
        
        echo '</textarea></p>';
        
        if (!empty($warnings)) {
            echo '<p><strong style="color: red;">Warning'.(count($warnings) > 1 ? 's' : '').':</strong></p><ul>';
            foreach ($warnings as $warning) {
                echo '<li>'.htmlentities($warning).'</li>';
            }
            echo '</ul>';
        }
        
        echo '<p>You can copy/paste this code in <a href="https://tools.wmflabs.org/quickstatements/">QuickStatements</a>. Don\'t forget to check the results!</p>';
        
    }
    
}

require '../../inc/footer.inc.php';

?>