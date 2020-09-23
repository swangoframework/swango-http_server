<?php
namespace ExceptionToResponse;
abstract class ModelNotFoundException extends \Swango\Model\Exception\ModelNotFoundException implements ExceptionToResponseInterface {
    public function getData() {
        return null;
    }
}