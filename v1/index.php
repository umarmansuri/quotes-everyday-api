<?php

/**
* ROUTES:
* (Authorization header with api key is required for user session)
*
* /register
*   method - post
*   params - name, email, password
*
* /login
*   method - post
*   params - email, password
*
* Creating new task in db
* /tasks
*   method - post
*   params - task
*
* Listing all tasks of authorized user
* /tasks
*   method - get
*
* Listing single task of authorized user
* /tasks/:user_id
*   method - GET
*   param - /:task_id
*
* Updating existing task
* /tasks/:task_id
*   method - PUT
*   params - task, status, /:task_id
*
* Deleting task. Users can delete only their tasks
* /tasks/:task_id
*   method - DELETE
*/

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

/**
* User registration
* url - /register
* method - POST
* params - name, email, password
*/
$app->post('/register', function() use ($app) {
  // check for required params
  verifyRequiredParams(array('name', 'email', 'password'));
  $response = array();
  // reading post params
  $name = $app->request->post('name');
  $email = $app->request->post('email');
  $password = $app->request->post('password');
  // validating email address
  validateEmail($email);

  $db = new DbHandler();
  $res = $db->createUser($name, $email, $password);

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

/**
* User Login
* url - /login
* method - POST
* params - email, password
*/
$app->post('/login', function() use ($app) {
  verifyRequiredParams(array('email', 'password'));
  // reading post params
  $email = $app->request()->post('email');
  $password = $app->request()->post('password');
  $response = array();

  $db = new DbHandler();
  // check for correct email and password
  if ($db->checkLogin($email, $password)) {
      // get the user by email
      $user = $db->getUserByEmail($email);

      if ($user != NULL) {
          $response["error"] = false;
          $response['name'] = $user['name'];
          $response['email'] = $user['email'];
          $response['apiKey'] = $user['api_key'];
          $response['createdAt'] = $user['created_at'];
      } else {
          // unknown error occurred
          $response['error'] = true;
          $response['message'] = "An error occurred. Please try again";
      }
  } else {
      // user credentials are wrong
      $response['error'] = true;
      $response['message'] = 'Login failed. Incorrect credentials';
  }

  echoResponse(200, $response);
});

/**
* Creating new task in db
* method POST
* params - name
* url - /tasks/
*/

$app->post('/tasks', 'authenticate', function() use ($app){
  verifyRequiredParams(array('task'));

  $response = array();
  $task = $app->request->post('task');

  global $user_id;
  $db = new DbHandler();

  // creating new task
  $task_id = $db->createTask($user_id, $task);
  if ($task_id != NULL) {
      $response["error"] = false;
      $response["message"] = "Task created successfully";
      $response["task_id"] = $task_id;
  } else {
      $response["error"] = true;
      $response["message"] = "Failed to create task. Please try again";
  }
  echoResponse(201, $response);
});

/**
* Listing all tasks of particular user
* method GET
* url /tasks
*/

$app->get('/tasks', 'authenticate', function(){
  global $user_id;
  $response = array();
  $db = new DbHandler();

  // fetching all user tasks
  $result = $db->getAllUserTasks($user_id);

  $response["error"] = false;
  $response["tasks"] = array();

  // looping through result and preparing tasks array
  while ($task = $result->fetch_assoc()) {
      $tmp = array();
      $tmp["id"] = $task["id"];
      $tmp["task"] = $task["task"];
      $tmp["status"] = $task["status"];
      $tmp["createdAt"] = $task["created_at"];
      array_push($response["tasks"], $tmp);
  }

  echoResponse(200, $response);
});

/**
* Listing single task of particular user
* method GET
* url /tasks/:id
* Return 404 if task doesn't belong to user
*/
$app->get('/tasks/:task_id', 'authenticate', function($task_id){
  global $user_id;
  $response = array();
  $db = new DbHandler();

  // fetch task
  $result = $db->getTask($task_id, $user_id);

  if ($result != NULL) {
      $response["error"] = false;
      $response["id"] = $result["id"];
      $response["task"] = $result["task"];
      $response["status"] = $result["status"];
      $response["createdAt"] = $result["created_at"];
      echoResponse(200, $response);
  } else {
      $response["error"] = true;
      $response["message"] = "The requested resource doesn't exists";
      echoResponse(404, $response);
  }
});

/**
* Updating existing task
* method PUT
* params task, status
* url - /tasks/:id
*/
$app->put('/tasks/:task_id', 'authenticate', function($task_id) use($app) {
  verifyRequiredParams(array('task', 'status'));

  global $user_id;
  $task = $app->request->put('task');
  $status = $app->request->put('status');

  $db = new DbHandler();
  $response = array();

  // updating task
  $result = $db->updateTask($user_id, $task_id, $task, $status);
  if ($result) {
      // task updated successfully
      $response["error"] = false;
      $response["message"] = "Task updated successfully";
  } else {
      // task failed to update
      $response["error"] = true;
      $response["message"] = "Task failed to update. Please try again!";
  }
  echoResponse(200, $response);
});


/**
 * Deleting task. Users can delete only their tasks
 * method DELETE
 * url /tasks
 */
$app->delete('/tasks/:task_id', 'authenticate', function($task_id) use($app) {
    global $user_id;

    $db = new DbHandler();
    $response = array();
    $result = $db->deleteTask($user_id, $task_id);
    if ($result) {
        // task deleted successfully
        $response["error"] = false;
        $response["message"] = "Task deleted succesfully";
    } else {
        // task failed to delete
        $response["error"] = true;
        $response["message"] = "Task failed to delete. Please try again!";
    }
    echoResponse(200, $response);
});

$app->run();
?>
