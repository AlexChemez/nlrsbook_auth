<?php

require_once($CFG->dirroot . "/blocks/nlrsbook_auth/Query.php");

require_login();

use App\Querys\Query;

class block_nlrsbook_auth extends block_base {

    public function init() {
        $this->title = get_string('nlrsbook_auth', 'block_nlrsbook_auth');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $setting = get_config('nlrsbook_auth', 'org_private_key'); // Секретный ключ организации

        if ($setting) {
            $shelfUrl = Query::getUrl("https://nlrs.ru/lk/shelf");
            $ordersShelfUrl = Query::getUrl("https://new.nlrs.ru/lk/orders-shelf");
            $ticketsUrl = Query::getUrl("https://nlrs.ru/lk/tickets");

            $this->content = new stdClass;
            $this->content->text .= <<<HTML
                <div>
                    <div class="mb-3">
                        <a href="{$shelfUrl}" target="_blank" class="nlrsbook_shelf_card__btn btn btn-primary mb-1"><i class="fa fa-bookmark mr-2" aria-hidden="true"></i>Моя полка</a>
                        <a href="{$ordersShelfUrl}" target="_blank" class="nlrsbook_shelf_card__btn btn btn-primary mb-1"><i class="fa fa-book mr-2" aria-hidden="true"></i>Мои заказы</a>
                        <a href="{$ticketsUrl}" target="_blank" class="nlrsbook_shelf_card__btn btn btn-primary mb-1"><i class="fa fa-question-circle mr-2" aria-hidden="true"></i>Задать вопрос</a>
                    </div>
                </div>
            HTML;
        } else {
            $this->content = new stdClass;
            $this->content->text .= <<<HTML
                <div class="row">
                    <div class="col-sm-12">
                        <div class="alert alert-warning">
                            Плагин не настроен. Обратитесь к администратору образовательного учреждения.
                        </div>
                    </div>
                </div>
            HTML;
        }

        return $this->content;
    }

    function has_config()
    {
        return true;
    }
    
}