<?php
namespace Swango\HttpServer\Validator;
class Email extends \Swango\HttpServer\Validator {
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须为正确格式的邮箱';
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
    private static $emailValidator;
    protected function check(?string $key, &$value): void {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}