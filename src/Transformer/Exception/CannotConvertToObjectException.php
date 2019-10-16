<?php
namespace Swango\HttpServer\Transformer\Exception;
class CannotCovertToObjectException extends \RuntimeException {
    public function __construct($msg) {
        parent::__construct('Cannot covert this to object: ' . $msg);
    }
}