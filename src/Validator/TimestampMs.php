<?php
namespace Swango\HttpServer\Validator;
/**
 * @method static getInstance($cnkey, $from = null, $to = null)
 */
class TimestampMs extends Integer {
    public function __construct($cnkey, $from = null, $to = null) {
        if (! isset($from))
            $from = 1514736000000; // 2018年
        if (! isset($to))
            $to = 1893427200000; // 2030年
        parent::__construct($cnkey, $from, $to);
    }
    public function getCnMsg(): string {
        if (isset($this->cnmsg))
            return $this->cnmsg;
        $ret = $this->cnkey . '必须符合时间格式(' . date('Y-n-j H:i', $this->min) . '~' . date('Y-n-j H:i', $this->max) . ')';
        if (! $this->isOptional() || ! $this->couldBeNull())
            $ret .= '且不可缺省';
        return $ret;
    }
}