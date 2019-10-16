<?php
namespace Swango\HttpServer\Transformer\Exception;
class CannotCovertToIntException extends \RuntimeException {
    public function __construct($msg) {
        parent::__construct('Cannot covert this to int: ' . $msg);
    }
}