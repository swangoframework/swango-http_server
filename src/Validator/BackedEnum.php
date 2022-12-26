<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static getInstance($cnkey, string $enum_class, ?array $valid_array = null)
 */
class BackedEnum extends \Swango\HttpServer\Validator {
    public function __construct($cnkey, protected string $enum_class, protected ?array $valid_array = null) {
        parent::__construct($cnkey);
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg)) {
            return $this->cnmsg;
        }
        $enums = [];
        if (isset($this->valid_array)) {
            foreach ($this->valid_array as $case)
                $enums[] = $case->value;
        } else {
            foreach (($this->enum_class)::cases() as $case)
                $enums[] = $case->value;
        }
        $ret = $this->cnkey . '必须为(' . implode(',', $enums) . ')中的一项';
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
        if (isset($this->valid_array) && ! in_array($value, $this->valid_array)) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
    }
}