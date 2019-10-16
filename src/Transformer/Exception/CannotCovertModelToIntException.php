<?php
namespace Swango\HttpServer\Transformer\Exception;
class CannotCovertModelToIntException extends \RuntimeException {
    public function __construct($msg) {
        parent::__construct("Cannot covert model $msg to int");
    }
}