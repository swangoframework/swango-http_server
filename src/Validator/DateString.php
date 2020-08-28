<?php
namespace Swango\HttpServer\Validator;
class DateString extends Varchar {
    public function __construct(string $key) {
        parent::__construct($key, 8, 10);
    }
    public function getCnMsg(): string {
        return '非法的时间';
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        [
            $year,
            $month,
            $day
        ] = explode('-', $value);
        if (! isset($year) || ! isset($month) || ! isset($day) || ! is_numeric($year) || ! is_numeric($month) ||
             ! is_numeric($day) || $year < 2000 || $year > 2500 || $month < 1 || $month > 12 || $day < 1 || $day > 31)
            throw new \ExceptionToResponse\InvalidParameterException('Invalid ' . $key, $this->getCnMsg());
    }
}