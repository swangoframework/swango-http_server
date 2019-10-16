<?php
namespace ExceptionToResponse;
class InsufficientPermissionsException extends \ExceptionToResponse {
    const HEADER_STR = '403 Forbidden';
    public function __construct($enmsg = null, $cnmsg = null, $code = 200) {
        parent::__construct('Insufficient permissions', '登录状态失效，请重新授权', 401);
    }
}