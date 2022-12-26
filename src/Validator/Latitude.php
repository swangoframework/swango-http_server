<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static Latitude getInstance()
 */
class Latitude extends Double {
    public function __construct() {
        parent::__construct('纬度', null, - 90, 90);
    }
}