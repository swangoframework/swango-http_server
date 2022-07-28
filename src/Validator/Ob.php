<?php
namespace Swango\HttpServer\Validator;
/**
 * 用于检验
 * 对象 或 每项含义不同的数组
 */
class Ob extends \Swango\HttpServer\Validator {
    private bool $set_null_when_empty = false;
    protected bool $do_not_validate_deeper = false;
    /**
     * @var \Swango\HttpServer\Validator[]
     */
    protected array $map = [];
    public function getCnMsg(): string {
        if (isset($this->cnmsg)) {
            return $this->cnmsg;
        }
        $ret = $this->cnkey . '必须为对象';
        if (! $this->isOptional() || ! $this->couldBeNull()) {
            $ret .= '且不可缺省';
        }
        return $ret;
    }
    public function __get($key) {
        if (array_key_exists($key, $this->map)) {
            return $this->map[$key];
        }
        return null;
    }
    public function __set(string $key, \Swango\HttpServer\Validator $value) {
        $this->map[$key] = $value;
    }
    public function __unset(string $key) {
        unset($this->map[$key]);
    }
    public function __isset(string $key): bool {
        return isset($this->map[$key]);
    }
    /**
     * 表示可能为null，默认为不能为null
     */
    public function setNullWhenEmpty(): self {
        $this->set_null_when_empty = true;
        return $this;
    }
    public function couldBeEmpty(): bool {
        return $this->set_null_when_empty;
    }
    protected function check(?string $key, &$value): void {
        if (! is_array($value) && ! is_object($value)) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
        if ($this->do_not_validate_deeper) {
            return;
        }
        if (is_object($value)) {
            if ($this->couldBeEmpty() && false === current($value)) {
                $value = null;
                return;
            }
            foreach ($this->map as $k => $validator) {
                if (! property_exists($value, $k)) {
                    if ($validator->isOptional()) {
                        continue;
                    }
                    throw new \ExceptionToResponse\InvalidParameterException("Invalid $key.$k",
                        $validator->getCnKey() . '不可缺省');
                }
                $validator->validate("$key.$k", $value->$k);
            }
            foreach ($value as $k => &$v)
                if (! array_key_exists($k, $this->map)) {
                    unset($value->$k);
                }
            unset($v);
        } else {
            if ($this->couldBeEmpty() && empty($value)) {
                $value = null;
                return;
            }
            foreach ($this->map as $k => $validator) {
                if (! array_key_exists($k, $value)) {
                    if ($validator->isOptional()) {
                        continue;
                    }
                    throw new \ExceptionToResponse\InvalidParameterException("Invalid $key.$k",
                        $validator->getCnKey() . '不可缺省');
                }
                $validator->validate("$key.$k", $value[$k]);
            }
            foreach ($value as $k => &$v)
                if (! array_key_exists($k, $this->map)) {
                    unset($value[$k]);
                }
            unset($v);
        }
    }
    public function setMap(array $map): self {
        $this->map = $map;
        return $this;
    }
    public function doNotValidateDeeper(): self {
        $this->do_not_validate_deeper = true;
        return $this;
    }
}