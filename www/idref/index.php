<?php

/*

Input examples

https://www.idref.fr/026927608.rdf

*/

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.IDREF_SITE_DIR.'">IdRef To Wikidata</a>');

require '../../inc/header.inc.php';

echo '<p>Given the URL of a notice in IdRef, this tool produces QuickStatements commands to create a new Wikidata item about a person, with statements referenced by IdRef.</p>
<form action="'.SITE_DIR.IDREF_SITE_DIR.'" method="post"><p><label for="input">URL of the notice of a person in <a href="https://www.idref.fr/">IdRef</a> (the id is enough):</label><br /><input type="text" id="input" name="input" value="'.htmlentities(@$_POST['input']).'" style="width: 50%;" autofocus="autofocus" /> <input type="submit" value="Search" /></p></form>';

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
    
    if (preg_match('@(\d{8}[\dX])@', $_POST['input'], $match)) {
        $idrefId = $match[1];
    }
    
    if (empty($idrefId)) {
        echo '<p><strong style="color: red;">Unable to find the IdRef id with your input :(</strong></p>';
    }
    else {
        
        $warnings = array();
        
        $qid = null;
        
        $res = wdqs::query('SELECT ?item { ?item wdt:P269 \''.$idrefId.'\' } LIMIT 1', 300)->results->bindings;
        if (count($res) >= 1) {
            $qid = substr($res[0]->item->value, 31);
            echo '<p><strong style="color: red;">Warning: there is already one or more Wikidata items with this IdRef id (<a href="https://www.wikidata.org/wiki/'.$qid.'">'.$qid.'</a>).</strong></p>';
        }
        else {
            $qid = 'LAST';
        }
        
        $res = file_get_contents('https://www.idref.fr/'.$idrefId.'.rdf');
        //file_put_contents('/tmp/test.dat', $res);
        //$res = file_get_contents('/tmp/test.dat');
        
        $label = '';
        if (preg_match('/\<foaf\:givenName\>(.*)\<\/foaf\:givenName\>/', $res, $match)) {
            $label = $match[1];
        }
        if (preg_match('/\<foaf\:familyName\>(.*)\<\/foaf\:familyName\>/', $res, $match)) {
            $label = trim($label.' '.$match[1]);
        }
        if (preg_match('/\<foaf\:Person rdf\:about\=\"http\:\/\/www\.idref\.fr\/'.$idrefId.'\/id\"\>/', $res, $match)) {
            $type = 'Person';
        }
        if (preg_match('/\<bio\:date\>([0-9-]+)\<\/bio\:date\>'."\n".'\<\/bio\:Birth\>/', $res, $match)) {
            $birth = date2qs($match[1]);
        }
        if (preg_match('/\<bio\:date\>([0-9-]+)\<\/bio\:date\>'."\n".'\<\/bio\:Death\>/', $res, $match)) {
            $death = date2qs($match[1]);
        }
        if (preg_match('/\<dbpedia-owl\:citizenship rdf\:resource\=\"http\:\/\/sws\.geonames\.org\/([1-9]\d+)\"\/\>/', $res, $match)) {
            $country = $match[1];
        }
        if (preg_match('/\<dcterms\:language rdf\:resource\=\"http\:\/\/lexvo\.org\/id\/iso639-3\/([a-z]{3})\"\/\>/', $res, $match)) {
            $lang = $match[1];
        }
        if (preg_match('/\<owl\:sameAs rdf\:resource\=\"http\:\/\/isni\.org\/isni\/(\d{15}[0-9X])\"\/\>/', $res, $match)) {
            $isni = $match[1];
        }
        if (preg_match('/\<owl\:sameAs rdf\:resource\=\"http\:\/\/data\.bnf\.fr\/ark\:\/12148\/cb(\d{8}[0-9bcdfghjkmnpqrstvwxz])\#foaf\:Person\"\/\>/', $res, $match)) {
            $bnf = $match[1];
        }
        
        echo '<p>IdRef id: <a href="https://www.idref.fr/'.$idrefId.'">'.$idrefId.'</a>.';
        
        echo '<p><textarea rows="20" style="width: 100%; max-width: 100%;">';
        if ($qid == 'LAST') {
            echo 'CREATE';
        }
        echo "\n";
        
        $source = "\t".'S248'."\t".'Q47757534'."\t".'S269'."\t".'"'.$idrefId.'"'."\t".'S813'."\t".'+'.date('Y-m-d').'T00:00:00Z/11'."\t".'S854'."\t".'"https://www.idref.fr/'.$idrefId.'"';
        
        // labels
        if (!empty($label)) {
            $label = trim(preg_replace('/(.*)\(.*\)/', '$1', $label));
            echo $qid."\t".'Lfr'."\t".'"'.$label.'"'."\n";
            echo $qid."\t".'Len'."\t".'"'.$label.'"'."\n";
        }
        // human
        if (!empty($type) && ($type === 'Person')) {
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
            $wd_res = wdqs::query('SELECT DISTINCT ?item { ?item wdt:P1566 "'.$country.'" }', 86400)->results->bindings;
            if (count($wd_res) === 0) {
                $warnings[] = 'Unknwon GeoNames ID "'.htmlentities($country_code).'" in Wikidata.';
            } elseif (count($wd_res) === 1) {
                echo $qid."\t".'P27'."\t".substr($wd_res[0]->item->value, 31).$source."\n";
            } else {
                $warnings[] = 'GeoNames ID "'.htmlentities($country).'" is used in several Wikidata items.';
            }
        }
        // language
        if (!empty($lang)) {
            $wd_res = wdqs::query('SELECT DISTINCT ?item { ?item wdt:P220 "'.$lang.'" }', 86400)->results->bindings;
            if (count($wd_res) === 0) {
                $warnings[] = 'Unknwon ISO 639-3 code "'.htmlentities($country_code).'" in Wikidata.';
            } elseif (count($wd_res) === 1) {
                echo $qid."\t".'P1412'."\t".substr($wd_res[0]->item->value, 31).$source."\n";
            } else {
                $warnings[] = 'ISO 639-3 code "'.htmlentities($lang).'" is used in several Wikidata items.';
            }
        }
        // IdRef id
        echo $qid."\t".'P269'."\t".'"'.$idrefId.'"'.$source."\n";
        // ISNI
        if (!empty($isni)) {
            echo $qid."\t".'P213'."\t".'"'.trim(chunk_split($isni, 4, ' ')).'"'.$source."\n";
        }
        // BnF
        if (!empty($bnf)) {
            echo $qid."\t".'P268'."\t".'"'.$bnf.'"'.$source."\n";
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