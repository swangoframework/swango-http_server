<?php
namespace ExceptionToResponse;
class BadRequestException extends \ExceptionToResponse {
    const HEADER_STR = '400 Bad Request';
    public function __construct($enmsg = null, $cnmsg = null, $code = 200) {
        parent::__construct('Bad request', '非法的请求', 400);
    }
}