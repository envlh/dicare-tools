<?php

class twitterapi {
    
    public static function postTweet($status, $reply_to = null) {
        $method = 'POST';
        $url = 'https://api.twitter.com/1.1/statuses/update.json';
        $parameters = array(
            'oauth_consumer_key' => TWITTER_CONSUMER_KEY,
            'oauth_nonce' => date('Y\\ym\\md\\dH\\hi\\ms\\s').''.mt_rand(100000, 999999),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => TWITTER_ACCESS_TOKEN,
            'oauth_version' => '1.0',
            'status' => $status
        );
        if (!empty($reply_to)) {
            $parameters['in_reply_to_status_id'] = $reply_to;
        }
        ksort($parameters);
        $parameters_strings = array();
        foreach ($parameters as $key => &$value) {
            $parameters_strings[] = rawurlencode($key).'='.rawurlencode($value);
        }
        $signature_base_string = $method.'&'.rawurlencode($url).'&'.rawurlencode(implode('&', $parameters_strings));
        $signing_key = rawurlencode(TWITTER_CONSUMER_SECRET).'&'.rawurlencode(TWITTER_ACCESS_TOKEN_SECRET);
        $oauth_signature = rawurlencode(base64_encode(hash_hmac('sha1', $signature_base_string, $signing_key, true)));
        $headers = array(
            'Authorization: OAuth oauth_consumer_key="'.TWITTER_CONSUMER_KEY.'", oauth_nonce="'.$parameters['oauth_nonce'].'", oauth_signature="'.$oauth_signature.'", oauth_signature_method="'.$parameters['oauth_signature_method'].'", oauth_timestamp="'.$parameters['oauth_timestamp'].'", oauth_token="'.TWITTER_ACCESS_TOKEN.'", oauth_version="1.0"'
        );
        $body = 'status='.rawurlencode($status);
        if (!empty($reply_to)) {
            $body = 'in_reply_to_status_id='.$reply_to.'&'.$body;
        }
        return http::request($method, $url, $headers, $body);
    }
    
}

?>