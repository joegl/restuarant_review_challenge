<?php

/*
  YELP API Class
  
  Basic class for making the necessary requests to the YELP API to complete the 
  challenge.

*/


class yelp_api {

  // API Authentication Credentials
  // Hard coding credentials for this project; normally would add as parameters
  // to the constructor.
  protected $api_client_id  =   'XXXXXXXXXXXX';  
  protected $api_key        =   'XXXXXXXXXXXX';
  protected $api_access_token;

  // API request information
  protected $api_base_url = 'https://api.yelp.com/';
  protected $api_endpoint;
  protected $api_params = [];
  protected $error;

  /*
   * Returns $this->error.
   */
  public function getError() {
    return $this->error;
  }


  /** 
   * Makes a request to the Yelp API with the provided request parameters and 
   * returns the response.
   *
   * Authentication and call lifted from provided YELP example: 
   * https://github.com/Yelp/yelp-fusion/blob/master/fusion/php/sample.php     
   * 
   * @return   The JSON response from the request
   */
  public function sendRequest() {
    // Send Yelp API Call
    // run in a try/catch block to throw exceptions for failed requests.
    try {
        // initalize a curl request object
        $curl = curl_init();
        if (FALSE === $curl)
            throw new Exception('Failed to initialize');

        // create the request url
        $url = $this->api_base_url . $this->api_endpoint . "?" . http_build_query($this->api_params);

        // setup the curl request
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,  // Capture response.
            CURLOPT_ENCODING => "",  // Accept gzip/deflate/whatever.
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer " . $this->api_key,
                "cache-control: no-cache",
            ),
        ));

        // execute the curl request and store the response
        $response = curl_exec($curl);

        // throw exceptions for bad responses
        if (FALSE === $response)
            throw new Exception(curl_error($curl), curl_errno($curl));
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (200 != $http_status)
            throw new Exception($response, $http_status);

        // close the curl connection
        curl_close($curl);
    } catch(Exception $e) {
      // don't trigger error but pass it as response
       // trigger_error(sprintf(
            // 'Curl failed with error #%d: %s',
            // $e->getCode(), $e->getMessage()),
            // E_USER_ERROR);
        $this->error = $e->getMessage();
        return FALSE;
    }

    // return the response
    return $response;
  }



  /**
   * API REQUEST: v3/business_reviews
   * https://www.yelp.com/developers/documentation/v3/business_reviews
   *
   * This endpoint returns up to three review excerpts for a given business 
   * ordered by Yelp's default sort order.
   *
   * Note: at this time, the API does not return businesses without any reviews.
   * 
   * To use this endpoint, make the GET request to the following URL with the ID 
   * of the business you want to get reviews for. Normally, you'll get the 
   * Business ID from /businesses/search, /businesses/search/phone, 
   * /transactions/{transaction_type}/search or /autocomplete.
   *
   */
  public function getBusinessReviews($business_id) {
    // set the api endpoint
    $this->api_endpoint = 'v3/businesses/'.$business_id.'/reviews';
    // configure and set any request parameters; don't believe this request has
    // any
    // return the response from the sendRequest method
    return $this->sendRequest();
  }

}



?>