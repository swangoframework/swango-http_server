<?php
namespace Swango\HttpServer\Transformer;

/**
 * 用于检验
 * 对象 或 每项含义不同的数组
 */
class Ob extends \Swango\HttpServer\Transformer {
    public function transform(&$value) {
        if (! isset($value))
            return;
        $temp = null;
        if ($value instanceof \Swango\Model\AbstractBaseGateway) {
            $temp = $value->getProfileForClient();
        } elseif (is_object($value) && method_exists($value, 'toArray')) {
            $temp = $value->toArray();
        } else
            $temp = $value;
        if (! isset($temp))
            throw new Exception\CannotCovertToObjectException(var_export($value, true));

        $unset = [];
        foreach ($temp as $k=>&$v) {
            if ($v instanceof \Swango\Model\AbstractBaseGateway)
                $v = $v->getProfileForClient();
            if (array_key_exists($k, $this->map)) {
                /**
                 *
                 * @var $transformer \Transformer
                 */
                $transformer = $this->map[$k];
                if (! isset($v)) {
                    if ($transformer->needToUnsetOnNull())
                        $unset[] = $k;
                    continue;
                }
                $transformer->transform($v);
            }
        }
        unset($v);
        foreach ($unset as $k)
            unset($temp[$k]);

        @ksort($temp);
        $value = empty($temp) ? new \stdClass() : $temp;
    }
    private $map = [];
    public function setMap(array $map): self {
        $this->map = $map;
        return $this;
    }
}