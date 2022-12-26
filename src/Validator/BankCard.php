<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static getInstance()
 */
class BankCard extends Varchar {
    public function __construct() {
        parent::__construct('银行卡号', 15, 19);
        $this->cnmsg = '请填写正确的银行卡号';
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        $addNum = 0;
        $arr = [
            0, 
            2, 
            4, 
            6, 
            8, 
            1, 
            3, 
            5, 
            7, 
            9
        ];
        $fleg = true;
        $count = 0;
        for($i = strlen($value) - 1; $i >= 0; -- $i) {
            $num = $value{$i};
            if (! is_numeric($num))
                throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
            ++ $count;
            if ($count % 2 === 0)
                $num = $arr[$num];
            $addNum += $num;
        }
        if ($addNum % 10 != 0)
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}