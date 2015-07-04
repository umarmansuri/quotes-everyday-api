<?php

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

?>
