<?php

class DbHandler {

    private $conn;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    public function registerAppUser($params) {
      require_once 'PassHash.php';

      $name = $params['name'];
      $email = $params['email'];
      $gcm_registration_id = $params['gcm_registration_id'];
      $password = $params['password'];
      $notify_app_status = $params['notify_app_status'];
      $send_email_status = $params['send_email_status'];

      $response = array();
      // First check if user already existed in db
      if (!$this->isUserExists($email)) {
          // Generating password hash
          $password_hash = PassHash::hash($password);
          // Generating API key
          $api_key = $this->generateApiKey();
          // insert query
          $stmt = $this->conn->prepare("INSERT INTO app_users (name, email, password_hash, api_key, app_notify_status, send_email_status, gcm_registration_id, edited_at) values(?, ?, ?, ?, ?, ?, ?, date('Y-m-d H:i:s'))");
          $stmt->bind_param("ssssiis", $name, $email, $password_hash, $api_key, $notify_app_status, $send_email_status, $gcm_registration_id);
          $result = $stmt->execute();
          $stmt->close();
          // Check for successful insertion
          if ($result) {
              return USER_CREATED_SUCCESSFULLY;
          } else {
              return USER_CREATE_FAILED;
          }
      } else {
          return USER_ALREADY_EXISTED;
      }
      return $response;
    }

    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $password) {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT password_hash FROM app_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password
            $stmt->fetch();
            $stmt->close();
            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();
            // user not existed with the email
            return FALSE;
        }
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT id FROM app_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key, created_at FROM app_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM app_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $api_key = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM app_users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM app_users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    public function createQuote($user_id, $params) {
        $quote = $params['quote'];
        $quote_type = $params['quote_type'];
        $stmt = $this->conn->prepare("INSERT INTO quotes(quote, quote_type) VALUES(?, ?)");
        $stmt->bind_param("ss", $quote, $quote_type);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // task row created
            // now assign the task to user
            $new_quote_id = $this->conn->insert_id;
            $res = $this->createUserQuotes($user_id, $new_quote_id);
            if ($res) {
                // task created successfully
                return $new_quote_id;
            } else {
                // task failed to create
                return NULL;
            }
        } else {
            // task failed to create
            return NULL;
        }
    }

    public function createUserQuotes($user_id, $quote_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_quotes(user_id, quote_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $quote_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getAllQuotesByUser($user_id) {
        $stmt = $this->conn->prepare("SELECT t.* FROM quotes t, user_quotes ut WHERE t.id = ut.quote_id AND ut.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $quotes = $stmt->get_result();
        $stmt->close();
        return $quotes;
    }

    public function editQuote($user_id, $params) {
        $quote = $params['quote'];
        $quote_type = $params['quote_type'];
        $quote_id = $params['quote_id'];
        $stmt = $this->conn->prepare("UPDATE quotes t, user_quotes ut SET t.quote = ?, t.quote_type = ?, t.edited_at = date('Y-m-d H:i:s') WHERE t.id = ? AND t.id = ut.quote_id AND ut.user_id = ?");
        $stmt->bind_param("siii", $quote, $quote_type, $quote_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    public function deleteQuote($user_id, $quote_id) {
        $stmt = $this->conn->prepare("DELETE t FROM quotes t, user_quotes ut WHERE t.id = ? AND ut.quote_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $quote_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }
}
?>
