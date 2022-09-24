<?php

class CWikiDays {
    
    private $username = null;
    private $prefix = 0;
    private $project = 0;
    private $namespace = 0;
    private $redirects = true;
    private $timezone = 'UTC';
    private $timezone_infer = 'UTC';
    private $limit = 500;
    
    private $data = array();
    private $count = 0;
    private $longest_streak_count = 0;
    private $longest_streak_date = null;
    private $redirects_count = 0;
    
    public function __construct($username, $prefix, $project, $namespace, $redirects, $timezone, $limit) {
        $this->username = $username;
        $this->prefix = $prefix;
        $this->project = $project;
        $this->namespace = $namespace;
        $this->redirects = $redirects;
        $this->timezone = $timezone;
        $this->limit = $limit;
    }
    
    private function retrieveWikiTimezone() {
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
        $this->timezone_infer = $json->query->general->timezone;
    }
    
    private function retrieveData() {
        $usercontribs = array();
        $limit = $this->limit;
        $has_next = true;
        $uccontinue = null;
        while (($limit > 0) && $has_next) {
            $request_limit = 500;
            if ($limit < 500) {
                $request_limit = $limit;
            }
            $results = http::request('GET', 'https://'.$this->prefix.'.'.$this->project.'.org/w/api.php?action=query&format=json&list=usercontribs&ucuser='.urlencode($this->username).'&ucshow=new&ucnamespace='.$this->namespace.'&uclimit='.$request_limit.(!empty($uccontinue) ? '&uccontinue='.$uccontinue : ''));
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
            $usercontribs = array_merge($usercontribs, $json->query->usercontribs);
            if (!empty($json->continue->uccontinue)) {
                $uccontinue = $json->continue->uccontinue;
            } else {
                $has_next = false;
            }
            $limit -= 500;
        }
        return array_reverse($usercontribs);
    }
    
    private function retrieveRedirects($usercontribs) {
        $redirects = array();
        $chunks = array_chunk($usercontribs, 50);
        foreach ($chunks as $chunk) {
            $ids = array();
            foreach ($chunk as $row) {
                $ids[] = $row->pageid;
            }
            $results = http::request('GET', 'https://'.$this->prefix.'.'.$this->project.'.org/w/api.php?action=query&format=json&pageids='.implode('|', $ids).'&prop=info');
            if ($results === false) {
                throw new Exception('Unable to retrieve redirects (HTTP query, check that this wiki <code>'.$this->prefix.'.'.$this->project.'.org</code> exists).');
            }
            $json = json_decode($results);
            if (isset($json->error)) {
                throw new Exception('Error from Wikimedia server: <code>'.htmlentities($json->error->code).'</code>.');
            }
            if (!isset($json->query->pages)) {
                throw new Exception('Unable to retrieve redirects (invalid response, check that the wiki <code>'.$this->prefix.'.'.$this->project.'.org</code> exists).');
            }
            foreach ($json->query->pages as $row) {
                if (isset($row->redirect)) {
                    $redirects[] = $row->pageid;
                }
            }
        }
        return $redirects;
    }
    
    public function processData() {
        if ($this->timezone == 'wiki') {
            $this->retrieveWikiTimezone();
        } else {
            $this->timezone_infer = $this->timezone;
        }
        $usercontribs = $this->retrieveData();
        $redirects = $this->retrieveRedirects($usercontribs);
        $loop_date = null;
        $streak = 0;
        foreach ($usercontribs as &$row) {
            if (in_array($row->pageid, $redirects)) {
                $this->redirects_count++;
                if (!$this->redirects) {
                    continue;
                }
                $row->redirect = true;
            }
            $this->count++;
            $date = new DateTimeImmutable($row->timestamp);
            $date = $date->setTimezone(new DateTimeZone($this->timezone_infer));
            $row->date_local = $date;
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
        <p><input type="checkbox" name="redirects" id="redirects" value="true"'.($this->redirects ? ' checked="checked"' : '').' /> <label for="redirects">Include redirects</label></p>
        <p><label for="timezone">Timezone:</label> <select name="timezone" id="timezone"><option value="wiki"'.(($this->timezone == 'wiki') ? ' selected="selected"' : '').'>Wiki</option><option value="UTC"'.(($this->timezone == 'UTC') ? ' selected="selected"' : '').'>UTC</option>';
        $timezones = DateTimeZone::listIdentifiers();
        foreach ($timezones as $timezone) {
            if ($timezone != 'UTC') {
                echo '<option value="'.htmlentities($timezone).'"'.(($this->timezone == $timezone) ? ' selected="selected"' : '').'>'.htmlentities($timezone).'</option>';
            }
        }
        echo '</select></p>
        <p><label for="limit">Limit:</label> <input type="text" name="limit" id="limit" value="'.$this->limit.'" size="3" /></p>
        <p><input type="submit" value="Search" /></p>
        </form>';
    }

    public function displayResults() {
        if (count($this->data) == 0) {
            echo '<p>No creation found on this wiki :(</p>';
        } else {
            $labels = $this->retrieveLabels();
            echo '<p>'.$this->count.' creations found ('.($this->redirects ? 'including' : 'excluding').' '.$this->redirects_count.' redirect'.(($this->redirects_count > 1) ? 's' : '').') for <a href="https://'.$this->prefix.'.'.$this->project.'.org/wiki/User:'.urlencode(str_replace(' ', '_', $this->username)).'">User:'.htmlentities(str_replace(' ', '_', $this->username)).'</a> on <a href="https://'.$this->prefix.'.'.$this->project.'.org/">'.$this->prefix.'.'.$this->project.'.org</a>. Longest streak: '.$this->longest_streak_count.' day'.(($this->longest_streak_count > 1) ? 's' : '').', finished on '.$this->longest_streak_date->format('Y-m-d').' (timezone: '.htmlentities($this->timezone_infer).').</p>';
            echo '<ol reversed="true">';
            $previous_date = null;
            foreach (array_reverse($this->data) as $date_str => $date) {
                $items = array();
                foreach ($date as $row) {
                    $title = $row->title;
                    if (($this->project == 'wikidata') && (($this->namespace == 120) || ($this->namespace == 146))) {
                        $title = preg_replace('/^.*?:/', '', $title);
                    }
                    $item = '';
                    if (isset($row->redirect)) {
                        $item .= '<em>';
                    }
                    $item .= '<a href="https://'.$this->prefix.'.'.$this->project.'.org/wiki/'.urlencode(str_replace(' ', '_', $row->title)).'" title="'.$row->date_local->format('H:i:s').' ('.$row->date_local->format('e P').')">';
                    if (isset($labels[$title])) {
                        $item .= htmlentities($labels[$title]).' ('.htmlentities($title).')';
                    } else {
                        $item .= htmlentities($title);
                    }
                    $item .= '</a>';
                    if (isset($row->redirect)) {
                        $item .= '</em> (<abbr title="redirect">r</abbr>)';
                    }
                    $items[] = $item;
                }
                if (($previous_date != null) && ((new DateTime($previous_date))->sub(new DateInterval('P1D'))->format('Y-m-d') != $date_str)) {
                    echo '</ol><hr /><ol reversed="true">';
                }
                echo '<li><strong>'.$date_str.'</strong> <code>['.count($items).']</code> '.implode(', ', $items).'</li>';
                $previous_date = $date_str;
            }
            echo '</ol>';
        }
    }
    
}

?>