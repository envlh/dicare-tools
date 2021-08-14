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
        $party->setConcepts(explode(' ', $this->concepts));
        $items = $party->queryItems(0);
        $party->computeItems($items);
        $this->date_start = $party->items_query_time;
        $this->results_start = serialize($items);
        db::query('UPDATE `lexemes_challenge` SET `date_start` = \''.$this->date_start.'\', `results_start` = \''.db::sec($this->results_start).'\' WHERE `id` = '.$this->id);
        db::commit();
        // tweeting
        if (LEXEMES_CHALLENGE_TWEETS === true) {
            $tweet = 'New Wikidata Lexemes Challenge! This week\'s theme: '.$this->title."\n".'Help improving lexicographical data on Wikidata. At the moment, there are '.count($party->lexemes).' lexemes in '.count($party->languages).' languages linked to the items of this challenge.'."\n".SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$this->id;
            $r = twitterapi::postTweet($tweet);
            $tweet_data = json_decode(substr($r, strpos($r, "\r\n\r\n")));
            db::query('UPDATE `lexemes_challenge` SET `initial_tweet` = \''.db::sec($tweet_data->id_str).'\' WHERE `id` = '.$this->id);
            db::commit();
        }
    }
    
    public function close() {
        $party = new LexemeParty();
        $party->setConcepts(explode(' ', $this->concepts));
        $items = $party->queryItems(0);
        $party->computeItems($items);
        $this->date_end = $party->items_query_time;
        $this->results_end = serialize($items);
        db::query('UPDATE `lexemes_challenge` SET `date_end` = \''.$this->date_end.'\', `results_end` = \''.db::sec($this->results_end).'\' WHERE `id` = '.$this->id);
        db::commit();
        // stats
        $this->generateStatistics();
        // tweeting
        if (LEXEMES_CHALLENGE_TWEETS === true) {
            $tweet = '@'.TWITTER_ACCOUNT.' The challenge is over! There are now '.count($party->lexemes).' lexemes in '.count($party->languages).' languages linked to the items of this challenge.'."\n".SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$this->id;
            twitterapi::postTweet($tweet, $this->initial_tweet);
        }
    }
    
    public function generateStatistics() {
        if (!empty($this->results_start) && !empty($this->results_end)) {
            $concepts = explode(' ', $this->concepts);
            $startParty = new LexemeParty();
            $startParty->setConcepts($concepts);
            $startParty->computeItems(unserialize($this->results_start));
            $endParty = new LexemeParty();
            $endParty->setConcepts($concepts);
            $endParty->computeItems(unserialize($this->results_end));
            $values = array();
            foreach ($endParty->languages as $language) {
                $language_qid = $language->qid;
                $completion = 0; // concepts with at least one lexeme
                $removed = 0;
                $added = 0;
                foreach ($concepts as $concept_qid) {
                    if (isset($startParty->items[$concept_qid][$language_qid])) {
                        $lexemes_start = array_keys($startParty->items[$concept_qid][$language_qid]);
                    } else {
                        $lexemes_start = array();
                    }
                    if (isset($endParty->items[$concept_qid][$language_qid])) {
                        $lexemes_end = array_keys($endParty->items[$concept_qid][$language_qid]);
                    } else {
                        $lexemes_end = array();
                    }
                    if (!empty($lexemes_end)) {
                        $completion++;
                    }
                    $intersect = array_intersect($lexemes_start, $lexemes_end);
                    if (count($intersect) < count($lexemes_start)) {
                        $removed += abs(count($intersect) - count($lexemes_start));
                    }
                    if (count($intersect) < count($lexemes_end)) {
                        $added += count($lexemes_end) - count($intersect);
                    }
                }
                $values[] = '('.$this->id.', '.substr($language_qid, 1).', '.$completion.', '.$removed.', '.$added.')';
            }
            if (!empty($values)) {
                db::query('DELETE FROM `lexemes_challenge_statistics` WHERE `challenge_id` = '.$this->id);
                db::query('INSERT INTO `lexemes_challenge_statistics` VALUES'.implode(',', $values));
                db::commit();
            }
        }
    }
    
}

?>