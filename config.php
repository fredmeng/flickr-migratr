<?php
const api_key = '';
const api_secret = '';

// source
const src_oauth_token = '';
const src_oauth_token_secret = '';
const src_user_id = '';

// destination
const dest_oauth_token = '';
const dest_oauth_token_secret = '';

const api_endpoint = 'https://www.flickr.com/services/rest/';
const oauth_version = '1.0';
const oauth_signature_method = 'HMAC-SHA1';

const debug = false;
const log_path = '/tmp/flickr_migratr_error.log';
const photoset_dictionary = './photoset_dictionary.json';
const temp_photo_storage = '/tmp/flickr_migratr_tmp/';
const tag_to_mark_migration_status = '__migrated_already__';
