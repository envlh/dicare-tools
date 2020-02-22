<?php

/*

Input examples

https://www.idref.fr/026927608.rdf

*/

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.IDREF_SITE_DIR.'">IdRef To Wikidata</a>');

require '../../inc/header.inc.php';

echo '<p>Given the URL of a notice in IdRef, this tool produces QuickStatements commands to create a new Wikidata item about a person, with statements referenced by IdRef.</p>
<p>See also: <a href="'.SITE_DIR.BNF_SITE_DIR.'">BnF To Wikidata</a>.</p>
<form action="'.SITE_DIR.IDREF_SITE_DIR.'" method="post"><p><label for="input">URL of the notice of a person in <a href="https://www.idref.fr/">IdRef</a> (the id is enough):</label><br /><input type="text" id="input" name="input" value="'.htmlentities(@$_POST['input']).'" style="width: 50%;" autofocus="autofocus" /> <input type="submit" value="Search" /></p></form>';

if (!empty($_POST['input'])) {
    
    if (preg_match('@(\d{8}[\dX])@', $_POST['input'], $match)) {
        $idrefId = $match[1];
    }
    
    if (empty($idrefId)) {
        echo '<p><strong style="color: red;">Unable to find the IdRef id with your input :(</strong></p>';
    }
    else {
        
        echo '<p>IdRef id: <a href="https://www.idref.fr/'.$idrefId.'">'.$idrefId.'</a>.';
        
        $data = file_get_contents('https://www.idref.fr/'.$idrefId.'.rdf');
        //file_put_contents('/tmp/test.dat', $data);
        //$data = file_get_contents('/tmp/test.dat');
        
        $warnings = array();
        
        $label = '';
        if (preg_match('/\<foaf\:givenName\>(.*)\<\/foaf\:givenName\>/', $data, $match)) {
            $label = $match[1];
        }
        if (preg_match('/\<foaf\:familyName\>(.*)\<\/foaf\:familyName\>/', $data, $match)) {
            $label = trim($label.' '.$match[1]);
        }
        if (preg_match('/\<foaf\:Person rdf\:about\=\"http\:\/\/www\.idref\.fr\/'.$idrefId.'\/id\"\>/', $data, $match)) {
            $type = 'Person';
        }
        if (preg_match('/\<bio\:date\>([0-9-]+)\<\/bio\:date\>'."\n".'\<\/bio\:Birth\>/', $data, $match)) {
            $birth = utils::date2qs($match[1]);
        }
        if (preg_match('/\<bio\:date\>([0-9-]+)\<\/bio\:date\>'."\n".'\<\/bio\:Death\>/', $data, $match)) {
            $death = utils::date2qs($match[1]);
        }
        if (preg_match('/\<dbpedia-owl\:citizenship rdf\:resource\=\"http\:\/\/sws\.geonames\.org\/([1-9]\d+)\"\/\>/', $data, $match)) {
            $country = $match[1];
        }
        if (preg_match('/\<dcterms\:language rdf\:resource\=\"http\:\/\/lexvo\.org\/id\/iso639-3\/([a-z]{3})\"\/\>/', $data, $match)) {
            $lang = $match[1];
        }
        if (preg_match('/\<owl\:sameAs rdf\:resource\=\"http\:\/\/isni\.org\/isni\/(\d{15}[0-9X])\"\/\>/', $data, $match)) {
            $isni = trim(chunk_split($match[1], 4, ' '));
        }
        if (preg_match('/\<owl\:sameAs rdf\:resource\=\"http\:\/\/data\.bnf\.fr\/ark\:\/12148\/cb(\d{8}[0-9bcdfghjkmnpqrstvwxz])\#foaf\:Person\"\/\>/', $data, $match)) {
            $bnf = $match[1];
        }
        
        $qid = null;
        if (preg_match('/\<owl\:sameAs rdf\:resource\=\"http\:\/\/www\.wikidata\.org\/entity\/(Q[1-9]\d*)\"\/\>/', $data, $match)) {
            $qid = $match[1];
            echo '<p><strong style="color: red;">Warning: this IdRef notice links to a Wikidata item (<a href="https://www.wikidata.org/wiki/'.$qid.'">'.$qid.'</a>).</strong></p>';
        }
        if (($qid == null)) {
            $qid = utils::checkUsage('P269', 'IdRef id', $idrefId);
        }
        if (($qid == null) && !empty($bnf)) {
            $qid = utils::checkUsage('P268', 'BnF id', $bnf);
        }
        if (($qid == null) && !empty($isni)) {
            $qid = utils::checkUsage('P213', 'ISNI id', $isni);
        }
        if ($qid == null) {
            $qid = 'LAST';
        }
        
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
            echo $qid."\t".'P213'."\t".'"'.$isni.'"'.$source."\n";
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