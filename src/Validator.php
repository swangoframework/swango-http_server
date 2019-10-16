<?php
namespace Swango\HttpServer;
abstract class Validator {
    private $optional = false, $could_be_null = false, $key;
    protected $cnmsg, $cnkey, $validate_function;
    public function __construct(?string $cnkey) {
        $this->cnkey = $cnkey ?? '';
        $this->validate_function = new \SplQueue();
    }
    /**
     * 设置为非必须项
     */
    public function optional() {
        $this->optional = true;
        return $this;
    }
    /**
     *
     * @return boolean
     */
    public function isOptional() {
        return $this->optional;
    }
    /**
     * 表示可能为null，默认为不能为null
     */
    public function null() {
        $this->could_be_null = true;
        return $this;
    }
    /**
     *
     * @return boolean
     */
    public function couldBeNull() {
        return $this->could_be_null;
    }
    public function setCnMsg($cnmsg) {
        $this->cnmsg = $cnmsg;
        return $this;
    }
    public function getCnMsg() {
        return $this->cnmsg;
    }
    public function getCnKey() {
        return $this->cnkey;
    }
    public function attachValidateFunction($function) {
        $this->validate_function->enqueue($function);
        return $this;
    }
    abstract protected function check(?string $key, &$value): void;
    public function validate($key, &$value) {
        if (! isset($value) || $value === '')
            if ($this->couldBeNull()) {
                if ($value === '')
                    $value = null;
                return;
            } else
                throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        $this->check($key, $value);
        if (method_exists($this, 'validate_function'))
            $this->validate_function($value);
        foreach ($this->validate_function as $func)
            $func($value);
    }
}