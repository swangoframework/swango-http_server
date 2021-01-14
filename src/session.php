<?php
class session {
    const LIFETIME = 1209600; // 14天
    const SESSION_NAME = 'sid', SESSION_ID_LENGTH = 32;
    private static array $init_keys = [];
    private static $sid_generator = null;
    private static Swango\HttpServer\Session\AgentMapInterface $agent_map;
    public static function systemSetInitKeys(string ...$keys): void {
        if (defined('SWANGO_WORKING_IN_WORKER')) {
            throw new Exception('Cannot call session::setInitKeys() in worker');
        }
        if (defined('SWANGO_WORKING_IN_TASK')) {
            throw new Exception('Cannot call session::setInitKeys() in task worker');
        }
        if (empty($keys)) {
            return;
        }
        if (in_array('__', $keys)) {
            throw new Exception('Init keys must not contain "__"');
        }
        array_unshift($keys, '__');
        self::$init_keys = $keys;
    }
    public static function systemSetSidGenerator(callable $func): void {
        self::$sid_generator = $func;
    }
    public static function systemSetAgentMap(Swango\HttpServer\Session\AgentMapInterface $agent_map): void {
        self::$agent_map = $agent_map;
    }
    private static function generateSid(int $agent): string {
        if (null !== self::$sid_generator) {
            return (self::$sid_generator)($agent);
        }
        $time = (string)intval(microtime(true) * 10000);
        $tmp_string = base_convert($agent + 10, 10, 36);
        $tmp_string .= base_convert($time, 10, 36);
        $tmp_string .= substr(base_convert(crc32(SysContext::get('request_id')), 10, 36), 1);
        $sid = strtolower($tmp_string .
            XString\GenerateRandomString(self::SESSION_ID_LENGTH - 3 - strlen($tmp_string)));
        $crc = strtolower(substr(base_convert(crc16($sid), 10, 36), 1));
        $crc = str_repeat('0', 3 - strlen($crc)) . $crc;
        return $sid . $crc;
    }
    public static function getAgentMap(): Swango\HttpServer\Session\AgentMapInterface {
        if (! isset(self::$agent_map)) {
            self::$agent_map = new Swango\HttpServer\Session\Agent();
        }
        return self::$agent_map;
    }
    public static function start(Swoole\Http\Request $request, Swoole\Http\Response $response, ?string $sid = null, ?string $agent = null): void {
        if (isset($agent)) {
            $agent = self::getAgentMap()->getAgentId($agent);
        } else {
            $agent = null;
        }
        if (isset($sid) || (! isset($agent) && (isset($request->cookie[self::SESSION_NAME]) &&
                    preg_match('/[a-zA-Z0-9]{' . self::SESSION_ID_LENGTH . '}$/',
                        $request->cookie[self::SESSION_NAME])))) {
            $sid = $sid ?? $request->cookie[self::SESSION_NAME];
            cache::select(0);
            try {
                if (empty(self::$init_keys)) {
                    $session_string = cache::hGet($sid, '__');
                } else {
                    $session_data_array = cache::hMget($sid, self::$init_keys);
                    $session_string = current($session_data_array);
                }
                cache::select(1);
            } catch (Throwable $e) {
                cache::select(1);
                throw $e;
            }
            if (isset($session_string)) {
                $session_array = unpack('Jauth/Cagent/Nuid/Ntime', $session_string);
                $agent = $session_array['agent'];
                if ($agent === self::getAgentMap()->getWebAgentId()) {
                    $response->cookie(self::SESSION_NAME, $sid, Time\now() + 31536000, '/', null, true, true);
                } elseif (self::getAgentMap()->useCookieForSession($agent)) {
                    $response->cookie(self::SESSION_NAME, $sid, Time\now() + 31536000, '/');
                }
                $ob = new self($sid, $session_array['auth'],
                    $session_array['uid'] === 0xFFFFFFFF ? null : $session_array['uid'], $session_array['time']);
                $flag = true;
                foreach (self::$init_keys as $i => $key)
                    if ($flag) {
                        $flag = false;
                    } elseif (isset($session_data_array[$i])) {
                        try {
                            $ob->data->{$key} = Json::decodeAsObject($session_data_array[$i]);
                        } catch (JsonDecodeFailException $e) {
                            $ob->data->{$key} = null;
                        }
                    } else {
                        $ob->data->{$key} = null;
                    }
                $ob->agent = $agent;
                return;
            }
        } else {
            $sid = self::generateSid($agent);
        }
        if (! isset($ob)) {
            $ob = new self($sid, 0, null, Time\now());
            $ob->changed['__'] = true;
        }
        if (! isset($agent)) {
            if (isset($request->header['user-agent'])) {
                $agent = self::getAgentMap()->getAgentFromUserAgent($request->header['user-agent']);
            } else {
                $agent = self::getAgentMap()->getWebAgentId();
            }
            $response->cookie(self::SESSION_NAME, $sid, Time\now() + 31536000, '/');
        } elseif (self::getAgentMap()->useCookieForSession($agent)) {
            $response->cookie(self::SESSION_NAME, $sid, Time\now() + 31536000, '/');
        }
        $ob->agent = $agent;
    }
    /**
     * 将会启动新的协程运行$func，新协程中的session为指定sid
     * @param string $sid
     * @param callable $func
     * @param bool $wait_for_response （若设置为true，则本函数会等待新协程执行完成再返回，或直接抛出新协程中产生的Exception；否则立即返回）
     * @param float $timeout 当$wait_for_response为true时，则该项为超时时间，-1 永不超时
     * @return mixed 若$wait_for_response为true，则会返回$func的返回值；否则返回空
     * @throws \Swango\HttpServer\Session\Exception\SessionNotExistsException
     */
    public static function runUnderOtherSession(string $sid, callable $func, bool $wait_for_response = true, float $timeout = 10.0) {
        cache::select(0);
        try {
            if (empty(self::$init_keys)) {
                $session_data_array = [];
                $session_string = cache::hGet($sid, '__');
            } else {
                $session_data_array = cache::hMget($sid, self::$init_keys);
                $session_string = current($session_data_array);
            }
            cache::select(1);
        } catch (Throwable $e) {
            cache::select(1);
            throw $e;
        }
        if (! is_array($session_string)) {
            throw new Swango\HttpServer\Session\Exception\SessionNotExistsException();
        }
        $session_array = unpack('Jauth/Cagent/Nuid/Ntime', $session_string);
        if ($wait_for_response) {
            $channel = new \Swoole\Coroutine\Channel(1);
        } else {
            $channel = null;
        }
        go(function () use ($sid, $session_array, $session_data_array, $func, $channel) {
            try {
                $agent = $session_array['agent'];
                $ob = new self($sid, $session_array['auth'],
                    $session_array['uid'] === 0xFFFFFFFF ? null : $session_array['uid'], $session_array['time']);
                $flag = true;
                foreach (self::$init_keys as $i => $key)
                    if ($flag) {
                        $flag = false;
                    } elseif (isset($session_data_array[$i])) {
                        try {
                            $ob->data->{$key} = Json::decodeAsObject($session_data_array[$i]);
                        } catch (JsonDecodeFailException $e) {
                            $ob->data->{$key} = null;
                        }
                    } else {
                        $ob->data->{$key} = null;
                    }
                $ob->agent = $agent;
                $ret = $func();
                if (isset($channel)) {
                    $channel->push([
                        true,
                        $ret
                    ]);
                }
            } catch (Throwable $e) {
                if (isset($channel)) {
                    $channel->push([
                        false,
                        $e
                    ]);
                } else {
                    FileLog::logThrowable($e, \Swango\Environment::getDir()->log . 'error/', 'runUnderOtherSession');
                }
            }
        });
        $result = $channel->pop($timeout);
        if (! is_array($result)) {
            throw new \Exception('Run under other session overtime');
        } else {
            [
                $success,
                $ret
            ] = $result;
            if ($success) {
                return $ret;
            } else {
                throw $ret;
            }
        }
    }
    public static function startForWebhook(): void {
        $ob = new self('', 0, null, 0, false);
        $ob->agent = self::getAgentMap()->getWebhookAgentId();
    }
    public static function getUidBySid(string $sid): ?int {
        cache::select(0);
        try {
            $session_string = cache::hGet($sid, '__');
        } catch (Throwable $e) {
            cache::select(1);
            throw $e;
        }
        cache::select(1);
        if (isset($session_string)) {
            $session_array = unpack('Jauth/Cagent/Nuid/Ntime', $session_string);
            return $session_array['uid'] === 0xFFFFFFFF ? null : $session_array['uid'];
        }
        return null;
    }
    public static function end(): void {
        $ob = SysContext::get('session');
        if (isset($ob)) {
            $ob->saveIfNeeded();
        }
    }
    public static function getAgent(): ?int {
        $ob = SysContext::get('session');
        return isset($ob) ? $ob->agent : null;
    }
    public static function getAgentInString(): ?string {
        $ob = SysContext::get('session');
        return isset($ob) ? self::getAgentMap()->getAgent($ob->agent) : null;
    }
    public static function getSessionStartTime(): ?int {
        $ob = SysContext::get('session');
        return (isset($ob)) ? $ob->create_time : null;
    }
    public static function getId(): ?string {
        $ob = SysContext::get('session');
        return (isset($ob)) ? $ob->sid : null;
    }
    public static function newSid(): ?string {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return null;
        }
        $sid = self::generateSid($ob->agent);
        $ob->sid = $sid;
        $ob->changed['__'] = true;
        return $sid;
    }
    public static function setLifeTime(int $life_time): bool {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return false;
        }
        $ob->life_time = $life_time;
        return true;
    }
    public static function setUid(?int $uid): bool {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return false;
        }
        if ($ob->uid !== $uid) {
            $ob->uid = $uid;
            $ob->changed['__'] = true;
        }
        return true;
    }
    public static function getUid(): ?int {
        $ob = SysContext::get('session');
        return (isset($ob)) ? $ob->uid : null;
    }
    public static function set(string $key, $value): bool {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return false;
        }
        $ob->data->{$key} = $value;
        $ob->changed[$key] = true;
        return true;
    }
    /**
     * 立即操作redis并返回新值
     *
     * @param string $key
     * @param int $value
     * @return int|NULL
     */
    public static function incrBy(string $key, int $value): ?int {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return null;
        }
        $ret = $ob->hIncrBy($ob->sid, $key, $value);
        $ob->data->{$key} = $ret;
        if (array_key_exists($key, $ob->changed)) {
            unset($ob->changed[$key]);
        }
        return $ret;
    }
    public static function setIfNotExists(string $key, $value): bool {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return false;
        }
        if (array_key_exists($key, $ob->changed) && property_exists($ob->data, $key) && $ob->data->{$key} === null) {
            $ob->data->{$key} = $value;
            return true;
        }
        $flag = $ob->hSetNx($ob->sid, $key, $value);
        if ($flag) {
            $ob->data->{$key} = $value;
            if (array_key_exists($key, $ob->changed)) {
                unset($ob->changed[$key]);
            }
            return true;
        }
        return false;
    }
    public static function get(string $key) {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return null;
        }
        if (property_exists($ob->data, $key)) {
            return $ob->data->{$key};
        }
        $value = $ob->hGet($ob->sid, $key);
        if (isset($value)) {
            try {
                $value = Json::decodeAsObject($value);
            } catch (JsonDecodeFailException $e) {
                $value = null;
            }
        }
        $ob->data->{$key} = $value;
        return $value;
    }
    public static function has(string $key): bool {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return false;
        }
        if (property_exists($ob->data, $key)) {
            return isset($ob->data->{$key});
        }
        $value = $ob->hGet($ob->sid, $key);
        if (isset($value)) {
            try {
                $value = Json::decodeAsObject($value);
            } catch (JsonDecodeFailException $e) {
                $value = null;
            }
        }
        $ob->data->{$key} = $value;
        return isset($value);
    }
    public static function hasAuth(int $auth): bool {
        $ob = SysContext::get('session');
        return (isset($ob)) ? $ob->_hasAuth($auth) : false;
    }
    public static function addAuth(int $auth): void {
        $ob = SysContext::get('session');
        if (isset($ob)) {
            $ob->_addAuth($auth);
        }
    }
    public static function removeAuth(int $auth): void {
        $ob = SysContext::get('session');
        if (isset($ob)) {
            $ob->_removeAuth($auth);
        }
    }
    public static function hasNoAuthAtAll(): bool {
        $ob = SysContext::get('session');
        return (isset($ob)) ? 0 === $ob->auth : true;
    }
    public static function clear(): void {
        $ob = SysContext::get('session');
        if (! isset($ob)) {
            return;
        }
        $ob->delete($ob->sid);
        $ob->auth = 0;
        $ob->uid = null;
        $ob->data = new stdClass();
        $ob->changed = [
            '__' => true
        ];
    }
    /**
     *
     * @var int $auth 因为PHP整型上限的原因，最多仅支持63种权限
     */
    private string $sid;
    private int $auth, $create_time, $agent;
    private ?int $uid, $life_time;
    private bool $save_if_needed;
    private array $changed = [];
    private object $data;
    private function __construct(string $sid, int $auth, ?int $uid, int $create_time, bool $save_if_needed = true) {
        $this->sid = $sid;
        $this->auth = $auth;
        $this->uid = $uid;
        $this->create_time = $create_time;
        $this->data = new stdClass();
        $this->save_if_needed = $save_if_needed;
        SysContext::set('session', $this);
    }
    private static function _getAuthNum(int $auth): int {
        return 1 << ($auth - 1);
    }
    private function _hasAuth(int $auth): bool {
        return ($this->auth & self::_getAuthNum($auth)) > 0;
    }
    private function _addAuth(int $auth): self {
        $old = $this->auth;
        $this->auth |= self::_getAuthNum($auth);
        if ($this->auth !== $old) {
            $this->changed['__'] = true;
        }
        return $this;
    }
    private function _removeAuth(int $auth): self {
        if ($this->_hasAuth($auth)) {
            $this->auth ^= self::_getAuthNum($auth);
            $this->changed['__'] = true;
        }
        return $this;
    }
    private function getPackedBaseString(): string {
        return pack('JCNN', $this->auth, $this->agent, $this->uid ?? 0xFFFFFFFF, $this->create_time);
    }
    private function saveIfNeeded(): void {
        if (! $this->save_if_needed) {
            return;
        }
        $set = $del = [];
        if (array_key_exists('__', $this->changed)) {
            $set['__'] = $this->getPackedBaseString();
            unset($this->changed['__']);
        }
        $del = [];
        foreach ($this->changed as $key => $tmp) {
            $value = $this->data->{$key};
            if (isset($value)) {
                $set[$key] = Json::encode($value);
            } else {
                $del[] = $key;
            }
        }
        if (! empty($set)) {
            $this->hMSet($this->sid, $set);
        }
        if (! empty($del)) {
            $this->hDel($this->sid, ...$del);
        }
        $this->setTimeout($this->sid, $this->life_time ?? self::LIFETIME);
        $this->changed = [];
    }
    public function __call(string $name, $arguments) {
        cache::select(0);
        try {
            $ret = cache::__callStatic($name, $arguments);
        } catch (Throwable $e) {
            cache::select(1);
            throw $e;
        }
        cache::select(1);
        return $ret;
    }
}