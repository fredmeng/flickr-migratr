<?php

// source
const src_api_key = '';
const src_api_secret = '';
const src_user_id = '';

// destination
const dest_api_key = '';
const dest_api_secret = '';
const dest_oauth_token = '';
const dest_oauth_token_secret = '';

const api_endpoint = 'https://www.flickr.com/services/rest/';
const photoset_dictionary = './photoset_dictionary.json'; 
const oauth_version = '1.0';
const oauth_signature_method = 'HMAC-SHA1';

const temp_photo_storage = '/tmp/flickr_downloads/';

if (!file_exists(temp_photo_storage)) {
   system('mkdir ' . temp_photo_storage);
}

$per_page = 1;

$search = photos_search(src_user_id, $per_page);
$search = $search->photos;
$page = $search->page;
$pages = 1;//$search->pages;

for ($i=$page; $i<=$pages; $i++) {

   $search = photos_search(src_user_id, $per_page);
   $search = $search->photos;

   $photos = $search->photo;

   foreach ($photos as $photo) {

      $farm = $photo->farm;
      $server = $photo->server;
      $id = $photo->id;

      $info = photos_getInfo($id);
      $info = $info->photo;

      $osecret = $info->originalsecret;
      $oformat = $info->originalformat;
      $filename = $id . '_' . $osecret . '_o.' . $oformat;

      $original_photo = 'https://farm' . $farm . '.staticflickr.com/' . $server . '/' . $filename;

      system('curl -o ' . temp_photo_storage . $filename . ' ' . $original_photo);

      $title = $info->title->_content;
      $description = $info->description->_content;

      // tags
      $tags = '';

      foreach ($info->tags->tag as $t) {
         $tags .= $t->raw . ' ';
      }

      // old user id
      $tags .= 'old_user_id_' . src_user_id . ' ';

      // old photo id
      $tags .= 'old_photo_id_' . $id; 

      // photoset
      $contexts = photos_getAllContexts($id);
      $sets = $contexts->set;

      $photosets = array();
      foreach($sets as $set) {
         array_push($photosets, $set->title);
      }

      // upload
      $args = [
         'title' => $title,
         'description' => $description,
         'tags' => $tags,
         'is_public' => '0',
         'photo' => new \CurlFile(temp_photo_storage . $filename, mime_content_type(temp_photo_storage . $filename), 'photo'),
      ];

      // upload the photo
      $response = flickr_upload($args);
      
      // adding the newly uploaded photo into photosets 
      if (preg_match("|<photoid>(.*)</photoid>|", $response, $match)) { 

         $new_photo_id = str_replace(array('<photoid>','</photoid>'), array('',''), $match[0]); 

         foreach($photosets as $photoset) {

            // check if the photoset exists
            if (file_exists(photoset_dictionary)) {

               $photoset_dic = json_decode(file_get_contents(photoset_dictionary), true);
               $key = md5($photoset);

               if (array_key_exists($key, $photoset_dic)) {
                  $photoset_id = $photoset_dic[$key];
               }
            }

            // if photoset doesn't exist, create one and add the new photo into it
            if (!isset($photoset_id)) {

               $response = photosets_create($photoset, $new_photo_id);
               $photoset_id = $response->photoset->id;

               if (!isset($photoset_dic)) {
                  $photoset_dic = array();
               }

               $photoset_dic[md5($photoset)]  = $photoset_id;

               if (file_exists(photoset_dictionary)) {
                  $resource = fopen(photoset_dictionary, 'w+');
               } else {
                  $resource = fopen(photoset_dictionary, 'x+');
               }

               fwrite($resource, json_encode($photoset_dic));
               fclose($resource);

            } else {
               // add the new photo into an existing photoset
               $response = photosets_addPhoto($photoset_id, $new_photo_id);       
            }
         }
      }


      // delete the src photo
      unlink(temp_photo_storage . $filename);
      
      // try not to hit the APIs aggressively
      sleep(random_int(1,3));
   }

}

function photos_search($user_id, $per_page)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photos.search';

   $params =
      "api_key=" . dest_api_key . 
      "&format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . dest_api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&per_page=" . $per_page .
      "&user_id=" . $user_id;

   $base_string = 'GET&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = dest_api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   return http_request(api_endpoint, $params);
}

function photos_getInfo($photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photos.getInfo';

   $params =
      "api_key=" . dest_api_key . 
      "&format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . dest_api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&photo_id=" . $photo_id;

   $base_string = 'GET&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = dest_api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   return http_request(api_endpoint, $params);
}

function photos_getAllContexts($photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photos.getAllContexts';

   $params =
      "api_key=" . dest_api_key . 
      "&format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . dest_api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&photo_id=" . $photo_id;

   $base_string = 'GET&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = dest_api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   return http_request(api_endpoint, $params);
}

function photosets_addPhoto($photoset_id, $photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photosets.addPhoto';

   $params = 
      "api_key=" . dest_api_key . 
      "&format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . dest_api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&photo_id=" . $photo_id .
      "&photoset_id=" . $photoset_id;

   $base_string = 'POST&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = dest_api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   $args = array(
         'api_key' => dest_api_key,
         'format' => 'json',
         'method' => $method,
         'nojsoncallback' => 1,
         'oauth_consumer_key' => dest_api_key,
         'oauth_nonce' => $oauth_nonce,
         'oauth_signature_method' => oauth_signature_method,
         'oauth_timestamp' => $now,
         'oauth_token' => dest_oauth_token,
         'oauth_version' => oauth_version,
         'photo_id' => $photo_id,
         'photoset_id' => $photoset_id,
         'oauth_signature' => $oauth_sig,
         );

   return http_request(api_endpoint, $params, $args, 'POST');
}

function photosets_create($title, $photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photosets.create';

   $params = 
      "api_key=" . dest_api_key . 
      "&format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . dest_api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&primary_photo_id=" . $photo_id .
      "&title=" . rawurlencode($title); 

   $base_string = 'POST&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = dest_api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   $args = array(
         'api_key' => dest_api_key,
         'format' => 'json',
         'method' => $method,
         'nojsoncallback' => 1,
         'oauth_consumer_key' => dest_api_key,
         'oauth_nonce' => $oauth_nonce,
         'oauth_signature_method' => oauth_signature_method,
         'oauth_timestamp' => $now,
         'oauth_token' => dest_oauth_token,
         'oauth_version' => oauth_version,
         'primary_photo_id' => $photo_id,
         'title' => $title,
         'oauth_signature' => $oauth_sig,
         );

   return http_request(api_endpoint, $params, $args, 'POST');

}

function flickr_upload($args) 
{
   $upload_endpoint = 'https://up.flickr.com/services/upload/';

   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();

   $params = "description=" . rawurlencode($args['description']) .
      "&is_public=" . rawurlencode($args['is_public']) .
      "&oauth_consumer_key=" . dest_api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&tags=" . rawurlencode($args['tags']) .
      "&title=" . rawurlencode($args['title']);

   $base_string = 'POST&'. urlencode($upload_endpoint) . '&' . urlencode($params);
   $hash_key = dest_api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   $args['oauth_consumer_key'] = dest_api_key;
   $args['oauth_nonce'] = $oauth_nonce;
   $args['oauth_signature_method'] = oauth_signature_method;
   $args['oauth_timestamp'] = $now;
   $args['oauth_token'] = dest_oauth_token;
   $args['oauth_version'] = oauth_version;
   $args['oauth_signature'] = $oauth_sig;

   return http_request($upload_endpoint, $params, $args, 'POST', false);
}

function http_request($api_endpoint, $params = null, $args = null, $method = 'GET', $json_decode = true)
{
   $curl = curl_init();

   curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
   curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

   if (strcasecmp($method, 'GET') === 0) {
      $api_endpoint .= '?' . $params;
   }

   curl_setopt($curl, CURLOPT_URL, $api_endpoint);

   if (strcasecmp($method, 'POST') === 0) {
      curl_setopt($curl, CURLOPT_POST, TRUE);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
   }

   $response = curl_exec($curl);
   curl_close($curl);

   if ($json_decode) {
      return json_decode($response);
   } else {
      return $response;
   }
}

?>
