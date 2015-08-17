<?php
require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require_once '../include/Utils.php';
require '../vendor/autoload.php';

$app = new \Slim\Slim();

if(SLIM_DEBUG){
  $app->config('debug',true);
}

/**
* route test block
*/
$app->get('/', function () {
    echo "Hello World";
});
$app->get('/test/:name', function ($name) {
    echo "Hello, $name";
});

//////////////////////////////////////////
// Register User
//////////////////////////////////////////
$app->post('/register-user', function() use ($app) {
  // check for required params
  verifyRequiredParams(array('name', 'email', 'gcm_registration_id'));
  $response = array();
  $params = array();
  $params['notify_app_status'] = 1;
  $params['send_email_status'] = 0;
  $params['name'] = $app->request->post('name');
  $params['email'] = $app->request->post('email');
  $params['gcm_registration_id'] = ($app->request->post('gcm_registration_id'))?$app->request->post('gcm_registration_id'):'';
  $params['password'] = ($app->request->post('password'))?$app->request->post('password'):'123';

  validateEmail($params['email']);

  $db = new DbHandler();
  $res = $db->registerAppUser($params);

  if ($res == USER_CREATED_SUCCESSFULLY) {
      $response["error"] = false;
      $response["message"] = "You are successfully registered";
      echoResponse(201, $response);
  } else if ($res == USER_CREATE_FAILED) {
      $response["error"] = true;
      $response["message"] = "Oops! An error occurred while registereing";
      echoResponse(200, $response);
  } else if ($res == USER_ALREADY_EXISTED) {
      $response["error"] = true;
      $response["message"] = "Sorry, this email already existed";
      echoResponse(200, $response);
  }

});

////////////////////////////////////////
// Login
////////////////////////////////////////
$app->post('/login', function() use ($app) {
  verifyRequiredParams(array('email', 'password'));
  $email = $app->request()->post('email');
  $password = $app->request()->post('password');
  $response = array();

  $db = new DbHandler();
  if ($db->checkLogin($email, $password)) {
      $user = $db->getUserByEmail($email);
      if ($user != NULL) {
          $response["error"] = false;
          $response['name'] = $user['name'];
          $response['email'] = $user['email'];
          $response['apiKey'] = $user['api_key'];
          $response['createdAt'] = $user['created_at'];
      } else {
          $response['error'] = true;
          $response['message'] = "An error occurred. Please try again";
      }
  } else {
      $response['error'] = true;
      $response['message'] = 'Login failed. Incorrect credentials';
  }
  echoResponse(200, $response);
});

//////////////////////////////////////////////
// Create Quote
/////////////////////////////////////////////
$app->post('/create-quote', 'authenticate', function() use ($app){
  verifyRequiredParams(array('quote', 'quote_type'));

  $response = array();
  $params = array();
  $params['quote'] = $app->request->post('quote');
  // default to Motivational (1)
  $params['quote_type'] = ($app->request->post('quote_type'))?$app->request->post('quote_type'):'1';

  global $user_id;
  $db = new DbHandler();

  // creating new task
  $quote_id = $db->createQuote($user_id, $params);
  if ($quote_id != NULL) {
      $response["error"] = false;
      $response["message"] = "Task created successfully";
      $response["task_id"] = $quote_id;
  } else {
      $response["error"] = true;
      $response["message"] = "Failed to create task. Please try again";
  }
  echoResponse(201, $response);
});

//////////////////////////////////////////////////
// Get a quote and push to all users
// TODO parameter to specific userid
//////////////////////////////////////////////////
$app->get('/push-quote', function() use ($app){
  $response = array();
  $db = new DbHandler();

  $response["error"] = false;
  $response["quotes"] = array();
  $response["users"] = array();

  // fetching all user tasks
  $result = $db->getQuote();
  $result_gcm = $db->getUserGcmRegId();

  while ($gcm_id = $result_gcm->fetch_assoc()) {
      $tmp = array();
      $tmp["gcm_id"] = $gcm_id["gcm_registration_id"];
      array_push($response["users"], $tmp);
  }

  // looping through result and preparing tasks array
  while ($quote = $result->fetch_assoc()) {
      $tmp = array();
      $tmp["quote"] = html_entity_decode($quote["quote"],ENT_QUOTES);
      $tmp["author"] = $quote["author"];
      array_push($response["quotes"], $tmp);
  }

  $push_msg = $response['quotes'][0]['quote'];
  $registration_ids = array();
  foreach($response['users'] as $user_gcm){
    array_push($registration_ids, $user_gcm['gcm_id']);
  }

  /**
   * Push to GCM
   */
  $url = 'https://android.googleapis.com/gcm/send';

  $message = array(
    "Notice" => $push_msg,
    "notification_id" => "42",
  );

  $fields = array(
      'registration_ids' => $registration_ids,
      'data' => $message,
  );

  $headers = array(
       // Insert your GCM auth key here
      'Authorization: key=AIzaSyCyjWI6irXrD0G4E1xYHSfgfRVWrnbk6HU',
      'Content-Type: application/json'
  );

  // Open connection
  $ch = curl_init();
  // Set the url, number of POST vars, POST data
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // Disabling SSL Certificate support temporarly
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
  // Execute post
  $result = curl_exec($ch);
  if ($result === FALSE) {
      die('Curl failed: ' . curl_error($ch));
  }
  // Close connection
  curl_close($ch);

  echoResponse(200, $response);
});

//////////////////////////////////////////////////
// Get all current user quotes
// Authenticated user
//////////////////////////////////////////////////
$app->get('/quotes/:user_id', function($user_id) use ($app){
  $response = array();
  $db = new DbHandler();

  // fetching all user tasks
  $result = $db->getAllQuotesByUser($user_id);

  $response["error"] = false;
  $response["quotes"] = array();

  // looping through result and preparing tasks array
  while ($quote = $result->fetch_assoc()) {
      $tmp = array();
      $tmp["id"] = $quote["id"];
      $tmp["quote"] = $quote["quote"];
      $tmp["createdAt"] = $quote["created_at"];
      array_push($response["quotes"], $tmp);
  }

  echoResponse(200, $response);
});

/////////////////////////////////////////////////////
// Edit quote
// Authenticated user
//////////////////////////////////////////////////////
$app->put('/quote/edit/:quote_id', 'authenticate', function($quote_id) use($app) {
  verifyRequiredParams(array('quote', 'quote_type'));

  global $user_id;
  $params = array();
  $params['quote_id'] = $quote_id;
  $params['quote'] = $app->request->put('quote');
  $params['quote_type'] = ($app->request->put('quote_type'))?$app->request->put('quote_type'):'1';

  $db = new DbHandler();
  $response = array();

  // updating task
  $result = $db->editQuote($user_id, $params);
  if ($result) {
      // task updated successfully
      $response["error"] = false;
      $response["message"] = "Quote updated successfully";
  } else {
      // task failed to update
      $response["error"] = true;
      $response["message"] = "Quote failed to update. Please try again!";
  }
  echoResponse(200, $response);
});

/////////////////////////////////////////////////
// Delete quote
// Authenticated user
///////////////////////////////////////////////////
$app->delete('/quote/delete/:quote_id', 'authenticate', function($quote_id) use($app) {
    global $user_id;
    $db = new DbHandler2();
    $response = array();
    $result = $db->deleteQuote($user_id, $quote_id);
    if ($result) {
        $response["error"] = false;
        $response["message"] = "Task deleted succesfully";
    } else {
        $response["error"] = true;
        $response["message"] = "Task failed to delete. Please try again!";
    }
    echoResponse(200, $response);
});

$app->run();
?>
