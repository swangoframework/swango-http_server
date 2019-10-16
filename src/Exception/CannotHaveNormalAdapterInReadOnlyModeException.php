<?php
class CannotHaveNormalAdapterInReadOnlyModeException extends Exception {
    public function __contruct() {
        parent::__construct('Cannot have normal adapter in read only mode');
    }
}