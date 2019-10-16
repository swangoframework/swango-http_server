<?php
namespace Swango\HttpServer\Validator;
class Date extends Ob {
    private $not_futrue;
    public function __construct(string $cnkey, bool $not_futrue = true) {
        parent::__construct($cnkey);
        $this->not_futrue = $not_futrue;
        $this->map = [
            'year' => new Integer('年', 2019, (int)date('Y', \Time\now())),
            'month' => new Integer('月', 1, 12),
            'day' => new Integer('日', 1, 31)
        ];
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        if ($value->day > \Time\getDaysOfAMonth($value->year, $value->month))
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
        if ($this->not_futrue)
            if (mktime(0, 0, 0, $value->month, $value->day, $value->year) > \Time\getTomorrow())
                throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, '不能选择未来的日期');
    }
}