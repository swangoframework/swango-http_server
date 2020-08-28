<?php
namespace Swango\HttpServer\Validator;
class UUIdValidator extends \Swango\HttpServer\Validator\AnyString {
    public function __construct(string $cnkey = 'uuid') {
        parent::__construct($cnkey, 36, 36);
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须符合uuid格式';
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        $value = strtolower($value);
        if (! preg_match("/^([0-9a-f]{8})-([0-9a-f]{4})-(4[0-9a-f]{3})-([8-9a-b][0-9a-f]{3})-([0-9a-f]{12})$/", $value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}