<?php
namespace App\Querys;

require_once(__DIR__ . "/vendor/autoload.php");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Query
{
  const HOST = 'https://e.nlrs.ru/graphql';

  public static function getToken($user_id, $signature) {
    global $DB;
    $moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $user_id), '*', IGNORE_MISSING );
    if ($moduleinstance->token) {  
      $today = date("Y-m-d H:i:s");
      $date = $moduleinstance->exp;
      if ($today < $date) {  
        $getToken = self::checkToken($user_id, $signature);
        $row->id = $moduleinstance->id;
        $row->user_id = $user_id;
        $row->token = $getToken;
        $row->datetime = date("Y-m-d H:i:s", substr(self::jwt_decode($getToken)['exp'], 0, 10));
        $row->sub = self::jwt_decode($getToken)['sub'];
        $DB->update_record('nlrsbook_auth', $row);
        return $getToken;
      } else {
        return $moduleinstance->token;
      }
    } else {
      $getToken = self::createAccount($user_id, $signature);
      $row = new \stdClass();
      $row->user_id = $user_id;
      $row->token = $getToken;
      $row->datetime = date("Y-m-d H:i:s", substr(self::jwt_decode($getToken)['exp'], 0, 10));
      $row->sub = self::jwt_decode($getToken)['sub'];
      $DB->insert_record('nlrsbook_auth', $row);
      return $getToken;
    }
  }

  public static function checkToken($user_id, $signature) {
      $query = 'mutation {
        eduCheckIfLinkedNlrsAccountExistsAndGetToken(
          input: { 
              orgId: 1, 
              userIdInEduPlatform: "'.$user_id.'" 
          }
        ) {
          token
        }
      }';

      $data = array ('query' => $query);
      $data = http_build_query($data);

      $options = array(
        'http' => array(
          'header'  => sprintf("Authorization: Bearer %s", $signature),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
      $json = json_decode($getContents, true);
      if ($getContents === FALSE) { }
      return $json['data']['eduCheckIfLinkedNlrsAccountExistsAndGetToken']['token'];
  }

  public static function createAccount($user_id, $signature) {
      $query = 'mutation {
        eduCreateNewNlrsAccount(
          input: {
            orgId: 1
            userIdInEduPlatform: "'.$user_id.'"
          }
        ) {
          token
        }
      }';

      $data = array ('query' => $query);
      $data = http_build_query($data);

      $options = array(
        'http' => array(
          'header'  => sprintf("Authorization: Bearer %s", $signature),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
      $json = json_decode($getContents, true);
      if ($getContents === FALSE) { }
      return $json['data']['eduCreateNewNlrsAccount']['token'];
  }

  public static function generateServerApiRequestSignatureBase64($privateKey, $org_id, $user_id)
  {
    $payload = [
        'orgId' => $org_id, 
        'userIdInEduPlatform' => "${user_id}"
    ];
    $jwt = JWT::encode($payload, $privateKey, 'RS256');
    $encode = self::base64url_encode($jwt);
    return $encode;
  }

  protected static function base64url_encode( $data ){
      return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  protected static function jwt_decode($token){
      $jwt = array_combine(['header', 'payload', 'signature'], explode('.', $token));
      $base64_decode = base64_decode($jwt['payload']);
      $json = json_decode($base64_decode, true);
      return $json;
  }

  public static function getShelf($page, $first, $token) 
  {
      $query = 'query { 
          books(
              first: '.$first.'
              page: '.$page.'
              prefiltering: { onlyFromShelf: true }
            ) {
              paginatorInfo {
                total
                currentPage
                hasMorePages
                perPage
              }
              data {
                id
                title
                cover_thumb_url
              }
            }
        }';

      $data = array ('query' => $query);
      $data = http_build_query($data);

      $options = array(
        'http' => array(
          'header'  => sprintf("Authorization: Bearer %s", $token),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
      $json = json_decode($getContents, true);
      if ($getContents === FALSE) { }
      return $json['data']['books'];
  }

  public static function addBookToShelf($book_id, $token) {
      $query = 'mutation {
        addBookToShelf(bookId: '.$book_id.') {
          title
          is_on_shelf
        }
      }';

      $data = array ('query' => $query);
      $data = http_build_query($data);

      $options = array(
        'http' => array(
          'header'  => sprintf("Authorization: Bearer %s", $token),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
  }

  public static function removeBookToShelf($book_id, $token) {
      $query = 'mutation {
        removeBookFromShelf(bookId: '.$book_id.') {
          title
          is_on_shelf
        }
      }';

      $data = array ('query' => $query);
      $data = http_build_query($data);

      $options = array(
        'http' => array(
          'header'  => sprintf("Authorization: Bearer %s", $token),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
  }
}