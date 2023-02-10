<?php
namespace App\Querys;

class Query
{
  const HOST = 'https://e.nlrs.ru/graphql';

// Проверка и получение токена пользователя 
  public static function getToken() {
    global $DB, $USER;
    $moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $USER->id), '*', IGNORE_MISSING );
    if ($moduleinstance->token) {  
      $today = strtotime(date("Y-m-d H:i:s"));
      $expireDate = $moduleinstance->exp;
      if ($today > $expireDate) {  
        $getToken = self::checkToken($USER->id, self::generateServerApiRequestSignature());
        $row->id = $moduleinstance->id;
        $row->user_id = $USER->id;
        $row->token = $getToken;
        $row->exp = self::jwt_decode($getToken)['exp'];
        $row->sub = self::jwt_decode($getToken)['sub'];
        $DB->update_record('nlrsbook_auth', $row);
        return $getToken;
      } else {
        return $moduleinstance->token;
      }
    } else {
      $getToken = self::createAccount($USER->id, self::generateServerApiRequestSignature());
      $row = new \stdClass();
      $row->user_id = $USER->id;
      $row->token = $getToken;
      $row->exp = self::jwt_decode($getToken)['exp'];
      $row->sub = self::jwt_decode($getToken)['sub'];
      $DB->insert_record('nlrsbook_auth', $row);
      return $getToken;
    }
  }

  // Проверка токена
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

  // Создание аккаунта
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

  public static function generateServerApiRequestSignature()
  {    
    global $USER;

    $payload = [
      "orgId" => 1,
      "userIdInEduPlatform" => $USER->id,
    ];
    $secret = get_config('nlrsbook_auth', 'org_private_key'); // Секретный ключ организации
    $dataToSign = implode(chr(0x0A), [
        md5(json_encode($payload)),
    ]);

    $calculatedHmacSignature = hash_hmac('sha256', $dataToSign, $secret);
    return $calculatedHmacSignature;
  }

  protected static function jwt_decode($token){
      $jwt = array_combine(['header', 'payload', 'signature'], explode('.', $token));
      $base64_decode = base64_decode($jwt['payload']);
      $json = json_decode($base64_decode, true);
      return $json;
  }

  // Создание подписи
  public static function getSignature()
  {    
    global $USER;
    $sub = self::getSub($USER->id);
    $payload = [
      "orgId" => 1,
      "userIdInEduPlatform" => "${sub}",
    ];
    $secret = get_config('nlrsbook_auth', 'org_private_key'); // Секретный ключ организации
    $dataToSign = implode(chr(0x0A), [
        md5(json_encode($payload)),
    ]);

    $calculatedHmacSignature = hash_hmac('sha256', $dataToSign, $secret);
    return $calculatedHmacSignature;
  }

// Получение чит. билета пользователя
  public static function getSub($user_id) {
    global $DB;
    $moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $user_id), '*', IGNORE_MISSING ); 
    return $moduleinstance->sub;
  }

// Формирование ссылки бесшовной авторизации
  public static function getUrl($redirect)
  {
    global $USER;
    $seamlessAuthOrgId = 1;
    $nlrsUserId = self::getSub($USER->id);
    $seamlessAuthSignature = self::getSignature();
    $bookUrl = "https://e.nlrs.ru/seamless-auth-redirect?seamlessAuthOrgId=${seamlessAuthOrgId}&seamlessAuthUserId=${nlrsUserId}&seamlessAuthSignature=${seamlessAuthSignature}&override_redirect=${redirect}";
    return $bookUrl;
  }

// Получнеие данных книги
  public static function getBook($nlrsbook_id) 
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
          'header' => sprintf("Authorization: Bearer %s", self::getToken()),
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

// Получнеие данных полки
  public static function getShelf($page, $first) 
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
          'header'  => sprintf("Authorization: Bearer %s", self::getToken()),
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

// Добавление книги на полку
  public static function addBookToShelf($book_id) {
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
          'header'  => sprintf("Authorization: Bearer %s", self::getToken()),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
  }

// Удаление книги с полки
  public static function removeBookToShelf($book_id) {
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
          'header'  => sprintf("Authorization: Bearer %s", self::getToken()),
          'method'  => 'POST',  
          'content' => $data
        )
      );

      $context  = stream_context_create($options);
      $getContents = file_get_contents(sprintf(self::HOST), false, $context);
  }
}
