<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static getInstance(?string $cnkey, int $min_length = 0, int $max_length = 4096, bool $more_strict = false)
 */
class Varchar extends \Swango\HttpServer\Validator {
    private int $min_length, $max_length;
    private bool $more_strict;
    public function __construct(?string $cnkey, int $min_length = 0, int $max_length = 4096, bool $more_strict = false) {
        parent::__construct($cnkey);
        $this->min_length = $min_length;
        $this->max_length = $max_length;
        $this->more_strict = $more_strict;
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg)) {
            return $this->cnmsg;
        }
        $ret = $this->cnkey . '必须为字符串，长度为' . $this->min_length . '~' . $this->max_length . '，不得含有' .
            ($this->more_strict ? '( ) = \' ` % " \\ & < >' : '\\ & < >');
        if (! $this->isOptional() || ! $this->couldBeNull()) {
            $ret .= '且不可缺省';
        }
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (is_array($value) || is_object($value)) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
        $value = (string)$value;
        $value = str_replace([
            "\x08",
            "\x7F"
        ], '', $value); // x08为退格字符 x7f为DEL字符
        $value = trim($value);
        if (isset($this->more_strict)) {
            if (preg_match($this->more_strict ? "/\(|\)|\=|\'|\\\"|\`|\%|\&|\<|\>|\\\/" : "/\&|\<|\>|\\\/", $value)) {
                throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
            }
        }
        $length = \XString\strRealLength($value);
        if ($length < $this->min_length || $length > $this->max_length) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
    }
}