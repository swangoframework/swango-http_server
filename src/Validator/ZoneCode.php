<?php
namespace Swango\HttpServer\Validator;
class ZoneCode extends Integer {
    public function __construct() {
        parent::__construct('地区码', 1000, 9032);
        $this->cnmsg = '错误的地区码';
    }
}