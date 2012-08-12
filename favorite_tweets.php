<?php
// Run as cron job every 1 minute
// Make sure INDEX file is created with right permissions
set_time_limit(0);
date_default_timezone_set('America/New_York'); 

define('EMAIL', '"Eric Kerr" <EricPKerr@gmail.com>');
define('USERNAME', 'erickerr');

define('INDEX_FILE', 'favorite_tweets_index.txt');
define('INDEX_SIZE', 100); // Store most recent tweet ids to account for tweets out of order

define('FAVORITES_URL', 'http://api.twitter.com/1/favorites.json?screen_name=' . USERNAME . '&count=30&include_entities=true');
define('TIMELINE_URL', 'http://api.twitter.com/1/statuses/user_timeline.json?screen_name=' . USERNAME . '&count=30include_entities=true');

function fetch_new_tweets(){
  $fh = fopen(INDEX_FILE, 'r+');
  $index = @json_decode(stream_get_contents($fh));
  if(!$index) $index = array();
  
  $tweets = @json_decode(file_get_contents(FAVORITES_URL));
  $tweets = (array) $tweets;
  
  if(count($tweets) > 0){
    foreach($tweets as $tweet){
      $tweet_id = (int)$tweet->id;
      if(array_search($tweet_id, $index) !== false){
        continue; // Already been indexed
      }
      $index []= $tweet_id;
      
      $urls = $tweet->entities->urls;
      
      if(count($urls) > 0){
        $text = $tweet->text;
        $who = $tweet->user->screen_name;
        $name = $tweet->user->name;
        $thumb = $tweet->user->profile_image_url;
        $date = date('n/j/y g:i A', strtotime($tweet->created_at));
        
        $subject = "@$who, " . $date;
        
        $message = '<table><tr><td><img src="' . $thumb . '" style="margin:8px;width:48px;"></td><td><b>' . $name . ' (<a href="http://twitter.com/' . $who . '">@' . $who .'</a>)</b><br/>';
        $message .= '<a href="http://twitter.com/' . $who . '/status/' . $tweet_id . '">' . $date . '</a><br/>';
        $message .= $text . '</td></tr></table><br/>';
        
        foreach($urls as $url){
          $real_url = real_url($url->expanded_url);
          $message .= '<a href="' . $real_url . '">' . $real_url . '</a><br/>';
        }
        
        $message .= '<br/><br/>Sent from Favorited Tweet';
        
        $headers = 'Content-type: text/html' . "\r\n" .
          'From: eric@erickerr.com' . "\r\n" .
          'Reply-To: eric@erickerr.com' . "\r\n" .
          'X-Mailer: PHP/' . phpversion();
        
        mail(EMAIL, $subject, $message, $headers);
      }
    }
    
    //Maintain largest INDEX_SIZE set of ids
    asort($index);
    $index = array_slice($index, -1 * INDEX_SIZE);
  }
  
  $index = json_encode($index); //Convert back to JSON string
  
  ftruncate($fh, 0);
  fseek($fh, 0);
  fwrite($fh, $index, strlen($index));
  fclose($fh);
}

function real_url($url){
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  $response = curl_exec($ch);
  curl_close($ch);
  
  $response = explode('Location: ', $response);
  if(count($response) > 1){
    $response = explode("\n", $response[count($response) - 1]);
    $url = trim($response[0]);
  }
  
  return $url;
}

fetch_new_tweets();
sleep(30); // Close enough to every 30 seconds
fetch_new_tweets();
echo 'DONE!';