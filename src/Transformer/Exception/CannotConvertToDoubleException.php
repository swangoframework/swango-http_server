<?php
namespace Swango\HttpServer\Transformer\Exception;
class CannotCovertToDoubleException extends \RuntimeException {
    public function __construct($msg) {
        parent::__construct('Cannot covert this to double: ' . $msg);
    }
}