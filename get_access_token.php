<?php
include('config.php');

$api_access_token = 'https://www.flickr.com/services/oauth/access_token';
$oauth_nonce = md5(uniqid(rand(), true));
$now = time();

$long = array('oauthtokensecret:', 'url:');
$opt = getopt('', $long);

preg_match('|oauth_token=(.*?)&|', $opt['url'], $oauth_token);
preg_match('|oauth_verifier=(.*?)$|', $opt['url'], $oauth_verifier);

$param = 'oauth_consumer_key=' . api_key .  '&oauth_nonce=' . $oauth_nonce . '&oauth_signature_method=HMAC-SHA1&oauth_timestamp=' . $now . '&oauth_token=' . $oauth_token[1] . '&oauth_verifier=' . $oauth_verifier[1] . '&oauth_version=1.0';

$oauth_sig = base64_encode(hash_hmac('sha1', 'GET&'. urlencode($api_access_token) . '&' . urlencode($param), api_secret . '&' . $opt['oauthtokensecret'], true)); 

$access_token_url = $api_access_token . '?' . $param . '&oauth_signature=' . $oauth_sig;

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($curl, CURLOPT_URL, $access_token_url);
$result = curl_exec($curl);
curl_close($curl);

preg_match('|oauth_token=(.*?)&|', $result, $oauth_token);
preg_match('|oauth_token_secret=(.*?)&|', $result, $oauth_token_secret);
preg_match('|user_nsid=(.*?)&|', $result, $user_nsid);

echo "\n* oauth_token: $oauth_token[1]\n";
echo "* oauth_token_secret: $oauth_token_secret[1]  \n";
echo "* user_id: $user_nsid[1] \n";

?>
