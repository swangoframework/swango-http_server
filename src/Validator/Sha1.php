<?php
namespace Swango\HttpServer\Validator;
class Sha1 extends \Swango\HttpServer\Validator {
    public function __construct($cnkey, $min_length = 0, $max_length = 4096) {
        parent::__construct($cnkey);
        $this->min_length = $min_length;
        $this->max_length = $max_length;
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须为sha1散列化之后的字符串';
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (is_array($value) || is_object($value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        $value = (string)$value;
        if (! preg_match("/[0-9a-fA-F]{40}$/", $value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}