<?php

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
      error_log($api_endpoint . "\n\n", 3, log_path);
   }
   
   $response = curl_exec($curl);
   curl_close($curl);

   if (debug) {
      error_log($response . "\n\n", 5, log_path);
   }

   if ($json_decode) {
      return json_decode($response);
   } else {
      return $response;
   }
}

