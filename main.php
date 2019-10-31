<?php
include('config.php');

const debug = false;

$photoset_dictionary = './photoset_dictionary.json';
$temp_photo_storage = '/tmp/flickr_downloads/';
$tag_to_mark_migration_status = 'migrated_already';

if (!file_exists($temp_photo_storage)) {
   system('mkdir ' . $temp_photo_storage);
}

$max_retry = 10;
$current_retry = 0;

$search = null;
for($j=$current_retry; $j<$max_retry; $j++) {

   $search = photos_search(src_user_id, 1, 1);

   if (isset($search->stat) && strcasecmp($search->stat, 'ok') === 0) {
      $current_retry = 0;
      break;
   }
   
   $current_retry++;
   sleep(random_int(1,3));
}

$pages = 2;//$search->photos->pages;
$per_page = 2;

for ($page = 1; $page <= $pages; $page++) {

   // photos_search
   $search = null;
   for ($j=$current_retry; $j<$max_retry; $j++) {

      $search = photos_search(src_user_id, $per_page, $page, $tag_to_mark_migration_status);

      if (isset($search->stat) && strcasecmp($search->stat, 'ok') === 0) {
         $current_retry = 0;
         break;
      }

      $current_retry++;
      sleep(random_int(1,3));
   }


   foreach ($search->photos->photo as $photo) {

      $farm = $photo->farm;
      $server = $photo->server;
      $id = $photo->id;

      // photos_getInfo
      $info = null;
      for ($j=$current_retry; $j<$max_retry; $j++) {

         $info = photos_getInfo($id);

         if (isset($info->stat) && strcasecmp($info->stat, 'ok') === 0) {
            $current_retry = 0;
            break;
         }

         $current_retry++;
         sleep(random_int(1,3));
      }

      $info = $info->photo;

      $osecret = $info->originalsecret;
      $oformat = $info->originalformat;
      $filename = $id . '_' . $osecret . '_o.' . $oformat;

      $original_photo = 'https://farm' . $farm . '.staticflickr.com/' . $server . '/' . $filename;

      system('curl -o ' . $temp_photo_storage . $filename . ' ' . $original_photo);

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

      // photos_getAllContexts for photosets
      $contexts = null;
      for ($j=$current_retry; $j<$max_retry; $j++) {
         
         $contexts = photos_getAllContexts($id);
         
         if (isset($contexts->stat) && strcasecmp($contexts->stat, 'ok') === 0) {
            $current_retry = 0;
            break;
         }

         $current_retry++;
         sleep(random_int(1,3));
      }


      $photosets = array();

      if (!empty($contexts->set)) {
         foreach($contexts->set as $set) {
            array_push($photosets, array('id' => $set->id, 'title' => $set->title));
         }
      }

      // args for upload
      $args = [
         'title' => $title,
         'description' => $description,
         'tags' => $tags,
         'is_public' => '0',
         'photo' => new \CurlFile($temp_photo_storage . $filename, mime_content_type($temp_photo_storage . $filename), 'photo'),
      ];

      // upload the photo
      $upload_res = null;
      $new_photo_id = null;
      for ($j=$current_retry; $j<$max_retry; $j++) {
         
         // uploading
         $upload_res = flickr_upload($args);
         preg_match("|<photoid>(.*)</photoid>|", $upload_res, $match);
         
         if (!empty($match)) {
            $new_photo_id = $match[1];
            $current_retry = 0;
            break;
         }

         $current_retry++;
         sleep(random_int(1,3));
      }
      
      // adding the newly uploaded photo into photosets 
      if (!empty($photosets) && !empty($new_photo_id)) {


         foreach($photosets as $photoset) {

            // check if the photoset exists
            if (file_exists($photoset_dictionary)) {
               $photoset_dic = json_decode(file_get_contents($photoset_dictionary), true);
            } else {
               $photoset_dic = array();
            }


            // add the new photo into an existing photoset
            if (array_key_exists($photoset['id'], $photoset_dic)) {

               $new_photoset_id = $photoset_dic[$photoset['id']]['new_id'];

               $photosets_addPhoto_res = null;
               for ($j=$current_retry; $j<$max_retry; $j++) {

                  // photosets_addPhoto
                  $photosets_addPhoto_res = photosets_addPhoto($new_photoset_id, $new_photo_id);       

                  if (isset($photosets_addPhoto_res->stat) && strcasecmp($photosets_addPhoto_res->stat, 'ok') === 0) {
                     $current_retry = 0;
                     break;
                  }

                  $current_retry++;
                  sleep(random_int(1,3));
               }

            } else {

               // if photoset doesn't exist, create one and add the new photo into it

               $photoset_create_res = null;

               for ($j=$current_retry; $j<$max_retry; $j++) {

                  // photosets_create
                  $photoset_create_res = photosets_create($photoset['title'], $new_photo_id);

                  if (isset($photoset_create_res->stat) && strcasecmp($photoset_create_res->stat, 'ok') === 0) {
                     $current_retry = 0;
                     break;
                  }

                  $current_retry++;
                  sleep(random_int(1,3));
               }

               $new_photoset_id = $photoset_create_res->photoset->id;

               $photoset_dic[$photoset['id']] =  array('new_id' => $new_photoset_id, 'title' => $photoset['title']);
               
               if (file_exists($photoset_dictionary)) {
                  $resource = fopen($photoset_dictionary, 'w+');
               } else {
                  $resource = fopen($photoset_dictionary, 'x+');
               }

               fwrite($resource, json_encode($photoset_dic,  JSON_UNESCAPED_UNICODE));
               fclose($resource);
            }
            
         }
      }

      // add a tag to mark the photo has been migrated
      photos_addTags($id, $tag_to_mark_migration_status);

      // delete the src photo download
      unlink($temp_photo_storage . $filename);

      
      // try not to hit the APIs aggressively
      sleep(1);
   }

}

function photos_addTags($photo_id, $tags)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photos.addTags';

   $params =
      "format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . src_oauth_token .
      "&oauth_version=" . oauth_version .
      "&photo_id=" . $photo_id .
      "&tags=" . rawurlencode($tags);

   $base_string = 'POST&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = api_secret . '&' . src_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;
   
   $args = array(
         'format' => 'json',
         'method' => $method,
         'nojsoncallback' => 1,
         'oauth_consumer_key' => api_key,
         'oauth_nonce' => $oauth_nonce,
         'oauth_signature_method' => oauth_signature_method,
         'oauth_timestamp' => $now,
         'oauth_token' => src_oauth_token,
         'oauth_version' => oauth_version,
         'photo_id' => $photo_id,
         'tags' => rawurlencode($tags),
         'oauth_signature' => $oauth_sig,
         );
   

   if (debug) {
      error_log("*** addTags *** \n", 3, 'error.log');
   }

   return http_request(api_endpoint, $params, $args, 'POST');
}

function photos_search($user_id, $per_page, $page, $tags_to_exclude = null)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photos.search';

   $params =
      "format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . src_oauth_token .
      "&oauth_version=" . oauth_version .
      "&page=" . $page .
      "&per_page=" . $per_page;

   if (!empty($tags_to_exclude)) {
      $params .= '&text=' . rawurlencode('-' . $tags_to_exclude);
   }

   $params .= "&user_id=" . $user_id;

   $base_string = 'GET&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = api_secret . '&' . src_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   if (debug) {
      error_log("*** search *** \n", 3, 'error.log');
   }

   return http_request(api_endpoint, $params);
}

function photos_getInfo($photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photos.getInfo';

   $params =
      "format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . src_oauth_token .
      "&oauth_version=" . oauth_version .
      "&photo_id=" . $photo_id;

   $base_string = 'GET&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = api_secret . '&' . src_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;
   
   
   if (debug) {
      error_log("*** getInfo *** \n", 3, 'error.log');
   }

   return http_request(api_endpoint, $params);
}

function photos_getAllContexts($photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photos.getAllContexts';

   $params =
      "format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . src_oauth_token .
      "&oauth_version=" . oauth_version .
      "&photo_id=" . $photo_id;

   $base_string = 'GET&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = api_secret . '&' . src_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;
   
   if (debug) {
      error_log("*** getAllContexts *** \n", 3, 'error.log');
   }

   return http_request(api_endpoint, $params);
}

function photosets_addPhoto($photoset_id, $photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photosets.addPhoto';

   $params = 
      "format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&photo_id=" . $photo_id .
      "&photoset_id=" . $photoset_id;

   $base_string = 'POST&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   $args = array(
         'format' => 'json',
         'method' => $method,
         'nojsoncallback' => 1,
         'oauth_consumer_key' => api_key,
         'oauth_nonce' => $oauth_nonce,
         'oauth_signature_method' => oauth_signature_method,
         'oauth_timestamp' => $now,
         'oauth_token' => dest_oauth_token,
         'oauth_version' => oauth_version,
         'photo_id' => $photo_id,
         'photoset_id' => $photoset_id,
         'oauth_signature' => $oauth_sig,
         );
   
   if (debug) {
      error_log("*** addPhoto *** \n", 3, 'error.log');
   }

   return http_request(api_endpoint, $params, $args, 'POST');
}

function photosets_create($title, $photo_id)
{
   $oauth_nonce = md5(uniqid(rand(), true));
   $now = time();
   $method = 'flickr.photosets.create';

   $params = 
      "format=json" .
      "&method=" . $method . 
      "&nojsoncallback=1" . 
      "&oauth_consumer_key=" . api_key . 
      "&oauth_nonce=" . $oauth_nonce . 
      "&oauth_signature_method=" . oauth_signature_method .
      "&oauth_timestamp=" . $now .
      "&oauth_token=" . dest_oauth_token .
      "&oauth_version=" . oauth_version .
      "&primary_photo_id=" . $photo_id .
      "&title=" . rawurlencode($title); 

   $base_string = 'POST&'. urlencode(api_endpoint) . '&' . urlencode($params);
   $hash_key = api_secret . '&' . dest_oauth_token_secret;
   $oauth_sig = base64_encode(hash_hmac('sha1', $base_string, $hash_key, true));

   $params .= '&oauth_signature=' . $oauth_sig;

   $args = array(
         'format' => 'json',
         'method' => $method,
         'nojsoncallback' => 1,
         'oauth_consumer_key' => api_key,
         'oauth_nonce' => $oauth_nonce,
         'oauth_signature_method' => oauth_signature_method,
         'oauth_timestamp' => $now,
         'oauth_token' => dest_oauth_token,
         'oauth_version' => oauth_version,
         'primary_photo_id' => $photo_id,
         'title' => $title,
         'oauth_signature' => $oauth_sig,
         );
   
   if (debug) {
      error_log("*** createPhotoSet *** \n\n", 3, 'error.log');
   }

   return http_request(api_endpoint, $params, $args, 'POST');

}

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

   if (debug) {
      error_log($api_endpoint . "\n\n", 3, 'error.log');
   }
   
   $response = curl_exec($curl);
   curl_close($curl);

   if (debug) {
      error_log($response . "\n\n", 3, 'error.log');
   }

   if ($json_decode) {
      return json_decode($response);
   } else {
      return $response;
   }
}

?>
