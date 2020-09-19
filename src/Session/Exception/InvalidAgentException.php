<?php
namespace Swango\HttpServer\Session\Exception;
class InvalidAgentException extends \ExceptionToResponse {
    public function __construct() {
        parent::__construct('Invalid agent', '非法的终端');
    }
}