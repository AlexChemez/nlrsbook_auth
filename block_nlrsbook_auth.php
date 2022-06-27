<?php

class block_nlrsbook_auth extends block_base {

    public function init() {
        $this->title = get_string('nlrsbook_auth', 'block_nlrsbook_auth');
    }

    public function get_content() {
        global $CFG;
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text .= <<<HTML
            <div>
                <h4 class="mb-3">Авторизация</h4>
            </div>
        HTML;

        return $this->content;
    }
    
/*
    public function hide_header()
    {
        return true;
    }
*/

    function has_config()
    {
        return true;
    }

}