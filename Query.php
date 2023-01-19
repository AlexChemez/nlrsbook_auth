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
      $today = strtotime(date("Y-m-d H:i:s"));
      $expireDate = $moduleinstance->exp;
      if ($today > $expireDate) {  
        $getToken = self::checkToken($user_id, $signature);
        $row->id = $moduleinstance->id;
        $row->user_id = $user_id;
        $row->token = $getToken;
        $row->exp = self::jwt_decode($getToken)['exp'];
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
      $row->exp = self::jwt_decode($getToken)['exp'];
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
          'header'  => sprintf("X-signature: %s", $signature),
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

  public static function getSub($user_id) {
    global $DB;
    $moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $user_id), '*', IGNORE_MISSING ); 
    return $moduleinstance->sub;
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
          'header'  => sprintf("X-signature: %s", $signature),
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


  public static function generateServerApiRequestSignature($payload, $secret)
  {    
    $dataToSign = implode(chr(0x0A), [
        md5(json_encode($payload)),
    ]);

    $calculatedHmacSignature = hash_hmac('sha256', $dataToSign, $secret);
    return $calculatedHmacSignature;
  }

  public static function generateServerApiRequestSignatureBase64($payload, $secret)
  {    
    $dataToSign = implode(chr(0x0A), [
        md5(json_encode($payload)),
    ]);

    $calculatedHmacSignature = hash_hmac('sha256', $dataToSign, $secret);
    return $calculatedHmacSignature;
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

  public static function getBook($nlrsbook_id, $token) 
  {
      $query = '{ 
          book(id: '.$nlrsbook_id.') {
            id
            coverThumbImage {
              url
              width
              height
            }
            title
            authors {
              id
              fullName
            }
            annotation
            shortBibl
            innerPagesCount
            pubPlace
            publisher
            pubDate
            is_on_shelf
            isOnShelf
        }
      }';

      $data = array ('query' => $query);
      $data = http_build_query($data);

      $options = array(
        'http' => array(
          'header' => sprintf("Authorization: Bearer %s", $token),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
      $json = json_decode($getContents, true);
      if ($getContents === FALSE) { /* Handle error */ }
      return $json['data']['book'];
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
