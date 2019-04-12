<?php

class parameter {
    
    public static function get($key) {
        $res = db::query('SELECT `value` FROM `parameter` WHERE `key` = \''.db::sec($key).'\'');
        if ($res->num_rows !== 1) {
            return null;
        }
        return $res->fetch_object()->value;
    }
    
    public static function set($key, $value) {
        db::query('INSERT INTO `parameter`(`key`, `value`) VALUES(\''.db::sec($key).'\', \''.db::sec($value).'\') ON DUPLICATE KEY UPDATE `value` = \''.db::sec($value).'\'');
    }
    
}

?>