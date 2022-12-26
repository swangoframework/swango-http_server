<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static getInstance($cnkey, $min_length = 0, $max_length = 4096)
 */
class HanZi extends Varchar {
    public function __construct($cnkey, $min_length = 0, $max_length = 4096) {
        parent::__construct($cnkey, $min_length, $max_length, false);
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        if (preg_match('/^.*[a-zA-Z\d]/', $value))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg() . ' 且必须为纯汉字');
    }
}