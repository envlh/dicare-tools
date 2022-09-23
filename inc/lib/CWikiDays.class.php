<?php

class CWikiDays {
    
    private $username = null;
    private $prefix = 0;
    private $project = 0;
    private $namespace = 0;
    private $timezone = 'utc';
    private $timeoffset = 0;
    private $timelabel = 'UTC';
    private $limit = 500;
    
    private $data = array();
    private $count = 0;
    private $longest_streak_count = 0;
    private $longest_streak_date = null;
    
    public function __construct($username, $prefix, $project, $namespace, $timezone, $limit) {
        $this->username = $username;
        $this->prefix = $prefix;
        $this->project = $project;
        $this->namespace = $namespace;
        $this->timezone = $timezone;
        $this->limit = $limit;
    }
    
    private function retrieveTimezoneConfiguration() {
        $results = http::request('GET', 'https://'.$this->prefix.'.'.$this->project.'.org/w/api.php?action=query&format=json&meta=siteinfo');
        if ($results === false) {
            throw new Exception('Unable to retrieve configuration (HTTP query, check that this wiki <code>'.$this->prefix.'.'.$this->project.'.org</code> exists).');
        }
        $json = json_decode($results);
        if (isset($json->error)) {
            throw new Exception('Error from Wikimedia server: <code>'.htmlentities($json->error->code).'</code>.');
        }
        if (!isset($json->query->general->timeoffset)) {
            throw new Exception('Unable to retrieve configuration (invalid response, check that the wiki <code>'.$this->prefix.'.'.$this->project.'.org</code> exists).');
        }
        $this->timeoffset = $json->query->general->timeoffset;
        if ($this->timeoffset < 0) {
            $this->timelabel = 'UTC'.($this->timeoffset / 60);
        }
        elseif ($this->timeoffset > 0) {
            $this->timelabel = 'UTC+'.($this->timeoffset / 60);
        }
        else {
            $this->timelabel = 'UTC';
        }
    }
    
    public function retrieveData() {
        if ($this->timezone == 'wiki') {
            $this->retrieveTimezoneConfiguration();
        }
        $results = http::request('GET', 'https://'.$this->prefix.'.'.$this->project.'.org/w/api.php?action=query&format=json&list=usercontribs&ucuser='.urlencode($this->username).'&ucshow=new&ucnamespace='.$this->namespace.'&uclimit='.$this->limit);
        if ($results === false) {
            throw new Exception('Unable to retrieve data (HTTP query, check that this wiki <code>'.$this->prefix.'.'.$this->project.'.org</code> exists).');
        }
        $json = json_decode($results);
        if (isset($json->error)) {
            throw new Exception('Error from Wikimedia server: <code>'.htmlentities($json->error->code).'</code>.');
        }
        if (!isset($json->query->usercontribs)) {
            throw new Exception('Unable to retrieve data (invalid response, check that the wiki <code>'.$this->prefix.'.'.$this->project.'.org</code> exists).');
        }
        $loop_date = null;
        $streak = 0;
        $this->count = count($json->query->usercontribs);
        foreach (array_reverse($json->query->usercontribs) as &$row) {
            $date = new DateTimeImmutable($row->timestamp);
            if ($this->timeoffset != 0) {
                $date = $date->add(new DateInterval('PT'.$this->timeoffset.'M'));
            }
            $row->timestamp_local = $date->format('Y-m-d\\TH:i:s\\Z');
            $date = $date->setTime(0, 0, 0, 0);
            $date_str = $date->format('Y-m-d');
            if (!isset($this->data[$date_str])) {
                $this->data[$date_str] = array();
            }
            $this->data[$date_str][] = $row;
            if ($date != $loop_date) {
                if (($loop_date == null) || $loop_date->add(new DateInterval('P1D')) == $date) {
                    $streak++;
                } else {
                    if ($streak > $this->longest_streak_count) {
                        $this->longest_streak_count = $streak;
                        $this->longest_streak_date = $loop_date;
                    }
                    $streak = 1;
                }
                $loop_date = $date;
            }
        }
        if ($streak > $this->longest_streak_count) {
            $this->longest_streak_count = $streak;
            $this->longest_streak_date = $loop_date;
        }
    }
    
    public function retrieveLabels() {
        $labels = array();
        if ($this->project == 'wikidata') {
            $query = null;
            if (($this->namespace == 0) || ($this->namespace == 120)) {
                $query = 'SELECT ?item ?itemLabel { VALUES ?item { %ids% } . SERVICE wikibase:label { bd:serviceParam wikibase:language "en" } }';
            }
            elseif ($this->namespace == '146') {
                $query = 'SELECT ?item (GROUP_CONCAT(DISTINCT ?lemma; SEPARATOR = " / ") AS ?itemLabel) { VALUES ?item { %ids% } . ?item wikibase:lemma ?lemma } GROUP BY ?item';
            }
            if ($query != null) {
                $items = array();
                foreach ($this->data as $data) {
                    foreach ($data as $row) {
                        $items[] = preg_replace('/^.*?:/', '', $row->title);
                    }
                }
                $query = str_replace('%ids%', 'wd:'.implode(' wd:', $items), $query);
                $results = wdqs::query($query)->results->bindings;
                foreach ($results as $row) {
                    $id = substr($row->item->value, 31);
                    $label = $row->itemLabel->value;
                    if ($id != $label) {
                        $labels[$id] = $label;
                    }
                }
            }
        }
        return $labels;
    }
    
    public function displayForm($projects) {
        echo '<form action="'.SITE_DIR.CWIKIDAYS_SITE_DIR.'" method="get">
        <p><label for="username">Username:</label> <input type="text" name="username" id="username"'.(!empty($this->username) ? ' value="'.htmlentities($this->username).'"' : '').' /></p>
        <p><label for="project">Project:</label> <input type="text" name="prefix" size="6" value="'.$this->prefix.'" /> . <select name="project" id="project">';
        foreach ($projects as $project) {
            echo '<option value="'.$project.'"'.(($project == $this->project) ? ' selected="selected"' : '').'>'.$project.'.org</option>';
        }
        echo '</select></p>
        <p><label for="namespace">Namespace:</label> <input type="text" name="namespace" id="namespace" value="'.$this->namespace.'" size="3" /> (Main: 0, File: 6, Property: 120, Lexeme: 146)</p>
        <p><label for="timezone">Timezone:</label> <select name="timezone" id="timezone"><option value="wiki"'.(($this->timezone == 'wiki') ? ' selected="selected"' : '').'>Wiki</option><option value="utc"'.(($this->timezone == 'utc') ? ' selected="selected"' : '').'>UTC</option></select></p>
        <p><label for="limit">Limit:</label> <input type="text" name="limit" id="limit" value="'.$this->limit.'" size="3" /></p>
        <p><input type="submit" value="Search" /></p>
        </form>';
    }

    public function displayResults() {
        if (count($this->data) == 0) {
            echo '<p>No creation found on this wiki :(</p>';
        } else {
            $labels = $this->retrieveLabels();
            echo '<p>'.$this->count.' creations found. Longest streak: '.$this->longest_streak_count.' day'.(($this->longest_streak_count > 1) ? 's' : '').', finished on '.$this->longest_streak_date->format('Y-m-d').'.</p>';
            echo '<ul class="streak">';
            $previous_date = null;
            foreach (array_reverse($this->data) as $date_str => $date) {
                $items = array();
                foreach ($date as $row) {
                    $title = $row->title;
                    if (($this->project == 'wikidata') && (($this->namespace == 120) || ($this->namespace == 146))) {
                        $title = preg_replace('/^.*?:/', '', $title);
                    }
                    $item = '<a href="https://'.$this->prefix.'.'.$this->project.'.org/wiki/'.htmlentities(str_replace(' ', '_', $row->title)).'" title="'.substr($row->timestamp_local, 11, 8).' '.$this->timelabel.'">';
                    if (isset($labels[$title])) {
                        $item .= htmlentities($labels[$title]).' ('.htmlentities($title).')';
                    } else {
                        $item .= htmlentities($title);
                    }
                    $item .= '</a>';
                    $items[] = $item;
                }
                if (($previous_date != null) && ((new DateTime($previous_date))->sub(new DateInterval('P1D'))->format('Y-m-d') != $date_str)) {
                    echo '</ul><hr /><ul>';
                }
                echo '<li><strong>'.$date_str.'</strong> <code>['.count($items).']</code> '.implode(', ', $items).'</li>';
                $previous_date = $date_str;
            }
            echo '</ul>';
        }
    }
    
}

?>