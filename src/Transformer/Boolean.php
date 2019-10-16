<?php
namespace Swango\HttpServer\Transformer;
class Boolean extends \Swango\HttpServer\Transformer {
    public function transform(&$value) {
        if (! isset($value))
            return;
        return (boolean)$value;
    }
}