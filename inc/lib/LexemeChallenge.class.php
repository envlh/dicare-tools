<?php

class LexemeChallenge {
    
    public $id;
    public $title;
    public $concepts;
    public $initial_tweet;
    
    public static function getChallenge($id) {
        $res = db::query('SELECT * FROM `lexeme_challenge` WHERE `id` = '.$id.' AND `date_start` IS NOT NULL');
        if (mysqli_num_rows($res) === 1) {
            return $res->fetch_object('LexemeChallenge');
        } else {
            return null;
        }
    }
    
    public static function getCurrentChallenge() {
        $res = db::query('SELECT * FROM `lexeme_challenge` WHERE `date_start` IS NOT NULL AND `date_end` IS NULL LIMIT 1');
        if (mysqli_num_rows($res) === 1) {
            return $res->fetch_object('LexemeChallenge');
        } else {
            return null;
        }
    }

    public static function findNewChallenge() {
        $res = db::query('SELECT * FROM `lexeme_challenge` WHERE `date_start` IS NULL AND `date_scheduled` <= NOW() ORDER BY `date_scheduled` LIMIT 1');
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
        db::query('UPDATE `lexeme_challenge` SET `date_start` = \''.$this->date_start.'\', `results_start` = \''.db::sec($this->results_start).'\' WHERE `id` = '.$this->id);
        db::commit();
        // tweeting
        if (LEXEMES_CHALLENGE_TWEETS === true) {
            $tweet = 'New Wikidata Lexemes Challenge! This week\'s theme: '.$this->title."\n".'Help improving lexicographical data on Wikidata. At the moment, there are '.count($party->lexemes).' lexemes in '.count($party->languages).' languages linked to the items of this challenge.'."\n".SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$this->id;
            $r = twitterapi::postTweet($tweet);
            $tweet_data = json_decode(substr($r, strpos($r, "\r\n\r\n")));
            db::query('UPDATE `lexeme_challenge` SET `initial_tweet` = \''.db::sec($tweet_data->id_str).'\' WHERE `id` = '.$this->id);
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
        db::query('UPDATE `lexeme_challenge` SET `date_end` = \''.$this->date_end.'\', `results_end` = \''.db::sec($this->results_end).'\' WHERE `id` = '.$this->id);
        db::commit();
        // tweeting
        if (LEXEMES_CHALLENGE_TWEETS === true) {
            $tweet = '@'.TWITTER_ACCOUNT.' The challenge is over! There are now '.count($party->lexemes).' lexemes in '.count($party->languages).' languages linked to the items of this challenge.'."\n".SITE_DIR.LEXEMES_SITE_DIR.'challenge.php?id='.$this->id;
            twitterapi::postTweet($tweet, $this->initial_tweet);
        }
    }
    
}

?>