<?php

abstract class sparql {
    
    private static $last_query_time = null;
    
    protected static function a_query($endpoint_url, $endpoint_id, $query, $cache) {
        $query = trim($query);
        $query_hash = self::getQueryHash($query);
        $cache = max(300, $cache);
        $res = db::query('SELECT `query`, `response`, `last_update` FROM `query` WHERE `query_hash` = 0x'.$query_hash.' AND `endpoint_id` = \''.db::sec($endpoint_id).'\' AND `last_update` >= NOW() - INTERVAL '.$cache.' SECOND');
        if ($res->num_rows == 1) {
            $o = $res->fetch_object();
            if ($o->query !== $query) {
                throw new Exception('Internal error (SHA1 collision: 0x'.$query_hash.').');
            }
            $data = gzdecode($o->response);
            self::$last_query_time = $o->last_update;
        }
        else {
            $res = db::query('SELECT GET_LOCK(\''.$endpoint_id.'_sparql_lock\', 20) AS `lock`');
            if (($res->num_rows !== 1) || ($res->fetch_object()->lock !== '1')) {
                throw new Exception('Server is busy, try again later.');
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_USERAGENT, DICARE_USER_AGENT);
            curl_setopt($ch, CURLOPT_URL, $endpoint_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'query='.urlencode($query).'&format=json');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            curl_close($ch);
            if ($data === false) {
                throw new Exception('Error while querying SPARQL endpoint ('.$endpoint_id.')'."\n".$query);
            }
            db::query('INSERT INTO `query`(`query_hash`, `endpoint_id`, `last_update`, `query`, `response`) VALUES(0x'.$query_hash.', \''.db::sec($endpoint_id).'\', NOW(), \''.db::sec($query).'\', \''.db::sec(gzencode($data)).'\') ON DUPLICATE KEY UPDATE `last_update` = NOW(), `response` = \''.db::sec(gzencode($data)).'\'');
            self::$last_query_time = db::query('SELECT `last_update` FROM `query` WHERE `query_hash` = 0x'.$query_hash.' AND `endpoint_id` = \''.db::sec($endpoint_id).'\'')->fetch_row()[0];
            db::commit();
            db::query('SELECT RELEASE_LOCK(\''.$endpoint_id.'_sparql_lock\')');
        }
        return json_decode($data);
    }
    
    protected static function a_getQueryTime($endpoint_id, $query) {
        $query = trim($query);
        $res = db::query('SELECT `last_update` FROM `query` WHERE `query_hash` = 0x'.self::getQueryHash($query).' AND `endpoint_id` = \''.db::sec($endpoint_id).'\' AND `query` = \''.db::sec($query).'\'');
        if ($res->num_rows === 1) {
            return $res->fetch_object()->last_update;
        }
        return null;
    }
    
    private static function getQueryHash($query) {
        return sha1($query);
    }
    
    public static function getLastQueryTime() {
        return self::$last_query_time;
    }
    
}

?>