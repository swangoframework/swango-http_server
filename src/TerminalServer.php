<?php
namespace Swango\HttpServer;
class TerminalServer {
    public static $log_queue_redis_key = 'http_server_log_host_queue';
    private $status, $gc_collect_cycles_result, $clear_cache_result;
    private $fd_table, $switch_log_request_1, $switch_log_request_2, $notify_log_queue_atomic;
    public function __construct(\Swoole\Server\Port $port) {
        // $port->on('connect', [
        // $this,
        // 'onConnect'
        // ]);
        $port->on('receive', [
            $this,
            'onReceive'
        ]);
        // $port->on('close', [
        // $this,
        // 'onClose'
        // ]);
        $port->set([
            'open_eof_split' => true,
            'package_eof' => "\r\n"
        ]);
        $this->notify_log_queue_atomic = new \Swoole\Atomic(0);
        $this->switch_log_request_1 = new \Swoole\Atomic(0);
        $this->switch_log_request_2 = new \Swoole\Atomic(0);
        $this->fd_table = new \Swoole\Table(1024);
        $this->fd_table->column('worker_id', \Swoole\Table::TYPE_INT, 1);
        $this->fd_table->column('switch', \Swoole\Table::TYPE_INT, 1);
        $this->fd_table->create();
    }
    public function onConnect(\Swoole\Server $server, int $fd, int $reactor_id): void {
        $this->fd_table->set($fd,
            [
                'worker_id' => $server->worker_id,
                'switch' => 0
            ]);
    }
    public function onReceive(\Swoole\Server $server, int $fd, int $reactor_id, string $data): void {
        $this->fd_table->set($fd,
            [
                'worker_id' => $server->worker_id,
                'switch' => 0
            ]);
        $data = substr($data, 0, - 2);
        $cmds = explode("\x1E", $data);
        switch ($cmds[0]) {
            case 'show' :
                if ($cmds[1] === 'request') {
                    $this->fd_table->set($fd, [
                        'switch' => 1
                    ]);
                    $this->switch_log_request_1->add();
                } elseif ($cmds[1] === 'request-with-response' || $cmds[1] === 'rr') {
                    $this->fd_table->set($fd, [
                        'switch' => 2
                    ]);
                    $this->switch_log_request_2->add();
                } else {
                    $server->send($fd, "Invalid param:{$cmds[1]}\r\n");
                    $this->close($fd);
                }
                break;
            case 'status' :
                $id = \Swango\HttpServer::getWorkerId();
                $this->status[$fd] = new \stdClass();
                $this->status[$fd]->worker_request_count = [
                    $id => \Swango\HttpServer::getWorker()->worker_http_request_counter
                ];
                $this->status[$fd]->worker_master_db_count = [
                    $id => \Swango\Db\Pool\master::getWorkerCount()
                ];
                $this->status[$fd]->worker_slave_db_count = [
                    $id => \Swango\Db\Pool\slave::getWorkerCount()
                ];
                $this->status[$fd]->worker_memory_usage = [
                    $id => memory_get_usage()
                ];
                $this->status[$fd]->worker_memory_peak_usage = [
                    $id => memory_get_peak_usage()
                ];
                $stats = \Swoole\Coroutine::stats();
                $this->status[$fd]->coroutine_num = [
                    $id => $stats['coroutine_num']
                ];
                $this->status[$fd]->coroutine_peak_num = [
                    $id => $stats['coroutine_peak_num']
                ];
                $this->status[$fd]->context_size = [
                    $id => \SysContext::getSize()
                ];
                $stats = $server->stats();
                $server->send($fd, "[General status]\r\n");
                $server->send($fd, 'Start time:         ' . date('Y-m-d H:i:s', $stats['start_time']) . "\r\n");
                $server->send($fd, 'Status time:        ' . date('Y-m-d H:i:s') . "\r\n");
                $server->send($fd, "Tcp connection num: {$stats['connection_num']}\r\n");
                $server->send($fd, "Tcp accept count:   {$stats['accept_count']}\r\n");
                $server->send($fd, "Tcp close count:    {$stats['close_count']}\r\n");
                $server->send($fd, 'Http resquest count:' . \Swango\HttpServer::getTotalHttpRequest() . "\r\n");
                $server->send($fd, "[Model cache (Swoole\\Table) memory size(kb)]\r\n");

                $data = \Swango\Model\LocalCache::getAllInstanceSizes();
                if (empty($data)) {
                    $server->send($fd, "*No model cache created\r\n");
                } else {
                    foreach ($data as $key=>$memorySize) {
                        $s = $key . ':';
                        $l = strlen($key);
                        if ($l < 19)
                            $s .= str_repeat(' ', 19 - $l);
                        $server->send($fd, $s . sprintf("%.2f\r\n", $memorySize / 1024));
                    }
                    $server->send($fd, sprintf("*Total:             %.2f\r\n", array_sum($data) / 1024));
                }

                $server->send($fd, "[Each worker status (status worker:{$id})]\r\n");
                for($dst_worker_id = 0; $dst_worker_id < \Swango\Environment::getServiceConfig()->worker_num; ++ $dst_worker_id)
                    @$server->sendMessage(pack('nN', 2, $fd), $dst_worker_id);
                break;
            case 'gcmem' :
                $result = gc_collect_cycles();
                $this->gc_collect_cycles_result[$fd] = [
                    \Swango\HttpServer::getWorkerId() => $result
                ];
                for($dst_worker_id = 0; $dst_worker_id < \Swango\Environment::getServiceConfig()->worker_num; ++ $dst_worker_id)
                    @$server->sendMessage(pack('nN', 5, $fd), $dst_worker_id);
                // 各个进程可能先一步执行完，所以这里也要判断一下
                if (count($this->gc_collect_cycles_result[$fd]) === \Swango\Environment::getServiceConfig()->worker_num) {
                    ksort($this->gc_collect_cycles_result[$fd]);
                    $server->send($fd,
                        'Collect cycles: ' . implode(' ', $this->gc_collect_cycles_result[$fd]) . ' (' .
                             array_sum($this->gc_collect_cycles_result[$fd]) . ")\r\n");
                    unset($this->gc_collect_cycles_result[$fd]);
                    $this->close($fd);
                }
                break;
            case 'clearcache' :
                if ($cmds[1] === 'app') {
                    for($dst_worker_id = 0; $dst_worker_id < \Swango\Environment::getServiceConfig()->worker_num; ++ $dst_worker_id)
                        @$server->sendMessage(pack('nN', 6, $fd), $dst_worker_id);
                    if (class_exists('\\Controller\\app\\GET', false)) {
                        $result = \Controller\app\GET::clearCache();
                    } else {
                        $result = 0;
                    }
                    $this->clear_cache_result[$fd] = [
                        \Swango\HttpServer::getWorkerId() => $result
                    ];
                    // 各个进程可能先一步执行完，所以这里也要判断一下
                    if (count($this->clear_cache_result[$fd]) === \Swango\Environment::getServiceConfig()->worker_num) {
                        ksort($this->clear_cache_result[$fd]);
                        $total_mem = array_sum($this->clear_cache_result[$fd]);
                        array_walk($this->clear_cache_result[$fd],
                            function (&$value, $key) {
                                $value = sprintf('%.2f', $value / 1024);
                            });
                        $total_mem = sprintf('%.2f', $total_mem / 1024);
                        $server->send($fd,
                            'Clear memory(kb): ' . implode(' ', $this->clear_cache_result[$fd]) . " ($total_mem)\r\n");
                        unset($this->clear_cache_result[$fd]);
                        $this->close($fd);
                    }
                } else {
                    $server->send($fd, "Invalid param:{$cmds[1]}\r\n");
                    $this->close($fd);
                }
                break;
            default :
                $this->close($fd);
        }
    }
    public function onClose(\Swoole\Server $server, ?int $fd, ?int $reactor_id = null): void {
        $arr = $this->fd_table->get($fd);
        if ($arr) {
            if ($arr['switch'] === 1) {
                $this->switch_log_request_1->sub();
            } elseif ($arr['switch'] === 2) {
                $this->switch_log_request_2->sub();
            }
            $this->fd_table->del($fd);
        }
        $this->notifyLogQueue(true);
    }
    public function onPipeMessage(\Swoole\Server $server, int $src_worker_id, $message) {
        $arr = unpack('Nfd/ncmd', substr($message, 0, 6));
        $fd = $arr['fd'];
        $cmd = $arr['cmd'];
        $message = substr($message, 6);
        switch ($cmd) {
            case 1 :
                if (! $server->send($fd, $message . "\r\n")) {
                    $this->onClose($server, $fd);
                }
                break;
            case 2 :
                $this->close($fd);
                break;
            case 3 :
                [
                    $this->status[$fd]->worker_request_count[$src_worker_id],
                    $this->status[$fd]->worker_master_db_count[$src_worker_id],
                    $this->status[$fd]->worker_slave_db_count[$src_worker_id],
                    $this->status[$fd]->worker_memory_usage[$src_worker_id],
                    $this->status[$fd]->worker_memory_peak_usage[$src_worker_id],
                    $this->status[$fd]->coroutine_num[$src_worker_id],
                    $this->status[$fd]->coroutine_peak_num[$src_worker_id],
                    $this->status[$fd]->context_size[$src_worker_id]
                ] = explode('-', $message);
                if (count($this->status[$fd]->worker_request_count) ===
                     \Swango\Environment::getServiceConfig()->worker_num) {
                    ksort($this->status[$fd]->worker_request_count);
                    ksort($this->status[$fd]->worker_master_db_count);
                    ksort($this->status[$fd]->worker_slave_db_count);
                    ksort($this->status[$fd]->worker_memory_usage);
                    ksort($this->status[$fd]->worker_memory_peak_usage);
                    ksort($this->status[$fd]->coroutine_num);
                    ksort($this->status[$fd]->coroutine_peak_num);
                    ksort($this->status[$fd]->context_size);
                    $server->send($fd,
                        'Running requests:   ' . implode(' ', $this->status[$fd]->worker_request_count) . ' (' .
                             array_sum($this->status[$fd]->worker_request_count) . ")\r\n");
                    $server->send($fd,
                        'Master connections: ' . implode(' ', $this->status[$fd]->worker_master_db_count) . ' (' .
                             array_sum($this->status[$fd]->worker_master_db_count) . ")\r\n");
                    $server->send($fd,
                        'Slave connections:  ' . implode(' ', $this->status[$fd]->worker_slave_db_count) . ' (' .
                             array_sum($this->status[$fd]->worker_slave_db_count) . ")\r\n");
                    $total_mem = array_sum($this->status[$fd]->worker_memory_usage);
                    array_walk($this->status[$fd]->worker_memory_usage,
                        function (&$value, $key) {
                            $value = sprintf('%.2f', $value / 1024);
                        });
                    $total_mem = sprintf('%.2f', $total_mem / 1024);
                    $server->send($fd,
                        'Memory usage(kb):   ' . implode(' ', $this->status[$fd]->worker_memory_usage) .
                             " ($total_mem)\r\n");
                    $total_mem = array_sum($this->status[$fd]->worker_memory_peak_usage);
                    array_walk($this->status[$fd]->worker_memory_peak_usage,
                        function (&$value, $key) {
                            $value = sprintf('%.2f', $value / 1024);
                        });
                    $total_mem = sprintf('%.2f', $total_mem / 1024);
                    $server->send($fd,
                        'Mem peak usage(kb): ' . implode(' ', $this->status[$fd]->worker_memory_peak_usage) .
                             " ($total_mem)\r\n");
                    $server->send($fd,
                        'Coroutine num:      ' . implode(' ', $this->status[$fd]->coroutine_num) . ' (' .
                             array_sum($this->status[$fd]->coroutine_num) . ")\r\n");
                    $server->send($fd,
                        'Coroutine peak num: ' . implode(' ', $this->status[$fd]->coroutine_peak_num) . ' (' .
                             array_sum($this->status[$fd]->coroutine_peak_num) . ")\r\n");
                    $server->send($fd,
                        'Context size:       ' . implode(' ', $this->status[$fd]->context_size) . ' (' .
                             array_sum($this->status[$fd]->context_size) . ")\r\n");
                    unset($this->status[$fd]);
                    $this->close($fd);
                }
                break;
            case 4 :
                $this->gc_collect_cycles_result[$fd][$src_worker_id] = (int)$message;
                if (count($this->gc_collect_cycles_result[$fd]) === \Swango\Environment::getServiceConfig()->worker_num) {
                    ksort($this->gc_collect_cycles_result[$fd]);
                    $server->send($fd,
                        'Collect cycles: ' . implode(' ', $this->gc_collect_cycles_result[$fd]) . ' (' .
                             array_sum($this->gc_collect_cycles_result[$fd]) . ")\r\n");
                    unset($this->gc_collect_cycles_result[$fd]);
                    $this->close($fd);
                }
                break;
            case 5 :
                $this->clear_cache_result[$fd][$src_worker_id] = (int)$message;
                if (count($this->clear_cache_result[$fd]) === \Swango\Environment::getServiceConfig()->worker_num) {
                    ksort($this->clear_cache_result[$fd]);
                    $total_mem = array_sum($this->clear_cache_result[$fd]);
                    array_walk($this->clear_cache_result[$fd],
                        function (&$value, $key) {
                            if ($value === 0) {
                                $value = '0.00';
                            } else {
                                $value = sprintf('%.2f', $value / 1024);
                            }
                        });
                    $total_mem = sprintf('%.2f', $total_mem / 1024);
                    $server->send($fd,
                        'Clear memory(kb): ' . implode(' ', $this->clear_cache_result[$fd]) . " ($total_mem)\r\n");
                    unset($this->clear_cache_result[$fd]);
                    $this->close($fd);
                }
                break;
        }
    }
    public function sendPipMessageToTerminalWorker(\Swoole\Server $worker, int $fd, int $cmd, ?string $data = ''): bool {
        $dst_worker_id = $this->fd_table->get($fd, 'worker_id');
        if ($dst_worker_id === false) {
            return false;
        }
        return $worker->sendMessage(pack('nNn', 1, $fd, $cmd) . $data, $dst_worker_id);
    }
    public function close(int $fd): bool {
        $worker = \Swango\HttpServer::getWorker();
        $worker_id = $this->fd_table->get($fd, 'worker_id');
        if ($worker->worker_id === $worker_id) {
            if ($worker->close($fd)) {
                $this->onClose($worker, $fd);
                return true;
            } else {
                return false;
            }
        }
        return $this->sendPipMessageToTerminalWorker($worker, $fd, 2);
    }
    public function send(string $data, int $switch): bool {
        $worker = \Swango\HttpServer::getWorker();
        $result = false;
        foreach ($this->fd_table as $fd=>$row) {
            if ($row['switch'] === $switch) {
                if ($row['worker_id'] === $worker->worker_id) {
                    if (! $worker->send($fd, $data . "\r\n")) {
                        $this->onClose($worker, $fd);
                    }
                } else {
                    $result = $this->sendPipMessageToTerminalWorker($worker, $fd, 1, $data);
                }
            }
        }
        return $result;
    }
    public function getRequestLogSwitchStatus(int $switch): bool {
        $this->notifyLogQueue();
        if ($switch === 1 && $this->switch_log_request_1->get() > 0) {
            return true;
        } elseif ($switch === 2 && $this->switch_log_request_2->get() > 0) {
            return true;
        }
        return false;
    }
    public function notifyLogQueue(bool $force = false) {
        if ($force || $this->notify_log_queue_atomic->get() === 0 && $this->notify_log_queue_atomic->add() === 1) {
            $redis = \Swango\Cache\RedisPool::pop();
            $redis->select(1);
            $redis->lPush(self::$log_queue_redis_key, \Swango\Environment::getServiceConfig()->local_ip);
            \Swango\Cache\RedisPool::push($redis);
        }
    }
}