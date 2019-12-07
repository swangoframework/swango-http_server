<?php
class session {
    const LIFETIME = 1209600; // 14天
    const SESSION_NAME = 'sid', SESSION_ID_LENGTH = 32;
    public const Agent_web = 1, Agent_wx = 2, Agent_ali = 3, Agent_app = 4;
    public const Agent_wmp = 5, Agent_webhook = 6, Agent_qmp = 7, Agent_oppo = 8, Agent_badam = 9, Agent_toutiao = 9, Agent_baidu = 10;
    public static function start(\Swoole\Http\Request $request, \Swoole\Http\Response $response, ?string $sid = null,
        ?string $agent = null): void {
        switch ($agent) {
            case 'app' :
                $agent = self::Agent_app;
                break;
            case 'wmp' :
                $agent = self::Agent_wmp;
                break;
            case 'qmp' :
                $agent = self::Agent_qmp;
                break;
            case 'oppo' :
                $agent = self::Agent_oppo;
                break;
            case 'badam' :
                $agent = self::Agent_badam;
                break;
            case 'toutiao' :
                $agent = self::Agent_toutiao;
                break;
            case 'baidu' :
                $agent = self::Agent_baidu;
                break;
            case 'mp' :
                $agent = self::Agent_wx;
                break;
            default :
                $agent = null;
        }

        if (isset($sid) || (! isset($agent) && (isset($request->cookie[self::SESSION_NAME]) &&
             preg_match('/[a-zA-Z0-9]{' . self::SESSION_ID_LENGTH . '}$/', $request->cookie[self::SESSION_NAME])))) {
            $sid = $sid ?? $request->cookie[self::SESSION_NAME];

            $redis = \Swango\Cache\RedisPool::pop();
            $redis->select(0);
            $session_string = $redis->hGet($sid, '__');
            if ($redis->errCode !== 0)
                throw new \Swango\Cache\RedisErrorException("Redis error: [$redis->errCode] $redis->errMsg",
                    $redis->errCode);
            \Swango\Cache\RedisPool::push($redis);

            if (isset($session_string)) {
                $session_array = unpack('Jauth/Cagent/Nuid/Ntime', $session_string);
                $agent = $session_array['agent'];
                if ($agent === self::Agent_web)
                    $response->cookie(self::SESSION_NAME, $sid, \Time\now() + 31536000, '/', null, true, true);
                elseif (self::getAgentInString($agent) === null)
                    $response->cookie(self::SESSION_NAME, $sid, \Time\now() + 31536000, '/');

                $ob = new self($sid, $session_array['auth'],
                    $session_array['uid'] === 4294967295 ? null : $session_array['uid'], $session_array['time']);
                $ob->agent = $agent;
                return;
            }
        } else {
            $tmp_string = self::getAgentInString($agent) ?? '';
            $tmp_string .= strtolower(base_convert(intval(microtime(true) * 1000), 10, 36));

            $sid = $tmp_string . \XString\GenerateRandomString(self::SESSION_ID_LENGTH - strlen($tmp_string));
        }

        if (! isset($ob)) {
            $ob = new self($sid, 0, null, \Time\now());
            $ob->changed['__'] = true;
        }

        if (! isset($agent)) {
            if (isset($request->header['user-agent'])) {
                $user_agent = $request->header['user-agent'];
                if (($pos = strpos($user_agent, 'MicroMessenger')) !== false)
                    $agent = self::Agent_wx;
                elseif (($pos = strpos($user_agent, 'Alipay')) !== false)
                    $agent = self::Agent_ali;
                else
                    $agent = self::Agent_web;
            } else
                $agent = self::Agent_web;

            $response->cookie(self::SESSION_NAME, $sid, \Time\now() + 31536000, '/');
        } elseif ($agent === self::Agent_wx || $agent === self::Agent_ali || $agent === self::Agent_web)
            $response->cookie(self::SESSION_NAME, $sid, \Time\now() + 31536000, '/');
        $ob->agent = $agent;
    }
    public static function startForWebhook(): void {
        $ob = new self('', 0, null, 0, false);
        $ob->agent = self::Agent_webhook;
    }
    public static function getUidBySid(string $sid): ?int {
        $redis = \Swango\Cache\RedisPool::pop();
        $redis->select(0);
        $session_string = $redis->hGet($sid, '__');
        if ($redis->errCode !== 0)
            throw new \Swango\Cache\RedisErrorException("Redis error: [$redis->errCode] $redis->errMsg", $redis->errCode);
        \Swango\Cache\RedisPool::push($redis);

        if (isset($session_string)) {
            $session_array = unpack('Jauth/Cagent/Nuid/Ntime', $session_string);
            return $session_array['uid'] === 4294967295 ? null : $session_array['uid'];
        }
        return null;
    }
    public static function end(): void {
        $ob = \SysContext::get('session');
        if (isset($ob))
            $ob->saveIfNeeded();
    }
    public static function getAgent(): ?int {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return null;
        return $ob->agent;
    }
    public static function getAgentInString(?string $agent = null): ?string {
        if (! isset($agent)) {
            $ob = \SysContext::get('session');
            if (! isset($ob))
                return null;
            $agent = $ob->agent;
        }
        switch ($agent) {
            case self::Agent_app :
                return 'app';
            case self::Agent_wmp :
                return 'wmp';
            case self::Agent_qmp :
                return 'qmp';
            case self::Agent_oppo :
                return 'oppo';
            case self::Agent_badam :
                return 'badam';
            case self::Agent_toutiao :
                return 'toutiao';
            case self::Agent_baidu :
                return 'baidu';
            case self::Agent_wx :
                return 'mp';
            default :
                return null;
        }
    }
    public static function getSessionStartTime(): ?int {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return null;
        return $ob->create_time;
    }
    public static function getId(): ?string {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return null;
        return $ob->sid;
    }
    public static function newSid(): ?string {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return null;
        $tmp_string = self::getAgentInString() ?? '';
        $tmp_string .= strtolower(base_convert(intval(microtime(true) * 1000), 10, 36));

        $sid = $tmp_string . \XString\GenerateRandomString(self::SESSION_ID_LENGTH - strlen($tmp_string));
        $ob->sid = $sid;
        $ob->changed['__'] = true;
        return $sid;
    }
    public static function setLifeTime(int $life_time): bool {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return false;
        $ob->life_time = $life_time;
        return true;
    }
    public static function setUid(?int $uid): bool {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return false;
        if ($ob->uid !== $uid) {
            $ob->uid = $uid;
            $ob->changed['__'] = true;
        }
        return true;
    }
    public static function getUid(): ?int {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return null;
        return $ob->uid;
    }
    public static function set(string $key, $value): bool {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return false;
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
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return null;
        $ret = $ob->hIncrBy($ob->sid, $key, $value);
        $ob->data->{$key} = $ret;
        if (array_key_exists($key, $ob->changed))
            unset($ob->changed[$key]);
        return $ret;
    }
    public static function setIfNotExists(string $key, $value): bool {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return false;
        if (array_key_exists($key, $ob->changed) && property_exists($ob->data, $key) && $ob->data->{$key} === null) {
            $ob->data->{$key} = $value;
            return true;
        }

        $flag = $ob->hSetNx($ob->sid, $key, $value);
        if ($flag) {
            $ob->data->{$key} = $value;
            if (array_key_exists($key, $ob->changed))
                unset($ob->changed[$key]);
            return true;
        }
        return false;
    }
    public static function get(string $key) {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return null;
        if (property_exists($ob->data, $key))
            return $ob->data->{$key};

        $value = $ob->hGet($ob->sid, $key);

        if (isset($value))
            $value = \Swoole\serialize::unpack($value);
        $ob->data->{$key} = $value;
        return $value;
    }
    public static function has(string $key): bool {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return false;
        if (property_exists($ob->data, $key))
            return isset($ob->data->{$key});

        $value = $ob->hGet($ob->sid, $key);

        if (isset($value))
            $value = \Swoole\serialize::unpack($value);
        $ob->data->{$key} = $value;
        return isset($value);
    }
    public static function hasAuth(int $auth): bool {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return false;
        return $ob->_hasAuth($auth);
    }
    public static function addAuth(int $auth): void {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return;
        $ob->_addAuth($auth);
    }
    public static function removeAuth(int $auth): void {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return;
        $ob->_removeAuth($auth);
    }
    public static function hasNoAuthAtAll(): bool {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return true;
        return $ob->auth === 0;
    }
    public static function clear(): void {
        $ob = \SysContext::get('session');
        if (! isset($ob))
            return;
        $ob->delete($ob->sid);
        $ob->auth = 0;
        $ob->uid = null;
        $ob->data = new \stdClass();
        $ob->changed = [
            '__' => true
        ];
    }

    /**
     *
     * @var int $auth 因为PHP整型上限的原因，最多仅支持63种权限
     */
    private $sid, $auth, $agent, $uid, $create_time, $data, $changed = [], $save_if_needed, $life_time;
    private function __construct(string $sid, int $auth, ?int $uid, int $create_time, $save_if_needed = true) {
        $this->sid = $sid;
        $this->auth = $auth;
        $this->uid = $uid;
        $this->create_time = $create_time;
        $this->data = new \stdClass();
        $this->save_if_needed = $save_if_needed;
        \SysContext::set('session', $this);
    }
    private static function _getAuthNum(int $auth): int {
        if ($auth === 1)
            return 1;
        if ($auth === 2)
            return 2;
        return 2 << ($auth - 2);
    }
    private function _hasAuth(int $auth): bool {
        return ($this->auth & self::_getAuthNum($auth)) > 0;
    }
    private function _addAuth(int $auth): self {
        $old = $this->auth;
        $this->auth |= self::_getAuthNum($auth);
        if ($this->auth !== $old)
            $this->changed['__'] = true;
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
        return pack('JCNN', $this->auth, $this->agent, $this->uid ?? 4294967295, $this->create_time);
    }
    private function saveIfNeeded(): void {
        if (! $this->save_if_needed)
            return;

        $set = $del = [];
        if (array_key_exists('__', $this->changed)) {
            $set['__'] = $this->getPackedBaseString();
            unset($this->changed['__']);
        }
        $del = [];
        foreach ($this->changed as $key=>$tmp) {
            $value = $this->data->{$key};
            if (isset($value))
                $set[$key] = \Swoole\serialize::pack($value);
            else
                $del[] = $key;
        }
        if (! empty($set) && ! empty($del)) {
            $container = \Swlib\Archer::getMultiTask();
            $container->addTask([
                $this,
                'hMSet'
            ], [
                $this->sid,
                $set
            ]);
            array_unshift($del, $this->sid);
            $container->addTask([
                $this,
                'hDel'
            ], $del);
            $container->waitForAll();
        } elseif (! empty($set)) {
            $this->hMSet($this->sid, $set);
        } elseif (! empty($del)) {
            $this->hDel($this->sid, ...$del);
        }
        $this->setTimeout($this->sid, $this->life_time ?? self::LIFETIME);
        $this->changed = [];
    }
    public function __call(string $name, $arguments) {
        $redis = \Swango\Cache\RedisPool::pop();
        $redis->select(0);
        if ($redis->errCode !== 0)
            throw new \Swango\Cache\RedisErrorException("Redis error: [$redis->errCode] $redis->errMsg", $redis->errCode);
        $ret = $redis->{$name}(...$arguments);
        if ($redis->errCode !== 0)
            throw new \Swango\Cache\RedisErrorException("Redis error: [$redis->errCode] $redis->errMsg", $redis->errCode);
        \Swango\Cache\RedisPool::push($redis);
        return $ret;
    }
}