<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static ZoneCode getInstance()
 */
class ZoneCode extends Integer {
    public function __construct() {
        parent::__construct('地区码', 1000, 9032);
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        return '错误的地区码';
    }
}