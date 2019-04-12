<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.DD_SITE_DIR.'">Dedicated Dashboard</a>');

require '../../inc/header.inc.php';

echo '<style type="text/css">
body { line-height: 200%; }
h2 input { vertical-align: bottom; }
fieldset { margin: 12px 0; padding: 0 12px; border: 0; background: #EEE; }
fieldset p { margin: 0 0 8px 0; }
legend { font-weight: bold; }
input[type=text] { box-sizing: border-box; width: 100%; }
input[type=button] { padding: 4px 12px; }
textarea { box-sizing: border-box; width: 100%; max-width: 100%; padding: 2px 4px; border-radius: 3px; }
td { text-align: center; }
.list td { text-align: right; }
td.label { text-align: left; }
</style>
<script type="text/javascript"><!--
function moveUp(item) {
    if (item != item.parentNode.firstChild) {
        item.parentNode.insertBefore(item, item.previousSibling);
    }
}
function moveDown(item) {
    if (item != item.parentNode.lastChild) {
        item.parentNode.insertBefore(item.nextSibling, item);
    }
}
function remove(item) {
    item.remove();
}
function addRule() {
    node = document.createElement(\'fieldset\');
    node.innerHTML = \'<legend>Rule</legend><p><label for="rule_label" title="Label of the rule.">Label:</label><br /><input type="text" id="rule_label" name="rule_label[]" /><br /><label for="rule_condition" title="SPARQL condition of the rule.">Condition:</label><br /><input type="text" id="rule_condition" name="rule_condition[]" /><br /></p><p><input type="button" value="Move up" onClick="moveUp(this.parentNode.parentNode);" /> <input type="button" value="Move down" onClick="moveDown(this.parentNode.parentNode);" /> <input type="button" value="Remove" onClick="remove(this.parentNode.parentNode);" /></p>\';
    document.getElementById(\'rules\').appendChild(node);
}
function toggleVisibility(id) {
    elem = document.getElementById(id);
    if (elem.style.display == \'none\') {
        document.getElementById(id).style.display = \'block\';
    } else {
        document.getElementById(id).style.display = \'none\';
    }
}
--></script>';

# INPUT

$title = null;
if (!empty($_GET['title'])) {
    $title = trim($_GET['title']);
}

$dashboard_query = null;
if (!empty($_GET['dashboard_query'])) {
    $dashboard_query = trim($_GET['dashboard_query']);
}

$list_query = null;
if (!empty($_GET['list_query'])) {
    $list_query = trim($_GET['list_query']);
}

$columns_title = null;
if (!empty($_GET['columns_title'])) {
    $columns_title = trim($_GET['columns_title']);
}

$columns_query = null;
if (!empty($_GET['columns_query'])) {
    $columns_query = trim($_GET['columns_query']);
}

$rules = array();
$rules_labels = array();
$rules_conditions = array();
if (!empty($_GET['rule_label']) && is_array($_GET['rule_label']) && !empty($_GET['rule_condition']) && is_array($_GET['rule_condition']) && (count($_GET['rule_label']) == count($_GET['rule_condition']))) {
    for ($i = 0; $i < count($_GET['rule_label']); $i++) {
        $label = trim($_GET['rule_label'][$i]);
        $condition = trim($_GET['rule_condition'][$i]);
        $rules[] = array('label' => $label, 'condition' => $condition);
        $rules_labels[] = 'rule_label[]='.htmlentities($label);
        $rules_conditions[] = 'rule_condition[]='.htmlentities($condition);
    }
}
if (empty($rules)) {
    $rules[] = array('label' => '', 'condition' => '');
}

$link = SITE_DIR.DD_SITE_DIR.'?title='.htmlentities($title).'&amp;dashboard_query='.htmlentities($dashboard_query).'&amp;list_query='.htmlentities($list_query).'&amp;columns_title='.htmlentities($columns_title).'&amp;columns_query='.htmlentities($columns_query).'&amp;'.implode('&amp;', $rules_labels).'&amp;'.implode('&amp;', $rules_conditions);

$_rule = null;
if (isset($_GET['rule']) && preg_match('/^[0-9]*$/', $_GET['rule'])) {
    $_rule = $_GET['rule'];
}

$_column = null;
if (isset($_GET['column']) && preg_match('/^[0-9]*$/', $_GET['column'])) {
    $_column = $_GET['column'];
}

# FORM

if (!empty($title)) {
    echo '<h2><a href="'.$link.'">'.htmlentities($title).'</a> <input type="button" value="Show/hide dashboard editor" onClick="toggleVisibility(\'dashboardEditor\');" /></h2>';
} else {
    echo '<h2>Create new dashboard</h2>
    <p>Examples: <a href="'.SITE_DIR.DD_SITE_DIR.'?title=British+MPs&dashboard_query=SELECT+(COUNT(*)+AS+%3Fcount)+WHERE+{+BIND(%3C%25COLUMN_VALUE%25%3E+AS+%3Fposition)+.+%3Fitem+wdt%3AP31+wd%3AQ5+%3B+wdt%3AP39+%3Fposition+.+%25CONDITION%25+}&list_query=SELECT+%3Fitem+%3FitemLabel+WHERE+{+BIND(%3C%25COLUMN_VALUE%25%3E+AS+%3Fposition)+.+%3Fitem+wdt%3AP31+wd%3AQ5+%3B+wdt%3AP39+%3Fposition+.+SERVICE+wikibase%3Alabel+{+bd%3AserviceParam+wikibase%3Alanguage+%22en%22.+}+.+%25CONDITION%25+}+ORDER+BY+%3FitemLabel&columns_title=Terms&columns_query=SELECT+%3Fcolumn_value+%3Fcolumn_label+WHERE+{+%3Fcolumn_value+p%3AP279+[+rdf%3Atype+wikibase%3ABestRank+%3B+ps%3AP279+wd%3AQ16707842+%3B+pq%3AP2937+[+p%3AP31+[+rdf%3Atype+wikibase%3ABestRank+%3B+pq%3AP1545+%3Fcolumn_label+]+]+]+.+}+ORDER+BY+xsd%3Ainteger(%3Fcolumn_label)&rule_label[]=All+British+MPs&rule_condition[]=&rule_label[]=British+MPs+without+a+gender+(P21)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP21+[]+}&rule_label[]=British+MPs+without+a+date+of+birth+(P569)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP569+[]+}&rule_label[]=British+MPs+without+a+date+of+birth+with+day+precision+(P569)&rule_condition[]=%3Fitem+p%3AP569+[+rdf%3Atype+wikibase%3ABestRank+%3B+psv%3AP569%2Fwikibase%3AtimePrecision+%3FbirthdatePrecision+]+.+FILTER+(%3FbirthdatePrecision+%3C+11)&rule_label[]=British+MPs+without+a+place+of+birth+(P19)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP19+[]+}&rule_label[]=British+MPs+without+an+article+in+the+English+Wikipedia&rule_condition[]=MINUS+{+%3Fsitelink+schema%3Aabout+%3Fitem+.+%3Fsitelink+schema%3AinLanguage+%22en%22+}">British MPs</a>,
        <a href="'.SITE_DIR.DD_SITE_DIR.'?title=Wikisources&dashboard_query=SELECT+(COUNT(*)+AS+%3Fcount)+WHERE+{+%3Fitem+wdt%3AP31+wd%3AQ5+.+%3Fsitelink+schema%3Aabout+%3Fitem+%3B+schema%3AisPartOf+%3Chttps%3A%2F%2F%25COLUMN_VALUE%25.wikisource.org%2F%3E+.+%25CONDITION%25+}&list_query=SELECT+%3Fitem+%3FitemLabel+WHERE+{+%3Fitem+wdt%3AP31+wd%3AQ5+.+%3Fsitelink+schema%3Aabout+%3Fitem+%3B+schema%3AisPartOf+%3Chttps%3A%2F%2F%25COLUMN_VALUE%25.wikisource.org%2F%3E+.+SERVICE+wikibase%3Alabel+{+bd%3AserviceParam+wikibase%3Alanguage+%22en%22+.+}+.+%25CONDITION%25+}+ORDER+BY+%3FitemLabel&columns_title=Languages&columns_query=SELECT+%3Fcolumn_value+%3Fcolumn_label+WHERE+{+VALUES+(%3Fcolumn_value+%3Fcolumn_label)+{+(%22en%22+%22en%22)+(%22fr%22+%22fr%22)+(%22de%22+%22de%22)+}+}&rule_label[]=Number+of+authors&rule_condition[]=&rule_label[]=Authors+without+a+date+of+birth+(P569)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP569+[]+}&rule_label[]=Authors+without+a+date+of+death+(P570)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP570+[]+}&rule_label[]=Authors+without+a+country+of+citizenship+(P27)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP27+[]+}">Wikisources</a>,
        <a href="'.SITE_DIR.DD_SITE_DIR.'?title=European+Castles&dashboard_query=SELECT+(COUNT(*)+AS+%3Fcount)+WHERE+{+%3Fitem+wdt%3AP31%2Fwdt%3AP279*+wd%3AQ23413+%3B+wdt%3AP17+%3C%25COLUMN_VALUE%25%3E+.+%25CONDITION%25+}&list_query=SELECT+%3Fitem+%3FitemLabel+{+%3Fitem+wdt%3AP31%2Fwdt%3AP279*+wd%3AQ23413+%3B+wdt%3AP17+%3C%25COLUMN_VALUE%25%3E+.+SERVICE+wikibase%3Alabel+{+bd%3AserviceParam+wikibase%3Alanguage+%22en%22+.+}+.+%25CONDITION%25+}&columns_title=Country&columns_query=SELECT+%3Fcolumn_value+%3Fcolumn_label+{+VALUES+(%3Fcolumn_value+%3Fcolumn_label)+{+(wd%3AQ29+%22Spain%22)+(wd%3AQ142+%22France%22)+(wd%3AQ183+%22Germany%22)+}+}&rule_label[]=All&rule_condition[]=&rule_label[]=Castles+without+image+(P18)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP18+[]+}&rule_label[]=Castles+without+adminstrative+location+(P131)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP131+[]+}&rule_label[]=Castles+without+coordinate+location+(P625)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP625+[]+}&rule_label[]=Castles+without+inception+(P571)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP571+[]+}&rule_label[]=Castles+without+architect+(P84)&rule_condition[]=FILTER+NOT+EXISTS+{+%3Fitem+wdt%3AP84+[]+}">European Castles</a>.</p>';
}

echo '<form id="dashboardEditor" method="get" action="'.SITE_DIR.DD_SITE_DIR.'"';
if (!empty($title)) {
    echo ' style="display: none;"';
}
echo '>';

echo '<fieldset><legend>Dashboard</legend><p>
    <label for="title" title="Title of the dashboard.">Title:</label><br /><input type="text" id="title" name="title" value="'.htmlentities($title).'" /><br />
    <label for="dashboard_query" title="SPARQL query that is executed on each square. It must contain %COLUMN_VALUE% and %CONDITION% keywords which will be replaced by values of the columns and the rules.">Dashboard query:</label><br /><textarea id="dashboard_query" name="dashboard_query">'.htmlentities($dashboard_query).'</textarea><br />
    <label for="list_query" title="SPARQL query that list items from a square. It must contain %COLUMN_VALUE% and %CONDITION% keywords which will be replaced by values of the columns and the rules.">List query:</label><br /><textarea id="list_query" name="list_query">'.htmlentities($list_query).'</textarea><br />
</p></fieldset>';

echo '<fieldset><legend>Columns</legend><p>
    <label for="columns_title" title="Title of the columns.">Title:</label><br />
    <input type="text" id="columns_title" name="columns_title" value="'.htmlentities($columns_title).'" /><br />
    <label for="columns_query" title="SPARQL query that returns column_value and column_label variables.">Query:</label><br /><textarea id="columns_query" name="columns_query">'.htmlentities($columns_query).'</textarea><br />
</p></fieldset>';

echo '<div id="rules">';
foreach ($rules as $rule) {
    echo '<fieldset><legend>Rule</legend><p>
        <label for="rule_label" title="Label of the rule.">Label:</label><br /><input type="text" id="rule_label" name="rule_label[]" value="'.htmlentities($rule['label']).'" /><br />
        <label for="rule_condition" title="SPARQL condition of the rule.">Condition:</label><br /><input type="text" id="rule_condition" name="rule_condition[]" value="'.htmlentities($rule['condition']).'" /><br />
    </p>
    <p><input type="button" value="Move up" onClick="moveUp(this.parentNode.parentNode);" /> <input type="button" value="Move down" onClick="moveDown(this.parentNode.parentNode);" /> <input type="button" value="Remove" onClick="remove(this.parentNode.parentNode);" /></p>
</fieldset>';
}
echo '</div>';

echo '<p><input type="button" value="Add rule" onClick="addRule();" /></p>';

echo '<p><input type="submit" value="Show results" /></p>';

echo '</form>';

# RESULTS

try {
    
    if (empty($dashboard_query) || empty($columns_query) || empty($rules)) {
        throw new Exception();
    }
    
    $r = '<table><tr><th>Rules</th><th>'.htmlentities($columns_title).'</th>';
    
    $results = wdqs::query($columns_query, 86400)->results->bindings;
    if (count($results) == 0) {
        throw new Exception('No result from columns query.');
    }
    $columns = array();
    foreach ($results as $result) {
        
        if (empty($result->column_value->value)) {
            throw new Exception('Columns query returned empty column_value.');
        }
        $value = trim($result->column_value->value);
        if (empty($value)) {
            throw new Exception('Columns query returned invalid column_value.');
        }
        
        if (empty($result->column_label->value)) {
            throw new Exception('Columns query returned empty column_label.');
        }
        $label = trim($result->column_label->value);
        if (empty($label)) {
            throw new Exception('Columns query returned invalid column_label.');
        }
        
        $columns[] = array('value' => $value, 'label' => $label);
        
        $r .= '<th>'.htmlentities($label).'</th>';
        
    }
    
    $r .= '</tr>'."\n";
    
    for ($i = 0; $i < count($rules); $i++) {
        $r .= '<tr><td colspan="2" class="label">'.htmlentities($rules[$i]['label']).'</td>';
        for ($j = 0; $j < count($columns); $j++) {
            $query = str_replace(array('%COLUMN_VALUE%', '%CONDITION%'), array($columns[$j]['value'], $rules[$i]['condition']), $dashboard_query);
            $results = wdqs::query($query, 86400)->results->bindings;
            if (count($results) != 1) {
                throw new Exception('No result from dashboard query (column: '.htmlentities($columns[$j]['label']).', rule: '.htmlentities($rules[$i]['label']).'): '.htmlentities($query));
            }
            $result = $results[0];
            if (!isset($result->count->value)) {
                throw new Exception('Base query returned empty count (column: '.htmlentities($columns[$j]['label']).', rule: '.htmlentities($rules[$i]['label']).').');
            }
            $r .= '<td><a href="'.$link.'&amp;rule='.$i.'&amp;column='.$j.'#list">';
            $value = $result->count->value;
            if ($value === '0') {
                $r .= '&mdash;';
            } else {
                $r .= htmlentities(number_format($result->count->value));
            }
            $r .= '</a></td>';
        }
        $r .= '</tr>'."\n";
    }
    
    $r .= '</table>';
    
    if (isset($_rule) && isset($rules[$_rule]) && isset($_column) && isset($columns[$_column])) {
        $rule = $rules[$_rule];
        $column = $columns[$_column];
        $r .= '<h2 id="list">['.htmlentities($column['label']).'] '.htmlentities($rule['label']).'</h2>';
        $query = str_replace(array('%COLUMN_VALUE%', '%CONDITION%'), array($column['value'], $rule['condition']), $list_query);
        $results = wdqs::query($query.' LIMIT 1000')->results->bindings;
        if (count($results) == 0) {
            $r .= '<p>List is empty!</p>';
        } else {
            $r .= '<p>'.count($results).' result'.(count($results) > 1 ? 's' : '').'. <a href="https://query.wikidata.org/#'.htmlentities($query).'">Open in WDQS &rarr;</a></p>';
            $r .= '<table class="list"><tr><th>Label</th><th>Wikidata</th></tr>'."\n";
            foreach ($results as $result) {
                $qid = substr($result->item->value, 31);
                $r .= '<tr><td class="label">'.htmlentities($result->itemLabel->value).'</td><td><a href="https://www.wikidata.org/wiki/'.$qid.'">'.htmlentities($qid).'</a></td></tr>'."\n";
            }
            $r .= '</table>';
        }
    }
    
    echo $r;
    
} catch (Exception $e) {
    if (!empty($e->getMessage())) {
        echo '<div class="error"><p>Unable to process dashboard: '.htmlentities($e->getMessage()).'</p></div>';
    }
}

# END

require '../../inc/footer.inc.php';

?>