<?php

include('config.php');
include('flickr_upload.php');
include('http_request.php');
include('photos_addTags.php');
include('photos_getAllContexts.php');
include('photos_getInfo.php');
include('photos_search.php');
include('photosets_addPhoto.php');
include('photosets_create.php');

if (!file_exists(temp_photo_storage)) {
   system('mkdir ' . temp_photo_storage);
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

$total_pages = $search->photos->pages;
$per_page = 100;

for ($page = 1; $page <= $total_pages; $page++) {

   // photos_search
   $search = null;
   for ($j=$current_retry; $j<$max_retry; $j++) {

      $search = photos_search(src_user_id, $per_page, $page, tag_to_mark_migration_status);

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

      // building the original photo url
      $original_photo = str_replace(array('%farm-id%', '%server-id%', '%filename%'), array($farm, $server, $filename), original_photo_url);

      // fetching the original photo
      system('curl -o ' . temp_photo_storage . $filename . ' ' . $original_photo);

      // photo title & description
      $title = $info->title->_content;
      $description = $info->description->_content;

      // photo permissions
      $is_public = $info->visibility->ispublic; 
      $is_friend = $info->visibility->isfriend;
      $is_family = $info->visibility->isfamily;

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
         'is_friend' => $is_friend,
         'is_family' => $is_family,
         'is_public' => $is_public,
         'photo' => new \CurlFile(temp_photo_storage . $filename, mime_content_type(temp_photo_storage . $filename), 'photo'),
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
            if (file_exists(photoset_dictionary)) {
               $photoset_dic = json_decode(file_get_contents(photoset_dictionary), true);
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
               
               if (file_exists(photoset_dictionary)) {
                  $resource = fopen(photoset_dictionary, 'w+');
               } else {
                  $resource = fopen(photoset_dictionary, 'x+');
               }

               fwrite($resource, json_encode($photoset_dic,  JSON_UNESCAPED_UNICODE));
               fclose($resource);
            }
            
         }
      }

      // add a tag to mark the photo has been migrated
      photos_addTags($id, tag_to_mark_migration_status);

      // delete the src photo download
      unlink(temp_photo_storage . $filename);

      
      // try not to hit the APIs aggressively
      sleep(1);
   }

}

?>
