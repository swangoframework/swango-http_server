<?php
namespace Swango\HttpServer\Transformer\Exception;
class CannotCovertToFixedArrayException extends \RuntimeException {
    public function __construct($msg) {
        parent::__construct('Cannot covert this to fixed array: ' . $msg);
    }
}