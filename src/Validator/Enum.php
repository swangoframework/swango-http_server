<?php
namespace Swango\HttpServer\Validator;
class Enum extends \Swango\HttpServer\Validator {
    private $enum, $all_string;
    public function __construct($cnkey, array $enum) {
        parent::__construct($cnkey);
        $this->enum = $enum;
        $this->all_string = true;
        foreach ($enum as $item)
            if (! is_string($item) && ! is_numeric($item)) {
                $this->all_string = false;
                break;
            }
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        if ($this->all_string)
            $ret = $this->cnkey . '必须为(' . implode(',', $this->enum) . ')中的一项';
        else
            $ret = $this->cnkey . '必须为合法项';
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
        if (preg_match("/\&|\<|\>|\\\/", $value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        if (in_array($value, $this->enum))
            return;
        if (! $this->all_string) {
            foreach ($this->enum as $validator)
                if ($validator instanceof \Swango\HttpServer\Validator) {
                    try {
                        $validator->validate($key, $value);
                        return;
                    } catch(\Exception $e) {}
                }
        }
        throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}