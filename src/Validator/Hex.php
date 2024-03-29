<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static Hex getInstance($cnkey, $min_length = 0, $max_length = 4096)
 */
class Hex extends \Swango\HttpServer\Validator {
    private $min_length, $max_length;
    public function __construct($cnkey, $min_length = 0, $max_length = 4096) {
        parent::__construct($cnkey);
        $this->min_length = $min_length;
        $this->max_length = $max_length;
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须为十六进制形式的字符串，长度为' . $this->min_length . '~' . $this->max_length;
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
        
        $pattern = '/^[a-fA-F0-9]{' . $this->min_length . ',' . $this->max_length . '}$/';
        if (! preg_match($pattern, $value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        $value = strtoupper($value);
    }
}