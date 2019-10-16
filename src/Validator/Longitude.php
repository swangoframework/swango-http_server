<?php
namespace Swango\HttpServer\Validator;
class Longitude extends Double {
    public function __construct() {
        parent::__construct('经度', null, - 180, 180);
    }
}