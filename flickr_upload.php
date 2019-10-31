<?php

function flickr_upload($args) 
{
   $upload_endpoint = 'https://up.flickr.com/services/upload/';

   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();

   $params = "description=" . rawurlencode($args['description']) .
      "&is_public=" . rawurlencode($args['is_public']) .
      "&oauth_consumer_key=" . api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&tags=" . rawurlencode($args['tags']) .
      "&title=" . rawurlencode($args['title']);

   $base_string = 'POST&'. urlencode($upload_endpoint) . '&' . urlencode($params);
   $hash_key = api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   $args['oauth_consumer_key'] = api_key;
   $args['oauth_nonce'] = $oauth_nonce;
   $args['oauth_signature_method'] = oauth_signature_method;
   $args['oauth_timestamp'] = $now;
   $args['oauth_token'] = dest_oauth_token;
   $args['oauth_version'] = oauth_version;
   $args['oauth_signature'] = $oauth_sig;

   return http_request($upload_endpoint, $params, $args, 'POST', false);
}

?>
