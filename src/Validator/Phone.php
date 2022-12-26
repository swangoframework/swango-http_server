<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static Phone getInstance(?string $key = null)
 */
class Phone extends Integer {
    public function __construct(?string $key = null) {
        parent::__construct($key ?? '手机号', 13000000000, 19999999999);
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        return '请填写正确的手机号';
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        if (! preg_match("/1[3456789]{1}\d{9}$/", $value))
            throw new \ExceptionToResponse('Invalid phone', '请填写正确的手机号');
    }
}