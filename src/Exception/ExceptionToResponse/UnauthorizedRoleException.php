<?php
namespace ExceptionToResponse;
class UnauthorizedRoleException extends \ExceptionToResponse {
    const HEADER_STR = '401 Unauthorized';
    public function __construct($enmsg = null, $cnmsg = null, $code = 200) {
        parent::__construct('Unauthorized role', '身份信息错误，请重新登录', 401);
    }
    public function getTraceToRecord() {
        return '';
    }
}