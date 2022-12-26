<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static getInstance(?string $key, &$value)
 */
class Anything extends \Swango\HttpServer\Validator {
    protected function check(?string $key, &$value): void {}
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        if (! $this->isOptional() || ! $this->couldBeNull())
            return $this->cnkey . '不可缺省';
        return '';
    }
}