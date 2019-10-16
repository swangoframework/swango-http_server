<?php
namespace Swango\HttpServer\Validator;

/**
 * 用于检测每项含义都相同的数组
 */
class FixedArray extends \Swango\HttpServer\Validator {
    private $min_length, $max_length, $content_validator;
    public function __construct($cnkey, $min_length = 0, $max_length = 4096) {
        parent::__construct($cnkey);
        $this->min_length = $min_length;
        $this->max_length = $max_length;
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须为数组';
        if ($this->min_length > 0)
            $ret .= '，最少' . $this->min_length . '项';
        $ret .= '，最长' . $this->max_length . '项';
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (! is_array($value) || is_object($value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        $i = 0;
        foreach ($value as $k=>&$v)
            if ($k != $i ++)
                throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        unset($v);
        $length = count($value);
        if ($length < $this->min_length || $length > $this->max_length)
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        foreach ($value as $k=>&$v)
            $this->content_validator->validate("$key.$k", $v);
    }
    public function setContentValidator(\Swango\HttpServer\Validator $validator) {
        $this->content_validator = $validator;
        return $this;
    }
}