<?php
namespace Swango\HttpServer\Validator;
class Double extends \Swango\HttpServer\Validator {
    private ?int $decimal;
    private float $min, $max;
    public function __construct(?string $cnkey, ?int $decimal = null, float $min = 0.0, float $max = 2147483647.0) {
        parent::__construct($cnkey);
        $this->decimal = $decimal;
        $this->min = $min;
        $this->max = $max;
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg)) {
            return $this->cnmsg;
        }
        $ret = $this->cnkey . '必须为数字(' . $this->min . '~' . $this->max . ')';
        if (! $this->isOptional() || ! $this->couldBeNull()) {
            $ret .= '且不可缺省';
        }
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (! is_numeric($value)) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
        if (! isset($this->min) && ! isset($this->max)) {
            return;
        }
        if (isset($this->decimal)) {
            $value = (double)sprintf("%.{$this->decimal}f", $value);
        } else {
            $value = (double)$value;
        }
        if ($value < $this->min || $value > $this->max) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
    }
}