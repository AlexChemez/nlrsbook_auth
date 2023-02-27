<?php
require(__DIR__ . '/../../config.php');

require_once($CFG->dirroot . "/blocks/nlrsbook_auth/Query.php");

require_login();

global $DB,$USER;

use App\Querys\Query;

$PAGE->set_title('Авторизация');
$PAGE->set_heading('Авторизация');
$PAGE->set_pagelayout('standard');

$login = optional_param('login', '', PARAM_TEXT );
$pass = optional_param('password', '', PARAM_TEXT );
$newaccount = optional_param('newaccount', 0, PARAM_BOOL );

$moduleinstance = $DB->get_record('nlrsbook_auth', array('user_id' => $USER->id), '*', IGNORE_MISSING );
if ($moduleinstance) {
    redirect('/index.php', 'Вы уже авторизовались.', null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($login || $pass) {
    $connect = Query::nlrsConnect($login,$pass);
    $error = $connect['errors'][0]['message'];
    $data = $connect['data']['eduLinkExistingNlrsAccount']['token'];

    if ($error) {
        $msg = '<div class="hidden-alert">Недействительные учетные данные</div>';
    }
    if ($data) {
        $msg = '<div class="hidden-success">Успешно</div>';
        redirect('/index.php', 'Вы успешно авторизовались.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
if ($newaccount == true) {
    $connect = Query::createAccount();
    redirect('/index.php', 'Ваш аккаунт успешно создан.', null, \core\output\notification::NOTIFY_SUCCESS);
}

$style = file_get_contents($CFG->dirroot . "/blocks/nlrsbook_auth/style/nlrsbook_auth.css");

$template = '
            <style>'.$style.'</style>
            <div class="row my-5">
                <div class="col-sm-12 mb-3">Вы можете войти в уже существуюший аккаунт НБ РС(Я) лиюо создать новый аккаунт.</div>
                <div class="col-sm-12 col-md-6">
                    <form method="post">
                        <div class="form-group">
                            <label for="login">Логин</label>
                            <input name="login" type="text" class="form-control form-control-lg" id="nlrsbook_auth_login" placeholder="Введите email или чит. билет НБ РС(Я)" value="'.$login.'">
                        </div>
                        <div class="form-group">
                            <label for="password">Пароль</label>
                            <input name="password" type="password" class="form-control form-control-lg" id="nlrsbook_auth_pass" placeholder="Введите пароль" value="">
                        </div>
                        '.$msg.'
                        <input type="submit" id="nlrsbook_auth_btn" class="btn btn-block btn-lg btn-primary" value="Войти">
                    </form>
                </div>
                <div class="col-sm-12 col-md-6" style="display: inline-flex; flex-wrap: wrap; justify-content: center; align-items: center;">
                    <form method="post">
                        <input name="newaccount" type="hidden" value="1">
                        <button type="submit" id="nlrsbook_auth_create" class="btn btn-lg btn-primary">Создать новыый аккаунт</button>
                    </form>
                </div>
            </div>';

echo $OUTPUT->header();

echo $template;

echo $OUTPUT->footer();