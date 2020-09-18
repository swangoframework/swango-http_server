<?php
interface ExceptionToResponseInterface {
    public function getCnMsg();
    public function getMessage();
    public function getCode();
    public function getData();
}
class ExceptionToResponse extends \Exception implements ExceptionToResponseInterface {
    const HEADER_STR = '200 OK';
    private ?string $cnmsg;
    private $data;
    public function __construct(?string $enmsg = null, ?string $cnmsg = null, int $code = 200, $data = null, $retry_time = null) {
        $this->cnmsg = $cnmsg;
        $this->data = $data;
        if (isset($retry_time)) {
            $this->retryTime = $retry_time;
        }
        parent::__construct($enmsg, $code);
    }
    public function getCnMsg() {
        return $this->cnmsg;
    }
    public function getData() {
        return $this->data;
    }
}