<?php
namespace App\Querys;

class Query
{
  const HOST = 'https://e.nlrs.ru/graphql';

// Проверка и получение токена пользователя 
  public static function getToken() {
    global $DB, $USER;
    $moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $USER->id), '*', IGNORE_MISSING );
      if ($moduleinstance) {
        $today = strtotime(date("Y-m-d H:i:s"));
        $expireDate = $moduleinstance->exp;
        if ($today > $expireDate) {  
          $getToken = self::checkToken();
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
        return null;
      }
  }

  // Проверка токена
  public static function checkToken() {
      global $USER;
      $user_id = $USER->id;
      $signature = self::generateServerApiRequestSignature();
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


  // Связь с аккаунтом НБ
  public static function nlrsConnect($login, $password) {
      global $DB, $USER;
      $user_id = $USER->id;
      $signature = self::generateServerApiRequestSignature();
      $query = 'mutation {
        eduLinkExistingNlrsAccount(
          input: { 
              orgId: 1, 
              userIdInEduPlatform: "'.$user_id.'" 
              libraryCardNumberOrEmail: "'.$login.'" 
              password: "'.$password.'" 
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

      $json_token = $json['data']['eduLinkExistingNlrsAccount']['token'];
      $error = $json['errors'][0]['message'];

      $moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $user_id), '*', IGNORE_MISSING );
      if (empty($error)) {
        if (empty($moduleinstance)) {
          $row = new \stdClass();
          $row->user_id = $user_id;
          $row->token = $json_token;
          $row->exp = self::jwt_decode($json_token)['exp'];
          $row->sub = self::jwt_decode($json_token)['sub'];
          $DB->insert_record('nlrsbook_auth', $row);
        }
      }

      return $json;
  }

  // Создание аккаунта
  public static function createAccount() {
      global $DB, $USER;
      $user_id = $USER->id;
      $signature = self::generateServerApiRequestSignature();
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

      $json_token = $json['data']['eduCreateNewNlrsAccount']['token'];
      $moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $user_id), '*', IGNORE_MISSING );
      if (empty($moduleinstance)) {
        $row = new \stdClass();
        $row->user_id = $user_id;
        $row->token = $json_token;
        $row->exp = self::jwt_decode($json_token)['exp'];
        $row->sub = self::jwt_decode($json_token)['sub'];
        $DB->insert_record('nlrsbook_auth', $row);
      }
      return $json['data']['eduCreateNewNlrsAccount']['token'];
  }

  // Создание подписи организации
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

  // Расшифроква JWT
  protected static function jwt_decode($token){
      $jwt = array_combine(['header', 'payload', 'signature'], explode('.', $token));
      $base64_decode = base64_decode($jwt['payload']);
      $json = json_decode($base64_decode, true);
      return $json;
  }

  // Создание подписи пользователя
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

  // Получение идентификатора пользователя
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

  // Получение данных книги
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
