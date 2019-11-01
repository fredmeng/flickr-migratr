<?php
include("config.php");

$api_request_token = 'https://www.flickr.com/services/oauth/request_token';
$api_access_token = 'https://www.flickr.com/services/oauth/access_token';
$api_oauth_authorize = 'https://www.flickr.com/services/oauth/authorize?oauth_token=%oauth_token%&perms=write';

$oauth_nonce = md5(uniqid(rand(), true));
$now = time();


$request_token_param = 'oauth_callback=http%3A%2F%2Fec1234-o-0-0-o.ap-southeastwest-1234.staticflickr.com&oauth_consumer_key=' . api_key . '&oauth_nonce=' . $oauth_nonce  . '&oauth_signature_method=HMAC-SHA1&oauth_timestamp=' . $now . '&oauth_version=1.0';

//echo "\n\n DEBUG: GET&" . urlencode($api_request_token) . '&' . urlencode($param) . "\n\n";

$oauth_sig = base64_encode(hash_hmac('sha1', 'GET&'. urlencode($api_request_token) . '&' . urlencode($request_token_param),  api_secret . '&', true));

$request_token_url = $api_request_token . '?' . $request_token_param . '&oauth_signature=' . $oauth_sig;

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($curl, CURLOPT_URL, $request_token_url);
$result = curl_exec($curl);
curl_close($curl);

preg_match('|oauth_token=(.*?)&|', $result, $oauth_token);
preg_match('|oauth_token_secret=(.*?)$|', $result, $oauth_token_secret);

$api_oauth_authorize_url = str_replace('%oauth_token%', $oauth_token[1], $api_oauth_authorize);

echo "\nStep 1: Enter the URL ($api_oauth_authorize_url) in your browser and hit the 'OK, I'LL AUTHORIZE IT' button.\n";
echo "\nStep 2: After hitting the button, you will be redirected to a website. Don't worry about the 404 page not found message. All you'll need to do is to copy the entire URL from the browser address bar and then run the command below:\n\n=> php get_access_token.php --oauthtokensecret=\"" . $oauth_token_secret[1] . "\" --url=\"the_url_you_copied_from_browser\"\n\n"

?>
