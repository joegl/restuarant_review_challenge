<html>
<head>

  <style type="text/css">
    body {
      font-family: arial, sans-serif;
      font-size: 16px;
    }
    .review {

    }
    .review h2 {
      font-size: 1.25rem;
      margin: 0;
      color: #FFF;
      padding: .5rem;
      background: #d32323;  /* yelp red */
    }
    .review h2 a {
      color: #FFF;
      padding: 0;
      margin: 0;
    }
    .review h2 a:hover {
      color: #EEE;
    }
    .review pre {
      overflow: auto;
      padding: .75rem;
      background: #f5f5f5;
      border: 1px solid #e6e6e6;
      border-radius: .25rem;
      font-size: .75rem;
    }

    .cache_info {
      font-style: italic;
      font-size: .875rem;
      color: #555;
    }

    form {
      width: 50%;
    }
    form .desc {
      display: block;
      margin: .5rem 0;
      font-size: .75rem;
    }
  
  </style>

</head>
<body>


<?php

// remove these or comment out
// error reporting overrides
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// simple debug function for displaying the reponse data
function simpledebug($debug_data) {
  echo "<pre>".var_export($debug_data, TRUE)."</pre>";
}

// set the default time zone to America/Chicago
date_default_timezone_set('America/Chicago'); 

// set the business_id for the business we want to pull reviews for
// literally just the url path for the yelp page after /biz/
// e.g., https://www.yelp.com/biz/milwaukee-ale-house-milwaukee
$business_id = 'milwaukee-ale-house-milwaukee';
if(isset($_POST['lookup_business_id'])) {
  // should probably do some parsing here to avoid any injection or malicious
  // but not doing any database work or anything.
  $business_id = $_POST['lookup_business_id'];
}

// this could actually take the business_id as a parameter and lookup 3 reviews
// and cache them for any business. All that's needed is a form to enter the
// business_id and then process it before looking it up
// add form to input a business_id
?>

<form method="POST" action="index.php">
  Lookup Business ID: <input type="text" name="lookup_business_id" value="<?php echo $business_id; ?>" size=60 />
  <input type="submit" value="Lookup" /><br />
  <span class="desc"><em>The business id can be found in the URL path for the business, after the /biz. The Milwaukee Ale House Business ID is "milwaukee-ale-house-milwaukee": https://www.yelp.com/biz/milwaukee-ale-house-milwaukee</em></span>
</form>

<?php

// require yelp api class file
require_once("yelp_api.php");

// setup file caching settings
if(!is_dir('review_data')) mkdir('review_data');
$cache_filename = 'review_data/'.$business_id.'_review-data.json';
$cache_timeout = 60 * 60; // cache timeout is 1 hour
$cache_expired = FALSE;
$cache_timestamp;

// create array to store only the relevant review data we need
$reviews = [];
// start building page output
$output = '';

// check if we're clearing the cached data
if(isset($_POST['clear_cached_data'])) {
  $cache_expired = TRUE;
}

// attempt to load the reviews from the cache
$cache_file_exists = file_exists($cache_filename);
if(!$cache_file_exists || $cache_expired) {
  // couldn't load cache file so expire the cache; or if it's already expired
  // from the force_fresh, ignore the rest
  $cache_expired = TRUE;
} else {
  // open the cache file for reading
  $cache_file = fopen($cache_filename, "r");
  // pull the reviews from the cached file and decode
  $review_file_data = fread($cache_file, filesize($cache_filename));
  $review_file_data = json_decode($review_file_data);
  // check for the cache timestamp and do comparison
  if(
    !isset($review_file_data->cache_timestamp)
    || ($review_file_data->cache_timestamp + $cache_timeout) < time()
  ) {
    // expire cache due to timeout or inability to find timeout
    $cache_expired = TRUE;
  } else {
    // set the reviews data to output from the cached files and decode it to 
    // an array instead of an object
    $reviews = json_decode(json_encode($review_file_data->reviews), TRUE);
    // set cache timestamp to last cache pull
    $cache_timestamp = $review_file_data->cache_timestamp;
  }
}

// if the cache is expired, pull new data from Yelp
if($cache_expired) {
  // construct a new yelp api object
  $yelp_api = new yelp_api();

  // make a request to the YELP API to pull the reviews for the provided business
  // ID
  // Yelp credentials are hard-coded into the API class for this project
  $response = $yelp_api->getBusinessReviews($business_id);

  // Yelp API will only ever return 3 reviews per business, and scraping Yelp 
  // for data is explicitly against their terms of service, so can't do that.
  // You can apply for Yelp Fusion VIP API access to get more than 3 reviews per
  // request but that's overboard.
  // https://github.com/Yelp/yelp-fusion/issues/466

  // check for valid response
  if(!$response) {
    // if not an object, an error was returned from the API; add the error to
    // output and kill the rest of the execution
    $output .= 'Lookup Error: ' . $yelp_api->getError();
  } else {
    // decode the JSON response to an object
    $json_response = json_decode($response);
    // loop through base "reviews" property with 3 stored reviews
    foreach($json_response->reviews as $json_review_data) {
      // store the review_id
      $review_id = $json_review_data->id;
      // combine the review_id with the business_id to get a unique id for the data
      // file; was going to cache each review into a JSON data file when I started
      // the project, but with only three reviews per business, just store one file
      // per business
      $file_id = $business_id .'_'. $review_id;
      // we can pull 4 out of 5 data points needed for the challenge straight from
      // the review data, however, the user's location will have to come from a 
      // request to their profile for more information
      // after further research, there is no endpoint to pull user data and no more
      // user data is exposed via the API than what I already get from the review 
      // data. So as of now there's no way to pull the user's location via the API.
      // it looks like there's an RSS feed for each user you can pull up via their
      // user id, which has a feed of recent reviews and geolocation data for the
      // review -- but is this the location they want? Do they want the user's 
      // location as in where they're from, or the location as in where they were
      // at when they left the review?
      // https://www.yelp.com/syndicate/user/<user_id>/rss.xml
      // I tried pulling via the URL and file_get_contents but it's kind of a PITA
      // to parse, and in the end I still don't know if this is the right data, so
      // not going to spend anymore time on it.
      // I tried pulling via the URL and file_get_contents but it's kind of a PITA
      // to parse, and in the end I still don't know if this is the right data, so
      // not going to spend anymore time on it. Might return to it if I have more
      // time.
      // $user_feed = file_get_contents('https://www.yelp.com/syndicate/user/'.$json_review_data->user->id.'/rss.xml');
      // $user_geolocation = FALSE;
      // if($user_feed) {
      //   // parse as xml for easy manipulation
      //   $xml_feed = new SimpleXmlElement($user_feed);
      //   if(isset($xml_feed->channel->item)) {
      //     foreach($xml_feed->channel->item as $feed_review) {
      //       if(strpos(current($feed_review->link), 'hrid='.$review_id)) {
      //         // found a matching review; store the geolocation coordinates and 
      //         // break the loop
      //         break;
      //       }
      //     }
      //   }
      // }
      
      // push into the reviews array using the file_id as the key
      $reviews[$file_id] = [
        'review_id' => $review_id,
        'review_url' => isset($json_review_data->url) ? $json_review_data->url : '',
        'author' => isset($json_review_data->user->name) ? $json_review_data->user->name : '',
        'author_image_url' => isset($json_review_data->user->image_url) ? $json_review_data->user->image_url : '',
        'rating' => isset($json_review_data->rating) ? $json_review_data->rating : '',
        'review_text' => isset($json_review_data->text) ? $json_review_data->text : '',
      ];

      // since we only ever are going to pull the same 3 reviews, just do a simple
      // file cache of all the data for the specific business with a timestamp.
      // first setup the data and insert the timestamp
      $cache_timestamp = time();
      $review_file_data = json_encode([
        'cache_timestamp' => $cache_timestamp,
        'reviews' => $reviews,
      ]);
      $cache_file = fopen($cache_filename, "w");
      fwrite($cache_file, $review_file_data);
      fclose($cache_file);
    }
  }
}


// we have all the reviews data we need and could get, so just loop through the
// reviews data we have and output it in JSON.
if(!$reviews || empty($reviews)) {
  // no review data
} else {
  foreach($reviews as $review_data) {
    // start building current review output
    $review_output = '';
    // add only data we want to output in JSON to new output array and encode it
    $review_json_output = json_encode(array_intersect_key($review_data, array_flip(array('author', 'author_image_url', 'rating', 'review_text'))));
    // add the review url in a linked h2 to output
    $review_output .= '<h2>Review ID: <a href="'.$review_data['review_url'].'" target="_blank">'.$review_data['review_id'].'</a></h2>';
    // add the json data in legible <pre> tags
    $review_output .= '<pre>'.$review_json_output.'</pre>';
    // add the current review output to the main output and wrap it in a container
    $output .= '<div class="review">'.$review_output.'</div>';
  }
}

// add cache data to bottom
if(isset($cache_timestamp)) {
  // add clear cached data button; duplicate the lookup_business_id field as a
  // hidden filed here with same name to preserve entered value
  $cache_refresh_form = '<form method="POST" action="index.php"><input type="hidden" name="clear_cached_data" value="yesplease" /><input type="submit" value="Clear Cached Data" /><input type="hidden" name="lookup_business_id" value="'.$business_id.'" /></form>';
  // add cache info and clear cached data button to output
  $output .= '<div class="cache_info">Data for "'. $business_id .'" last pulled and cached on '. date('F j, Y \a\t g:ia', $cache_timestamp) .' (' . date_default_timezone_get() .'). '.$cache_refresh_form;
}

// echo the output
echo $output;

?>

</body>
</html>