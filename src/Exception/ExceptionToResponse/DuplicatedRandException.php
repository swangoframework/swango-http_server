<?php
namespace ExceptionToResponse;
class DuplicatedRandException extends \ExceptionToResponse {
    const HEADER_STR = '451 Duplicated Rand';
    public function __construct(?string $rand) {
        parent::__construct('Duplicated rand. Hint: ' . $rand, '参数错误，请重试', 451);
    }
    public function getTraceToRecord() {
        return '';
    }
}