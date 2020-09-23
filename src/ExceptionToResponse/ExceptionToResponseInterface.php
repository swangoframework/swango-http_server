<?php
namespace ExceptionToResponse;
interface ExceptionToResponseInterface {
    public function getCnMsg();
    public function getMessage();
    public function getCode();
    public function getData();
}