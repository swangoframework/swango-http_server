<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static Boolean getInstance(?string $cnkey)
 */
class Boolean extends \Swango\HttpServer\Validator {
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须为布尔型';
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (! is_bool($value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}