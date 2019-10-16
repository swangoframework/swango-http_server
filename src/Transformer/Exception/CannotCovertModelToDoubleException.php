<?php
namespace Swango\HttpServer\Transformer\Exception;
class CannotCovertModelToDoubleException extends \RuntimeException {
    public function __construct($msg) {
        parent::__construct("Cannot covert model $msg to double");
    }
}