<?php
namespace Swango\HttpServer\Validator;
class AnyString extends \Swango\HttpServer\Validator {
    private $min_length, $max_length;
    public function __construct($cnkey, $min_length = 0, $max_length = 4096) {
        parent::__construct($cnkey);
        $this->min_length = $min_length;
        $this->max_length = $max_length;
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须为字符串，长度为' . $this->min_length . '~' . $this->max_length;
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (is_array($value) || is_object($value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        $value = trim(str_replace([
            "\x08",
            "\x7F"
        ], '', (string)$value));
        $length = \XString\strRealLength($value);
        if ($length < $this->min_length || $length > $this->max_length)
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}