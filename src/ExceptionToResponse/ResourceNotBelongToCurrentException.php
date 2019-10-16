<?php
namespace ExceptionToResponse;
class ResourceNotBelongToCurrentException extends \ExceptionToResponse {
    const HEADER_STR = '403 Forbidden';
    public function __construct($enmsg = null, $cnmsg = null, $code = 200) {
        parent::__construct('Insufficient permissions', '试图访问当前用户不可见的资源', 403);
    }
}