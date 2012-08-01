<?php
// Run as cron job every 1 minute
// Make sure INDEX file is created with right permissions
set_time_limit(0);

define('EMAIL', 'EricPKerr@gmail.com');
define('USERNAME', 'erickerr');

define('INDEX_FILE', 'favorite_tweets_index.txt');
define('INDEX_SIZE', 100); // Store most recent tweet ids to account for tweets out of order

define('FAVORITES_URL', 'http://api.twitter.com/1/favorites.json?screen_name=' . USERNAME . '&count=20&include_entities=true');
define('TIMELINE_URL', 'http://api.twitter.com/1/statuses/user_timeline.json?screen_name=' . USERNAME . '&include_entities=true');

function fetch_new_tweets(){
  $fh = fopen(INDEX_FILE, 'r+');
  $index = @json_decode(stream_get_contents($fh));
  if(!$index) $index = array();
  
  $tweets = @json_decode(file_get_contents(FAVORITES_URL));
  $tweets = (array) $tweets;
  
  if(count($tweets) > 0){
    foreach($tweets as $tweet){
      print_r($tweet); die();
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
        $date = date('n/j/y g:i A', strtotime($tweet->created_at));
        
        $subject = "@$who, " . $date;
        
        $message = '<b>' . $name . '(<a href="http://twitter.com/' . $who . '">@' . $who .'</a>)</b><br/>';
        $message .= '<a href="http://twitter.com/' . $who . '/status/' . $tweet_id . '">' . $date . '</a><br/>';
        $message .= $text . '</br><br/>';
        
        foreach($urls as $url){
          $message .= '<a href="' . $url->expanded_url . '">' . $url->expanded_url . '</a><br/>';
        }
        
        $headers = 'Content-type: text/html' . "\r\n" .
          'From: ' . EMAIL . "\r\n" .
          'Reply-To: ' . EMAIL . "\r\n" .
          'X-Mailer: PHP/' . phpversion();
        
        //mail(EMAIL, $subject, $message, $headers);
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

fetch_new_tweets();
sleep(30); // Close enough to every 30 seconds
fetch_new_tweets();
echo 'DONE!';