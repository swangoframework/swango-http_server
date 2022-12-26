<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static Integer getInstance(?string $cnkey, int $min = 0, int $max = 2147483647)
 */
class Integer extends \Swango\HttpServer\Validator {
    protected int $min, $max;
    public function __construct(?string $cnkey, int $min = 0, int $max = 2147483647) {
        parent::__construct($cnkey);
        $this->min = $min;
        $this->max = $max;
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg)) {
            return $this->cnmsg;
        }
        $ret = $this->cnkey . '必须为整数(' . $this->min . '~' . $this->max . ')';
        if (! $this->isOptional() || ! $this->couldBeNull()) {
            $ret .= '且不可缺省';
        }
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (! is_numeric($value)) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
        $value = (int)$value;
        if ($value < $this->min || $value > $this->max) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
    }
}