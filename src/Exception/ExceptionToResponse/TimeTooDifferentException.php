<?php
namespace ExceptionToResponse;
class TimeTooDifferentException extends \ExceptionToResponse {
    const HEADER_STR = '450 Time Too Different';
    public function __construct($enmsg = null, $cnmsg = null, $code = 200) {
        parent::__construct('Time too different', '本地时间与服务器时间相差太多，请调整本地时间', 450,
            [
                'servertime' => (int)(microtime(true) * 1000)
            ]);
    }
    public function getTraceToRecord() {
        return '';
    }
}