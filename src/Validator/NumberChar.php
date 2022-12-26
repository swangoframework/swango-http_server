<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static NumberChar getInstance($cnkey, $length)
 */
class NumberChar extends \Swango\HttpServer\Validator {
    private $lenght;
    public function __construct($cnkey, $length) {
        parent::__construct($cnkey);
        $this->lenght = $length;
    }
    public function getCnMsg() {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须为数字字符，长度为' . $this->lenght . '，不得含有字母或其它字符';
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (is_array($value) || is_object($value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        $value = (string)$value;
        
        $value = str_replace([
            "\x08", 
            "\x7F"
        ], '', $value); // x08为退格字符 x7f为DEL字符
        $value = trim($value);
        if (! preg_match("/^[0-9]{" . $this->lenght . "}$/", $value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}