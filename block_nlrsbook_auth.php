<?php
require_once($CFG->dirroot . "/blocks/nlrsbook_auth/Query.php");

use App\Querys\Query;

class block_nlrsbook_auth extends block_base {

    public function init() {
        $this->title = get_string('nlrsbook_auth', 'block_nlrsbook_auth');
    }

    public function get_content() {
        global $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $setting = get_config('nlrsbook_auth', 'org_private_key'); // Секретный ключ организации
        $auth_msg = file_get_contents($CFG->dirroot . "/blocks/nlrsbook_auth/message/auth.php");
        $setting_msg = file_get_contents($CFG->dirroot . "/blocks/nlrsbook_auth/message/setting.php");

        $this->content = new stdClass;
        if ($setting) {
            if (Query::getToken()) {
                $shelfUrl = Query::getUrl("https://nlrs.ru/lk/shelf");
                $ordersShelfUrl = Query::getUrl("https://new.nlrs.ru/lk/orders-shelf");
                $ticketsUrl = Query::getUrl("https://nlrs.ru/lk/tickets");
                $style = file_get_contents($CFG->dirroot . "/blocks/nlrsbook_auth/style/nlrsbook_auth.css");

                $this->content->text = <<<HTML
                        <style>{$style}</style>
                        <div class="nlrsbook_auth mb-3">
                            <a href="{$shelfUrl}" target="_blank" class="nlrsbook_auth__btn btn btn-lg btn-primary mb-1"><i class="fa fa-bookmark mr-2" aria-hidden="true"></i>Моя полка</a>
                            <a href="{$ordersShelfUrl}" target="_blank" class="nlrsbook_auth__btn btn btn-lg btn-primary mb-1"><i class="fa fa-book mr-2" aria-hidden="true"></i>Мои заказы</a>
                            <a href="{$ticketsUrl}" target="_blank" class="nlrsbook_auth__btn btn btn-lg btn-primary mb-1"><i class="fa fa-question-circle mr-2" aria-hidden="true"></i>Задать вопрос</a>
                        </div>            
                HTML;
            } else {
                $this->content->text = $auth_msg;
            }
        } else {
            $this->content->text = $setting_msg;
        }

        return $this->content;
    }

    function has_config()
    {
        return true;
    }
    
}