# Slim Framework CRUD

## Install

1. PHP + Mysql (With Mysqlnd extension)
2. Composer: https://getcomposer.org/doc/00-intro.md#globally

## Download Slim

1. Using Composer
```
composer require slim/slim
```

Make sure to run Slim Framework download command inside `/slim-crud` directory. It will create a new folder called `/vendor`. Please see below section.

## Folder structure

```
/slim-crud
  /include
    - Config.php
    - DbConnect.php
    - DbHandler.php
    - PassHash.php
    - Utils.php
  /v1
    - .htaccess
    - index.php
  /vendor
    - [Slim Framework library dir]
```

## Pretty Urls

Instead of accessing your index.php by 'ugly' URL like `localhost/slim-crud/v1/index.php`, you might wanna have a prettier one without the index.php at the end of your URL. This might take a few steps involving server config (I'm using apache2). Please refer to this [post](http://www.aimanbaharum.com/2015/03/13/vps-cloud-hosting-with-digital-ocean/) under **Setting up the server** and **Virtual host** section for server configuration.

This step allows you to access this project via custom virtual host.

1. Inside `/v1`, create a file called `.htaccess`
2. Write the following code in it:

```
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^(.*)$ %{ENV:BASE}index.php [QSA,L]
```

## Database setup

1. Create a database called... anything you like. Let's say **crud-db**
2. Run this query to create the table structures: http://pastebin.com/raw.php?i=1QZuxXr3

## Project config classes

- `Config.php` - global variables defined here
- `DbConnect.php` - database connection config
- `DbHandler.php` - database business logic
- `PassHash.php` - generates password hash for login feature
- `Utils.php` - custom utility class

#### 1. Config.php

```language-php
/** Database config */
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', 'localhost');
define('DB_NAME', 'crud-db');

define('USER_CREATED_SUCCESSFULLY', 0);
define('USER_CREATE_FAILED', 1);
define('USER_ALREADY_EXISTED', 2);

/** Debug modes */
define('PHP_DEBUG_MODE', true);
define('SLIM_DEBUG', true);
```

#### 2. DbConnect.php

```language-php
class DbConnect {

    private $conn;

    function __construct(){ }

    function connect(){
      include_once dirname(__FILE__) . '/Config.php';
      $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
      if (mysqli_connect_errno()) {
        echo "Failed to connect to Mysql: " . mysqli_connect_error();
      }
      return $this->conn;
    }
}
```

#### 3. DbHandler.php

_Only showing function names for brevity._

Full code: https://gist.github.com/aimanbaharum/07c83d49413233af0125

```language-php
class DbHandler {

    private $conn;

    function __construct() { ... }
    public function createUser($name, $email, $password) { ... }
    public function checkLogin($email, $password) { ... }
    private function isUserExists($email) { ... }
    public function getUserByEmail($email) { ... }
    public function getApiKeyById($user_id) { ... }
    public function getUserId($api_key) { ... }
    public function isValidApiKey($api_key) { ... }
    private function generateApiKey() { ... }
    public function createTask($user_id, $task) { ... }
    public function getTask($task_id, $user_id) { ... }
    public function getAllUserTasks($user_id) { ... }
    public function updateTask($user_id, $task_id, $task, $status) { ... }
    public function deleteTask($user_id, $task_id) { ... }
    public function createUserTask($user_id, $task_id) { ... }

}
```

#### 4. PassHash.php

```language-php
class PassHash {

  // blowfish
  private static $algo = '$2a';
  // cost parameter
  private static $cost = '$10';

  // internal use
  public static function unique_salt() {
    return substr(sha1(mt_rand()),0,22);
  }

  // generate hash
  public static function hash($password) {
    return crypt($password, self::$algo . self::$cost . '$' . self::unique_salt());
  }

  // compare password and hash
  public static function check_password($hash, $password) {
    $full_salt = substr($hash, 0, 29);
    $new_hash = crypt($password, $full_salt);
    return ($hash == $new_hash);
  }
}
```

#### 5. Utils.php

_Showing function names for brevity._

Full code: https://gist.github.com/aimanbaharum/3e5d9a81fc7253d8e03c

```language-php
require '../vendor/autoload.php';
require_once 'Config.php';

// (Optional) Set PHP_DEBUG_MODE to true in Config.php to display PHP error
if(PHP_DEBUG_MODE){
  error_reporting(-1);
  ini_set('display_errors', 'On');
}

// Used to recognize user session by authorization header
// Global variable
$user_id = NULL;

/**
* Verifying required params posted or not
*/
function verifyRequiredParams($required_fields) { ... }

/**
* Validating email address
*/
function validateEmail($email){ ... }

/**
* Echo json response
* @param String $status_code http response code
* @param Int $response Json response
*/
function echoResponse($status_code, $response) { ... }

/**
* Adding Middle Layer to authenticate every request
* Checking if the request has valid api key in the 'Authorization' header
* returns true if $api_key in Authorization Header is correct
*/
function authenticate(\Slim\Route $route) { ... }

/**
* (Optional) Debugging utility
*/
function p($input, $exit=1) { ... }
function j($input, $encode=true, $exit=1) { ... }
```

## Restful API routes

<table border='1'>
<thead>
<tr>
<th>Description</th>
<th>Route</th>
<th>Method</th>
<th>Params</th>
<th>Authorization Header</th>
</tr>
</thead>
<tbody>
<tr>
<td>Register a user</td>
<td>/register</td>
<td>POST</td>
<td>name, email, password</td>
<td>No</td>
</tr>
<tr>
<td>Login</td>
<td>/login</td>
<td>POST</td>
<td>email, password</td>
<td>No</td>
</tr>
<tr>
<td>Creating a new task</td>
<td>/tasks</td>
<td>POST</td>
<td>task</td>
<td>Yes</td>
</tr>
<tr>
<td>Listing all tasks of authorized user</td>
<td>/tasks</td>
<td>GET</td>
<td></td>
<td>Yes</td>
</tr>
<tr>
<td>Listing single task of authorized user</td>
<td>/tasks/:task_id</td>
<td>GET</td>
<td></td>
<td>Yes</td>
</tr>
<tr>
<td>Updating a task</td>
<td>/tasks/:task_id</td>
<td>PUT</td>
<td>task, status</td>
<td>Yes</td>
</tr>
<tr>
<td>Deleting a task</td>
<td>/tasks/:task_id</td>
<td>DELETE</td>
<td></td>
<td>Yes</td>
</tr>
</tbody>
</table>

## Using Slim

Showing functions for brevity. Description of lines are written in the comment.

Full code: https://gist.github.com/aimanbaharum/72846c011062974b10cf

```language-php
// Include config files
require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require_once '../include/Utils.php';
require '../vendor/autoload.php';

// Instantiate Slim object
$app = new \Slim\Slim();

// (Optional) Check if debug is true, show Slim debug report
if(SLIM_DEBUG){$app->config('debug',true);}

// Route test START
$app->get('/', function () {
    echo "Hello World";
});
$app->get('/test/:name', function ($name) {
    echo "Hello, $name";
});
// Route test ENDS

// Project routes START
$app->post('/register', function() use ($app) { ... );
$app->post('/login', function() use ($app) { ... );
$app->post('/tasks', 'authenticate', function() use ($app){ ... );
$app->get('/tasks', 'authenticate', function(){ ... );
$app->get('/tasks/:task_id', 'authenticate', function($task_id){ ... );
$app->put('/tasks/:task_id', 'authenticate', function($task_id) use($app) { ... );
$app->delete('/tasks/:task_id', 'authenticate', function($task_id) use($app) { ... );
// Project routes END

// Run Slim
$app->run();
```
### Basic workflow of routes

- Verify required parameters using `verifyRequiredParams()` function
- Validate email if necessary
- Consume **DbHandler** function for database queries
- Using **'authenticate'** in each routes that needs API key authorization
  - Each user have their own unique API key for authentication
  - Authenticated User ID is stored in the `$user_id` global var to be passed for db query
  - More information on [Middle ware](http://docs.slimframework.com/routing/middleware/)
- Prints out appropriate JSON response using `echoResponse()` function

## Testing routes

1. Use **REST Easy** addon for Firefox to test these API routes
2. Enter the URL of one of the routes, example `http://localhost/slim-crud/v1/` with **GET** method
3. Or you can use curl for testing

> GET: http://localhost/slim-crud/v1/

**Returns:**
```
Hello World
```

> GET: http://localhost/slim-crud/v1/test/najib

`najib` is a parameter set in the route (:name). The parameter is passed to be consumed by /test/:name route function.

**Returns:**
```
Hello, najib
```

### Create

Let's try to register a user.

> POST: http://localhost/slim-crud/v1/register
>
> Params:  
>   - name: rosmah  
>   - email: cincin.juta99@gmail.com  
>   - password: lovenajib123

**Returns:**
```language-json
{"error":false,"message":"You are successfully registered"}
```

Hooray! Let's create a task.

> POST: http://localhost/slim-crud/v1/tasks
>
> Params:  
>   - task: beli jet untuk abang jib  
>
> Header:  
>   - Authorization: user api key here. take from database

**Returns:**
```language-json
{"error":false,"message":"Task created successfully","task_id":4}
```

### Read

Now, would you like to see all of tasks of one particular user? Firstly, You need to acquire the user's API key (just access your database and take it from there). In practice, you would want to acquire the API key from login, and add it to HTTP Header using `setHeader()` in PHP.

> GET: http://localhost/slim-crud/v1/tasks
>
> Header:  
>   - Authorization: user api key here. check in database

**Returns:**
```language-json
{"error":false,"id":4,"task":"beli jet untuk abg jib","status":0,"createdAt":"2015-07-05 08:53:06"}
```

To get one particular task of an authorized user, simply pass a task id in the URL.

> GET: http://localhost/slim-crud/v1/tasks/4
>
> Header:  
>   - Authorization: user api key here. check in database

**Returns:**
```language-json
{"error":false,"id":4,"task":"beli jet untuk abg jib","status":0,"createdAt":"2015-07-05 08:53:06"}
```

### Update

> PUT: http://localhost/slim-crud/v1/tasks/4
>
> Params:  
>   - task: beli jet untuk abang jib tersayang
>   - status: 1
>
> Header:  
>   - Authorization: user api key here. take from database

**Returns:**
```language-json
{"error":false,"message":"Task updated successfully"}
```

### Delete

Simply pass a task id to delete route to remove the task from database.

> DELETE: http://localhost/slim-crud/v1/tasks/4
>
> Header:  
>   - Authorization: user api key here. take from database

**Returns:**
```language-json
{"error":false,"message":"Task deleted succesfully"}
```

---

## Conclusion

For best practice, define your routes according to what it does. For example, delete route should be `tasks/remove/:id`. This will make it easier for you to know which route does what.

> **Protip:**  
> Use `p()` or `j()` function in Utils to debug if any unseen error occurred. This function is so handy that if you use it right, could tell you which line of your code has error. Basic usage: `p(1)` will print 1 at that line and die.

#### Issues I've encountered

1. 404 error page - might caused by non-existant or disabled **mysqlnd** extension in apache2 config. Enable it or install if you haven't already. [Fix](http://stackoverflow.com/questions/23158943/install-both-mysql-and-mysqlnd-on-ubuntu-12-04)
2. Check if include and include_once directory is correct

#### Source

- http://www.aimanbaharum.com/2015/03/13/vps-cloud-hosting-with-digital-ocean/
- http://www.androidhive.info/2014/01/how-to-create-rest-api-for-android-app-using-php-slim-and-mysql-day-23/
