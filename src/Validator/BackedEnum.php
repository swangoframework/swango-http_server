<?php
namespace Swango\HttpServer\Validator;
class BackedEnum extends \Swango\HttpServer\Validator {
    public function __construct($cnkey, protected string $enum_class) {
        parent::__construct($cnkey);
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg)) {
            return $this->cnmsg;
        }
        $enum = [];
        foreach (($this->enum_class)::cases() as $case)
            $enum[] = $case->value;
        $ret = $this->cnkey . '必须为(' . implode(',', $enum) . ')中的一项';
        if (! $this->isOptional() || ! $this->couldBeNull()) {
            $ret .= '且不可缺省';
        }
        return $ret;
    }
    protected function check(?string $key, &$value): void {
        if (is_array($value) || is_object($value)) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
        $value = ($this->enum_class)::tryFrom($value);
        if (! isset($value)) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
    }
}