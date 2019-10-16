<?php
namespace Swango\HttpServer\Transformer;

/**
 * 用于检测每项含义都相同的数组
 */
class FixedArray extends \Swango\HttpServer\Transformer {
    public function transform(&$value) {
        if (! isset($value))
            return;
        if (! is_array($value) && (! is_object($value) || ! method_exists($value, 'toArray')))
            throw new Exception\CannotCovertToFixedArrayException(var_export($value, true));
        if (is_object($value) && method_exists($value, 'toArray'))
            $value = $value->toArray();
        if (isset($this->content_transform))
            foreach ($value as $k=>&$i) {
                if (! isset($i)) {
                    if ($this->content_transform->needToUnsetOnNull())
                        unset($value[$k]);
                    continue;
                }
                $this->content_transform->transform($i);
            }
        $value = array_values($value);
    }
    private $content_transform;
    public function setContentTransformer(\Swango\HttpServer\Transformer $transform) {
        $this->content_transform = $transform;
        return $this;
    }
}