<?php
namespace ExceptionToResponse;
class VerifyFailException extends \ExceptionToResponse {
    public function __construct() {
        parent::__construct('Verify fail', '验证失败，请重试');
    }
}