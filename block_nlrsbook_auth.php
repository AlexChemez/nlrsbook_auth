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

        $shelfUrl = Query::getUrl("https://nlrs.ru/lk/shelf");
        $ordersShelfUrl = Query::getUrl("https://new.nlrs.ru/lk/orders-shelf");
        $ticketsUrl = Query::getUrl("https://nlrs.ru/lk/tickets");

        $this->content = new stdClass;
        $this->content->text .= <<<HTML
            <div>
                <div class="mb-3">
                    <a href="{$shelfUrl}" target="_blank" class="nlrsbook_shelf_card__btn btn btn-primary mb-1">Моя полка</a>
                    <a href="{$ordersShelfUrl}" target="_blank" class="nlrsbook_shelf_card__btn btn btn-primary mb-1">Мои заказы</a>
                    <a href="{$ticketsUrl}" target="_blank" class="nlrsbook_shelf_card__btn btn btn-primary mb-1">Задать вопрос</a>
                </div>
            </div>
        HTML;

        return $this->content;
    }

    function has_config()
    {
        return true;
    }

}
