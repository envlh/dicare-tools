<?php

class LexemeChallenge {
    
    public $id;
    public $title;
    public $concepts;
    public $date_start;
    public $date_end;
    public $results_start;
    public $results_end;
    public $initial_tweet;
    
    public static function getChallenge($id) {
        $res = db::query('SELECT * FROM `lexemes_challenge` WHERE `id` = '.$id.' AND `date_start` IS NOT NULL');
        if (mysqli_num_rows($res) === 1) {
            return $res->fetch_object('LexemeChallenge');
        } else {
            return null;
        }
    }
    
    public static function getCurrentChallenge() {
        $res = db::query('SELECT * FROM `lexemes_challenge` WHERE `date_start` IS NOT NULL AND `date_end` IS NULL LIMIT 1');
        if (mysqli_num_rows($res) === 1) {
            return $res->fetch_object('LexemeChallenge');
        } else {
            return null;
        }
    }

    public static function findNewChallenge() {
        $res = db::query('SELECT * FROM `lexemes_challenge` WHERE `date_start` IS NULL AND `date_scheduled` <= NOW() ORDER BY `date_scheduled` LIMIT 1');
        if (mysqli_num_rows($res) === 1) {
            return $res->fetch_object('LexemeChallenge');
        } else {
            return null;
        }
    }
    
    public function open() {
        $party = new LexemeParty();
        $party->setPath(self::getPath());
        $party->setConcepts(explode(' ', $this->concepts));
        $items = $party->queryItems(0);
        $party->computeItems($items);
        $this->date_start = $party->items_query_time;
        $this->results_start = serialize($items);
        db::query('UPDATE `lexemes_challenge` SET `date_start` = \''.$this->date_start.'\', `results_start` = \''.db::sec($this->results_start).'\' WHERE `id` = '.$this->id);
        db::commit();
        // tweeting
        if (LEXEMES_CHALLENGE_TWEETS === true) {
            $tweet = 'New #Wikidata #Lexemes Challenge! This week\'s theme: '.$this->title."\n".'Help improving lexicographical data on @wikidata. At the moment, there are '.count($party->lexemes).' lexemes in '.count($party->languages).' languages linked to the items of this challenge:'."\n".SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$this->id;
            $tweet_data = json_decode(twitterapi::postTweet($tweet));
            db::query('UPDATE `lexemes_challenge` SET `initial_tweet` = \''.db::sec($tweet_data->id_str).'\' WHERE `id` = '.$this->id);
            db::commit();
        }
    }
    
    public function close() {
        $party = new LexemeParty();
        $party->setPath(self::getPath());
        $party->setConcepts(explode(' ', $this->concepts));
        $items = $party->queryItems(0);
        $party->computeItems($items);
        $this->date_end = $party->items_query_time;
        $this->results_end = serialize($items);
        // rankings
        $referenceParty = new LexemeParty();
        $referenceParty->setPath(self::getPath());
        $referenceParty->setConcepts(explode(' ', $this->concepts));
        $items = unserialize($this->results_start);
        $referenceParty->computeItems($items);
        $rankings = LexemeParty::generateRankings($referenceParty, $party);
        // statistics
        $statistics = $this->generateStatistics($referenceParty, $party);
        // db
        db::query('UPDATE `lexemes_challenge` SET `date_end` = \''.$this->date_end.'\', `results_end` = \''.db::sec($this->results_end).'\', `lexemes_improved` = '.$statistics->lexemes_improved.', `languages_improved` = '.$statistics->languages_improved.', `distinct_editors` = '.$statistics->distinct_editors.' WHERE `id` = '.$this->id);
        $this->saveRankings($rankings);
        db::commit();
        // tweeting
        if (LEXEMES_CHALLENGE_TWEETS === true) {
            $tweet = '@'.TWITTER_ACCOUNT.' This challenge is now over, with '.$statistics->distinct_editors.' editors improving '.$statistics->lexemes_improved.' lexemes in '.$statistics->languages_improved.' languages:'."\n".SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$this->id;
            twitterapi::postTweet($tweet, $this->initial_tweet);
        }
    }
    
    public function saveRankings($rankings) {
        $values = array();
        foreach ($rankings as $ranking) {
            $values[] = '('.$this->id.', '.substr($ranking->language_qid, 1).', '.$ranking->completion.', '.$ranking->removed.', '.$ranking->added.')';;
        }
        if (!empty($values)) {
            db::query('DELETE FROM `lexemes_challenge_statistics` WHERE `challenge_id` = '.$this->id);
            db::query('INSERT INTO `lexemes_challenge_statistics` VALUES'.implode(',', $values));
        }
    }
    
    public function generateStatistics($startParty, $endParty) {
        $users = array();
        $languages = array();
        $edited_lexemes = 0;
        $lexemes = array_unique(array_merge(array_keys($startParty->lexemes), array_keys($endParty->lexemes)));
        foreach ($lexemes as $lexeme) {
            $data = json_decode(http::request('GET', 'https://www.wikidata.org/w/api.php?action=query&prop=revisions&titles=Lexeme:'.$lexeme.'&rvprop=timestamp|user|userid&rvlimit=500&rvdir=newer&rvstart='.str_replace(' ', 'T', $this->date_start).'Z&rvend='.str_replace(' ', 'T', $this->date_end).'Z&format=json'));
            $pageId = key($data->query->pages);
            if (isset($data->query->pages->$pageId->revisions)) {
                $revisions = $data->query->pages->$pageId->revisions;
                if (count($revisions) >= 1) {
                    $edited_lexemes++;
                    if (isset($endParty->lexemes[$lexeme]) && !in_array($endParty->lexemes[$lexeme], $languages)) {
                        $languages[] = $endParty->lexemes[$lexeme];
                    } elseif (isset($startParty->lexemes[$lexeme]) && !in_array($startParty->lexemes[$lexeme], $languages)) {
                        $languages[] = $startParty->lexemes[$lexeme];
                    }
                    foreach ($revisions as $revision) {
                        if (!isset($users[$revision->user])) {
                            $users[$revision->user] = $revision->userid;
                        }
                    }
                }
            }
        }
        return (object) ['lexemes_improved' => $edited_lexemes, 'languages_improved' => count($languages), 'distinct_editors' => count($users)];
    }
    
    public static function getPath() {
        return 'wdt:'.implode('|wdt:', array_keys(LEXEMES_CHALLENGE_PROPERTIES));
    }
    
    public static function getPropertiesList() {
        $properties = array();
        foreach (LEXEMES_CHALLENGE_PROPERTIES as $pid => $label) {
            $properties[] = '<a href="https://www.wikidata.org/wiki/Property:'.$pid.'"><em>'.htmlentities($label).'</em> ('.$pid.')</a>';
        }
        return implode(', ', $properties);
    }
    
}

?>