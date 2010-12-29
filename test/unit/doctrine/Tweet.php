<?php
$path = realpath(dirname(__FILE__).'/../../bootstrap.php');
require_once $path;

$payload = <<<PAYLOAD
{"place":{"country":"United Kingdom","country_code":"","place_type":"poi","url":"http:\/\/api.twitter.com\/1\/geo\/id\/1c387f93e2896caf.json","attributes":{},"full_name":"Reading, Reading","name":"Reading","bounding_box":{"type":"Polygon","coordinates":[[[-1.052995,51.409779],[-0.928323,51.409779],[-0.928323,51.493078],[-1.052995,51.493078]]]},"id":"1c387f93e2896caf"},"favorited":false,"text":"meh","in_reply_to_screen_name":null,"in_reply_to_status_id_str":null,"in_reply_to_user_id":null,"contributors":null,"coordinates":null,"retweet_count":0,"source":"web","in_reply_to_user_id_str":null,"id_str":"19918670687899649","created_at":"Wed Dec 29 00:52:35 +0000 2010","retweeted":false,"in_reply_to_status_id":null,"user":{"statuses_count":11,"notifications":null,"profile_sidebar_fill_color":"DDEEF6","location":null,"profile_background_tile":false,"time_zone":"London","friends_count":2,"is_translator":false,"profile_link_color":"0084B4","description":null,"contributors_enabled":false,"verified":false,"favourites_count":0,"profile_sidebar_border_color":"C0DEED","url":null,"id_str":"228755437","created_at":"Mon Dec 20 15:54:37 +0000 2010","show_all_inline_media":false,"follow_request_sent":null,"geo_enabled":true,"profile_use_background_image":true,"profile_background_color":"C0DEED","protected":false,"profile_image_url":"http:\/\/a1.twimg.com\/a\/1292975674\/images\/default_profile_1_normal.png","lang":"en","profile_background_image_url":"http:\/\/a3.twimg.com\/a\/1292975674\/images\/themes\/theme1\/bg.png","followers_count":1,"name":"Capital Hotspots","following":null,"screen_name":"CapitalHotspots","id":228755437,"listed_count":0,"utc_offset":0,"profile_text_color":"333333"},"truncated":false,"id":19918670687899649,"entities":{"hashtags":[],"urls":[],"user_mentions":[]},"geo":null}
PAYLOAD;

$data = json_decode($payload);

$t = new lime_test;

$tweet = Tweet::hydrateFromDecodedResponse($data);

$t->is(is_float($tweet['latitude']),true,"Got a latitude");
$t->is(is_float($tweet['longitude']),true,"Got a longitude");

$tweet->save();

$data = json_decode('{"scrub_geo":{"user_id_str":"228755437","user_id":228755437,"up_to_status_id_str":"19918670687899649","up_to_status_id":19918670687899649}}');

$q = Doctrine::getTable('Tweet')
  ->createQuery('t')
  ->update()
  ->set('t.latitude','NULL')
  ->set('t.longitude','NULL')
  ->where('t.twitter_user_id = ?',$data->scrub_geo->user_id)
  ->andWhere('t.latitude IS NOT NULL');

$t->is($q->execute(),1,"Deleted geo data from geotagged tweets");
