## PHP scripts to copy Flickr photos from one account (source/src) to another (destination/dest) 

The user authentication part is also implemented in order to retrieve both public and private photos with the original format. And, the meta info including title, descriptions, tags, photosets and visibility will be migrated as well.

### Step-by-step How-TO

**Step 1** Go to https://www.flickr.com/services/api/ to get your own API key and API secret

**Step 2** Update config.php to have your newly created API key and API secret in it

*=== Step 3-5 require you to sign in with the source Flickr account ===* 

**Step 3** php auth.php

**Step 4** Follow instructions you'll get from Step#3 to get **oauth_token**, **oauth_token_secret** and **user_id**

**Step 5** Update config.php and update **src_oauth_token**={value_you_get_from_step_4), **oauth_token_secret**={value_you_get_from_step_4) and **src_user_id**={value_you_get_from_step_4)

*=== Step 6 require you to sign in with the destination Flickr account ===* 

**Step 6** Repeat Step#3 - 5 to set up **dest_oauth_token** and **dest_oauth_token_secret** in config.php 

**Step 7** php main.php&
