<?php
namespace Swango\HttpServer;
abstract class Transformer {
    private $unset_on_null = false;
    /**
     * 当该项为null时，unset掉
     */
    public function unsetOnNull(): self {
        $this->unset_on_null = true;
        return $this;
    }
    public function needToUnsetOnNull(): bool {
        return $this->unset_on_null;
    }
    abstract public function transform(&$value);
}