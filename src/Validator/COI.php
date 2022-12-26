<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static getInstance()
 */
class COI extends Varchar {
    public function __construct() {
        parent::__construct('身份证号', 18, 18);
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg)) {
            return $this->cnmsg;
        }
        return '请填写正确的身份证号';
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        $idcard_base = substr($value, 0, 17);
        $verify_code = substr($value, 17, 1);
        if ('x' === $verify_code) {
            $verify_code = 'X';
        }
        if (bccomp($idcard_base, '11000000000000000') < 0 || bccomp($idcard_base, '66000000000000000') > 0 ||
            (! is_numeric($verify_code) && $verify_code != 'X')) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
        // 校验码对应值
        $verify_code_list = [
            '1',
            '0',
            'X',
            '9',
            '8',
            '7',
            '6',
            '5',
            '4',
            '3',
            '2'
        ];
        // 根据前17位计算校验码
        $total = 0;
        foreach ([
                     7,
                     9,
                     10,
                     5,
                     8,
                     4,
                     2,
                     1,
                     6,
                     3,
                     7,
                     9,
                     10,
                     5,
                     8,
                     4,
                     2
                 ] as $i => $v)
            $total += $idcard_base{$i} * $v;
        if ($verify_code != $verify_code_list[$total % 11]) {
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        }
        $value = $idcard_base . $verify_code;
    }
}