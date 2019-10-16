<?php
namespace Swango\HttpServer\Validator;
class Latitude extends Double {
    public function __construct() {
        parent::__construct('纬度', null, - 90, 90);
    }
}