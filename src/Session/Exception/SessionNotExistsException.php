<?php
namespace Swango\HttpServer\Session\Exception;
class SessionNotExistsException extends \Exception {
    public function __construct() {
        parent::__construct('Session not exists');
    }
}