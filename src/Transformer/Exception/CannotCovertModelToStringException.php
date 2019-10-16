<?php
namespace Swango\HttpServer\Transformer\Exception;
class CannotCovertModelToStringException extends \RuntimeException {
    public function __construct($msg) {
        parent::__construct("Cannot covert model $msg to string");
    }
}