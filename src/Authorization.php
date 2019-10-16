<?php
namespace Swango\HttpServer;
abstract class Authorization {
    public const AUTH_NONE = 0;
    private static $func;
    public static function getUidWithRole(): string {
        if (null === self::$func) {
            $user_id = \session::getUid();
            if (null === $user_id)
                return '';
            return $user_id;
        } else {
            return (self::$func)();
        }
    }
    public static function registerGetUidWithRoleFunc(callable $func): void {
        self::$func = $func;
    }
}