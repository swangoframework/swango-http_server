<?php
namespace ExceptionToResponse;
class InvalidParameterException extends \ExceptionToResponse {
    const HEADER_STR = '400 Bad Request';
    public function __construct($enmsg = null, $cnmsg = null, $code = 200) {
        parent::__construct($enmsg, $cnmsg, 400);
    }
}