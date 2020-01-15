<?php

class twitterapi {
    
    public static function postTweet($status) {
        $method = 'POST';
        $url = 'https://api.twitter.com/1.1/statuses/update.json';
        $oauth_nonce = date('Y\\ym\\md\\dH\\hi\\ms\\s').''.mt_rand(100000, 999999);
        $oauth_timestamp = time();
        $oauth_signature_method = 'HMAC-SHA1';
        $signature_base_string = $method.'&'.rawurlencode($url).'&oauth_consumer_key%3D'.TWITTER_CONSUMER_KEY.'%26oauth_nonce%3D'.$oauth_nonce.'%26oauth_signature_method%3D'.$oauth_signature_method.'%26oauth_timestamp%3D'.$oauth_timestamp.'%26oauth_token%3D'.TWITTER_ACCESS_TOKEN.'%26oauth_version%3D1.0%26status%3D'.rawurlencode(rawurlencode($status));
        $signing_key = rawurlencode(TWITTER_CONSUMER_SECRET).'&'.rawurlencode(TWITTER_ACCESS_TOKEN_SECRET);
        $oauth_signature = rawurlencode(base64_encode(hash_hmac('sha1', $signature_base_string, $signing_key, true)));
        $headers = array(
            'Authorization: OAuth oauth_consumer_key="'.TWITTER_CONSUMER_KEY.'", oauth_nonce="'.$oauth_nonce.'", oauth_signature="'.$oauth_signature.'", oauth_signature_method="'.$oauth_signature_method.'", oauth_timestamp="'.$oauth_timestamp.'", oauth_token="'.TWITTER_ACCESS_TOKEN.'", oauth_version="1.0"'
        );
        $body = 'status='.rawurlencode($status);
        return http::request($method, $url, $headers, $body);
    }
    
}

?>