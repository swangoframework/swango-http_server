<?php
namespace ExceptionToResponse;
class ResourceNotExistsException extends \ExceptionToResponse {
    const HEADER_STR = '404 Not Found';
    public function __construct($enmsg = null, $cnmsg = null, $code = 200) {
        parent::__construct('Resource not exists', '请求地址不存在', 404);
    }
    public function getTraceToRecord() {
        return '';
    }
}