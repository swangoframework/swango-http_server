<?php
namespace Swango\HttpServer\Validator;
class Phone extends Integer {
    public function __construct(?string $key = null) {
        parent::__construct($key ?? '手机号', 13000000000, 19999999999);
        $this->cnmsg = '请填写正确的手机号';
        $this->attachValidateFunction(
            function (&$value): void {
                if (! preg_match("/1[3456789]{1}\d{9}$/", $value))
                    throw new \ExceptionToResponse('Invalid phone', '请填写正确的手机号');
            });
    }
}