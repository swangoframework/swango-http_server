<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static Longitude getInstance()
 */
class Longitude extends Double {
    public function __construct() {
        parent::__construct('经度', null, - 180, 180);
    }
}