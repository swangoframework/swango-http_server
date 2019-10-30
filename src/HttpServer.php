<?php
namespace Swango;
use Swango\Environment;

class HttpServer {
    protected static $worker, $worker_id, $terminal_server, $http_request_counter, $max_coroutine, $is_stopping = false;
    public static function getWorkerId(): ?int {
        return self::$worker_id;
    }
    public static function getWorker(): \Swoole\Http\Server {
        return self::$worker;
    }
    public static function getTotalHttpRequest(): int {
        return self::$http_request_counter->get();
    }
    public static function getMaxCoroutine(): int {
        return self::$max_coroutine;
    }
    public static function isStopping(): bool {
        return self::$is_stopping;
    }
    protected $server, $daemonize, $callback;
    protected $swoole_server_config = [
        'reactor_num' => 4, // reactor thread num
        'worker_num' => 8, // worker process num
        'task_worker_num' => 8,
        'task_ipc_mode' => 1, // 3为使用消息队列通信，争抢模式，无法使用定向投递
        'task_max_request' => 5000, // task进程处理200个请求后自动退出，防止内存溢出
        'backlog' => 128, // 最多同时有多少个等待accept的连接
        'max_request' => 0, // worker永不退出
        'reload_async' => true,
        'http_parse_post' => false, // 不自动解析POST包体
        'http_compression' => false, // 不自动压缩响应值
        'max_wait_time' => 30 // 重载后旧进程最大存活时间
    ];
    public function __construct() {
        $this->callback = [
            'Start' => [
                $this,
                'onStart'
            ],
            'ManagerStart' => [
                $this,
                'onManagerStart'
            ],
            'WorkerStart' => [
                $this,
                'onAllWorkerStart'
            ],
            'WorkerError' => [
                $this,
                'onWorkerError'
            ],
            'WorkerStop' => [
                $this,
                'onWorkerStop'
            ],
            'WorkerExit' => [
                $this,
                'onWorkerExit'
            ],
            'Task' => [
                $this,
                'onTask'
            ],
            'Finish' => [
                $this,
                'onFinish'
            ],
            'PipeMessage' => [
                $this,
                'onPipeMessage'
            ],
            'Request' => [
                $this,
                'onRequest'
            ]
        ];
        $this->swoole_server_config['pid_file'] = Environment::getDir()->base . Environment::getName() . '.pid';
    }
    protected function loadConfig(): void {
        $daemon_config = Environment::getServiceConfig();
        $this->swoole_server_config['reactor_num'] = $daemon_config->reactor_num;
        $this->swoole_server_config['worker_num'] = $daemon_config->worker_num;
        $this->swoole_server_config['task_worker_num'] = $daemon_config->task_worker_num;
        $this->swoole_server_config['task_max_request'] = $daemon_config->task_max_request;
    }
    protected function createSwooleServer(): void {
        $daemon_config = Environment::getServiceConfig();
        $this->server = new \Swoole\Http\Server($daemon_config->http_server_host, $daemon_config->http_server_port);
        self::$terminal_server = new HttpServer\TerminalServer(
            $this->server->addListener($daemon_config->terminal_server_host, $daemon_config->terminal_server_port,
                SWOOLE_SOCK_TCP));
    }
    protected function bindCallBack(): void {
        foreach ($this->callback as $event=>$func)
            $this->server->on($event, $func);
    }
    protected function initBeforeStart(): void {
        mt_srand((int)(microtime(true) * 10000) * 100 + ip2long(Environment::getServiceConfig()->local_ip));
        define('SERVER_TEMP_ID', mt_rand(0, 4294967295));

        $this->swoole_server_config['dispatch_mode'] = 3;

        \Swoole\Runtime::enableCoroutine(true);

        \Swango\Db\Pool\master::init();
        \Swango\Db\Pool\slave::init();
        \Swango\Model\LocalCache::init();
        self::$http_request_counter = new \Swoole\Atomic\Long();
        self::$max_coroutine = array_key_exists('max_coroutine', $this->swoole_server_config) ? $this->swoole_server_config['max_coroutine'] : 3000;
    }
    public function start($daemonize = false): void {
        if ($this->getPid() !== null)
            exit("Already running\n");

        $this->swoole_server_config['log_file'] = Environment::getDir()->log . 'swoole.log';
        $this->daemonize = $daemonize;
        $this->loadConfig();
        $this->createSwooleServer();
        $this->bindCallBack();
        $this->initBeforeStart();
        $this->swoole_server_config['daemonize'] = $daemonize;
        $this->server->set($this->swoole_server_config);
        echo "Starting\n";
        $this->server->start();
    }
    public function getPid(): ?int {
        $pidfile = Environment::getDir()->base . Environment::getName() . '.pid';
        if (file_exists($pidfile)) {
            $pid = file_get_contents($pidfile);
            return $pid && @posix_kill($pid, 0) ? $pid : null;
        } else
            return null;
    }
    public function stop(): bool {
        $pid = $this->getPid();
        if ($pid === null) {
            echo "Not running\n";
            return false;
        }
        posix_kill($pid, SIGTERM);
        echo "Stopping\n";
        sleep(1);
        return true;
    }
    public function reload(): void {
        $pid = $this->getPid();
        if ($pid === null)
            exit("Not running\n");
        posix_kill($pid, SIGUSR1);
        exit("Reloading\n");
    }
    public function reloadTask(): void {
        $pid = $this->getPid();
        if ($pid === null)
            exit("Not running\n");
        posix_kill($pid, SIGUSR2);
        exit("Reloading task\n");
    }
    public function getStatus(): string {
        $pid = $this->getPid();
        if ($pid === null)
            return "Not running\n";
        return "Master pid: $pid \n";
    }
    public function talk(array $cmds, string $host = '127.0.0.1', ?int $port = null): void {
        echo $this->getStatus();
        go(
            function () use ($cmds, $host, $port) {
                $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
                $client->set(
                    [
                        'open_eof_check' => true,
                        'package_eof' => "\r\n",
                        'package_max_length' => 1024 * 1024 * 2
                    ]);
                if (! $client->connect($host, $port ?? Environment::getServiceConfig()->terminal_server_port, - 1))
                    exit("connect failed. Error: {$client->errCode}\n");
                $client->send(\Swoole\Serialize::pack($cmds, SWOOLE_FAST_PACK) . "\r\n");
                for($response = $client->recv(); $response; $response = $client->recv())
                    echo $response;
                $client->close();
            });
    }
    public function onStart(\Swoole\Server $server): void {
        @cli_set_process_title(Environment::getName() . ' master');
    }
    public function onManagerStart(\Swoole\Server $server): void {
        @cli_set_process_title(Environment::getName() . ' manager');
    }
    private function onTaskStart(\Swoole\Server $serv, $worker_id): void {
        define('SWANGO_WORKING_IN_TASK', true);
        Environment::getWorkingMode()->reset();
    }
    private function onWorkerStart(\Swoole\Server $serv, $worker_id): void {
        define('SWANGO_WORKING_IN_WORKER', true);
        Environment::getWorkingMode()->reset();
        $serv->worker_http_request_counter = 0;
        \Swango\Cache\RedisPool::initInWorker();
        if ($worker_id === 0) {
            new \Swango\Cache\InternelCmd();
            // 每隔15分钟进行全服务DbPool计数校对，因为如果有worker非正常退出的情况，会引起该计数错误
            $this->add_worker_count_to_atomic_timer = \swoole_timer_tick(900000,
                function (int $timer_id) use ($serv) {
                    \Swango\Db\Pool\master::addWorkerCountToAtomic(true);
                    \Swango\Db\Pool\slave::addWorkerCountToAtomic(true);
                    for($dst_worker_id = 1; $dst_worker_id < Environment::getServiceConfig()->worker_num; ++ $dst_worker_id)
                        @$serv->sendMessage(pack('n', 3), $dst_worker_id);
                });
        }
    }
    public function onAllWorkerStart(\Swoole\Server $serv, $worker_id): void {
        opcache_reset();
        mt_srand((int)(microtime(true) * 10000) * 100 + $worker_id);
        self::$worker = $serv;
        self::$worker_id = $worker_id;
        if ($worker_id < $this->swoole_server_config['worker_num']) {
            @cli_set_process_title(Environment::getName() . ' worker ' . $worker_id);
            $this->onWorkerStart($serv, $worker_id);
        } else {
            $this->onTaskStart($serv, $worker_id);
            @cli_set_process_title(Environment::getName() . ' task ' . $worker_id);
        }
    }
    private function recycle(): void {
        self::$is_stopping = true;
        if (self::getWorkerId() < $this->swoole_server_config['worker_num'])
            go('Swango\\Cache\\InternelCmd::stopLoop');
        $pool = \Gateway::getDbPool(\Gateway::MASTER_DB);
        if (isset($pool))
            $pool->clearQueueAndTimer();
        $pool = \Gateway::getDbPool(\Gateway::SLAVE_DB);
        if (isset($pool))
            $pool->clearQueueAndTimer();
        \Swango\Cache\RedisPool::clearQueue();
        if (isset($this->add_worker_count_to_atomic_timer)) {
            \swoole_timer_clear($this->add_worker_count_to_atomic_timer);
            unset($this->add_worker_count_to_atomic_timer);
        }
    }
    public function onWorkerError(\Swoole\Server $serv, $worker_id, $worker_pid, $exit_code): void {
        trigger_error("Worker {$worker_id} error.Pid: {$worker_pid}. Exit code:$exit_code");
    }
    private $stopping_process_title_set = false;
    public function onWorkerStop(\Swoole\Server $serv, $worker_id): void {
        if (! $this->stopping_process_title_set) {
            $this->stopping_process_title_set = true;
            @cli_set_process_title(Environment::getName() . ' worker ' . $worker_id . ' [stopping]');
        }
        $this->recycle();
    }
    public function onWorkerExit(\Swoole\Server $serv, $worker_id): void {
        if (! $this->stopping_process_title_set) {
            $this->stopping_process_title_set = true;
            @cli_set_process_title(Environment::getName() . ' worker ' . $worker_id . ' [stopping]');
        }
        $this->recycle();
    }
    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): void {
        ++ self::$worker->worker_http_request_counter;
        $count = self::$http_request_counter->add();

        $request_time_float = $request->server['request_time_float'];
        $request_time = (int)$request_time_float;
        $client_ip = $request->header['x-forwarded-for'] ?? $request->server['remote_addr'];
        $client_ip_int = ip2long($client_ip);
        $local_ip_right = ip2long(Environment::getServiceConfig()->local_ip) % 0x10000;
        $request_id = sprintf('%08x-%04x-4%03x-%x%03x-%07x%05x', $client_ip_int, $local_ip_right, mt_rand(0, 0xFFF),
            mt_rand(8, 0xB), mt_rand(0, 0xFFF), ((int)$request_time) >> 4, $count % 0x100000);
        \SysContext::set('request_id', $request_id);
        $response->header('X-Request-ID', $request_id);

        $micro_second = substr(sprintf('%.3f', $request_time_float - $request_time), 2);
        $request_string = date("[H:i:s.$micro_second]", $request_time) . self::$worker_id . "-{$count} " . $client_ip .
             ' ' . $request->server['request_method'] . ' ' . ($request->header['host'] ?? '') .
             $request->server['request_uri'] . (isset($request->server['query_string']) ? '?' .
             $request->server['query_string'] : '');

        if (self::$terminal_server->getRequestLogSwitchStatus(1))
            self::$terminal_server->send($request_string, 1);

        $user_id = null;
        try {
            [
                $code,
                $enmsg,
                $cnmsg
            ] = HttpServer\Handler::start($request, $response);
            $user_id = HttpServer\Authorization::getUidWithRole();

            HttpServer\Handler::end($request);
        } catch(\Swoole\ExitException $e) {
            trigger_error("Unexpected exit:{$e->getCode()} {$e->getMessage()}");
        } catch(\Throwable $e) {
            trigger_error("Unexpected throwable:{$e->getCode()} {$e->getMessage()} {$e->getTraceAsString()}");
        }
        -- self::$worker->worker_http_request_counter;

        $end_time = microtime(true);
        $response_string = sprintf("#$user_id (%s) %.3fms [$code]$enmsg", \session::getId(),
            ($end_time - $request_time_float) * 1000);
        if ($code !== 200 || $enmsg !== 'ok')
            $response_string .= ' ' . $cnmsg;

        if (self::$terminal_server->getRequestLogSwitchStatus(2))
            self::$terminal_server->send($request_string . ' ==> ' . $response_string, 2);

        HttpServer\Handler::end();
    }
    public function onPipeMessage(\Swoole\Server $server, int $src_worker_id, $message) {
        $cmd = unpack('n', substr($message, 0, 2))[1];
        switch ($cmd) {
            case 1 : // 需要交给 TerminalServer 处理
                self::$terminal_server->onPipeMessage($server, $src_worker_id, substr($message, 2));
                break;
            case 2 : // 需要回复进程状态
                $status = \Swoole\Coroutine::stats();
                $fd = unpack('N', substr($message, 2, 4))[1];
                self::$terminal_server->sendPipMessageToTerminalWorker($server, $fd, 3,
                    "$server->worker_http_request_counter-" . \Swango\Db\Pool\master::getWorkerCount() . '-' .
                         \Swango\Db\Pool\slave::getWorkerCount() . '-' . memory_get_usage() . '-' .
                         memory_get_peak_usage() . "-{$status['coroutine_num']}-{$status['coroutine_peak_num']}-" .
                         \SysContext::getSize());
                break;
            case 3 : // 正在进行DbPool计数校对
                \Swango\Db\Pool\master::addWorkerCountToAtomic();
                \Swango\Db\Pool\slave::addWorkerCountToAtomic();
                break;

            case 5 : // 执行 gc_collect_cycles() 并回复结果
                $result = gc_collect_cycles();
                $fd = unpack('N', substr($message, 2, 4))[1];
                self::$terminal_server->sendPipMessageToTerminalWorker($server, $fd, 4, $result);
                break;
            case 6 :
                if (class_exists('\\Controller\\app\\GET', false))
                    $result = \Controller\app\GET::clearCache();
                else
                    $result = 0;
                $fd = unpack('N', substr($message, 2, 4))[1];
                self::$terminal_server->sendPipMessageToTerminalWorker($server, $fd, 5, $result);
        }
    }
    public function onTask(\Swoole\Server $serv, int $task_id, int $src_worker_id, $data) {
        [
            'cmd' => $cmd,
            'index' => $index
        ] = unpack('Ccmd/Cindex', $data);
        if ($cmd === 1 || $cmd === 2) {
            static $certs = [];
            if (! array_key_exists($index, $certs)) {
                $certname = Environment::getDir()->data . 'cert/rsa_private_key_' . $index . '.pem';
                if (! file_exists($certname))
                    return - 3;
                $key = include $certname;
                mangoParseRequest_SetPrivateKey($index, $key);
                $certs[$index] = null;
            }
            if ($cmd === 1)
                return mangoParseRequest(substr($data, 2), $index, false);
            else
                return mangoParseRequestRaw(substr($data, 2), $index, false);
        }
    }
    public function onFinish(\Swoole\Server $serv, int $task_id, string $data) {}
}