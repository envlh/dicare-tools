<?php

class LexemeChallenge {
    
    public $id;
    public $title;
    public $concepts;
    
    public static function getChallenge($id) {
        $res = db::query('SELECT * FROM `lexeme_challenge` WHERE `id` = '.$id);
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
        $this->date_start = $party->items_query_time;
        $this->results_start = serialize($items);
        db::query('UPDATE `lexeme_challenge` SET `date_start` = \''.$this->date_start.'\', `results_start` = \''.db::sec($this->results_start).'\' WHERE `id` = '.$this->id);
    }
    
    public function close() {
        $party = new LexemeParty();
        $party->setConcepts(explode(' ', $this->concepts));
        $items = $party->queryItems(0);
        $this->date_end = $party->items_query_time;
        $this->results_end = serialize($items);
        db::query('UPDATE `lexeme_challenge` SET `date_end` = \''.$this->date_end.'\', `results_end` = \''.db::sec($this->results_end).'\' WHERE `id` = '.$this->id);
    }
    
}

?>