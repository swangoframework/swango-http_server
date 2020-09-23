<?php
namespace Swango\HttpServer;
class Handler {
    public static function start(\Swoole\Http\Request $request, \Swoole\Http\Response $response): array {
        if (! isset($request->get)) {
            \SysContext::set('request_get', new \stdClass());
        } else {
            \SysContext::set('request_get', (object)$request->get);
        }
        try {
            $router = Router::getInstance($request);
            if ($router->getMethod() === 'OPTIONS') {
                $response->header('Allow', 'OPTIONS, GET, HEAD, POST');
                $response->header('Access-Control-Allow-Methods', 'OPTIONS, GET, HEAD, POST');
                $response->header('Access-Control-Allow-Headers',
                    'Rsa-Certificate-Id, Mango-Rsa-Cert, Mango-Request-Rand, Content-Type');
                $response->header('Access-Control-Expose-Headers', 'Mango-Response-Crypt');
                if (IS_DEV) {
                    $response->header('Access-Control-Allow-Origin', '*');
                }
                $response->end('');
                return [
                    200,
                    'ok',
                    null
                ];
            }
            $controller = $router->getController($response);
            if ($controller instanceof Controller\StaticResourceController) {
                $controller->begin()->endRequest();
            } else {
                if ($router->isWebhook()) {
                    \session::startForWebhook();
                } else {
                    if ('POST' === $router->getMethod()) {
                        $session_started = self::parseRequest($request, $response, $controller);
                        if (null === $session_started) {
                            return [
                                200,
                                'replay request',
                                'response cache found'
                            ];
                        }
                    } else {
                        $session_started = false;
                    }
                    if (! $session_started && $controller::USE_SESSION && ! $controller::START_SESSION_LATER) {
                        \session::start($request, $response);
                    }
                }
                \cache::select(1);
                $controller->checkAuthority()->validate()->begin();
                if ('GET' !== $router->getMethod()) {
                    $controller->jsonResponse();
                } else {
                    $controller->endRequest();
                }
            }
            return [
                $controller->json_response_code,
                $controller->json_response_enmsg,
                $controller->json_response_cnmsg
            ];
        } catch (\ExceptionToResponse\ExceptionToResponseInterface $e) {
            $code = $e->getCode();
            $enmsg = $e->getMessage();
            $cnmsg = $e->getCnMsg();
            $data = $e->getData();
        } catch (\ApiErrorException $e) {
            $code = 500;
            $enmsg = 'Third party service error';
            $cnmsg = $e::supplier . '发生错误，有可能正在维护';
            $data = null;
        } catch (\RuntimeException $e) {
            $code = 500;
            $enmsg = 'Unexpected system error';
            $cnmsg = '此服务维护中，暂时不可用';
            $data = null;
        } catch (\Exception $e) {
            $code = method_exists($e, 'getSwangoCode') ? $e->getSwangoCode() : 500;
            $enmsg = 'Unexpected system error';
            $data = null;
            // 死锁
            if ($e instanceof \Swango\Db\Exception\QueryErrorException && $e->errno === 1213) {
                $cnmsg = '当前使用该服务的人数较多，请稍后重试';
            } elseif (method_exists($e, 'getSwangoCnMsg')) {
                $enmsg = $e->getMessage();
                $cnmsg = $e->getSwangoCnMsg();
            } else {
                $cnmsg = '服务器开小差了，请稍后重试';
            }
        } catch (\Throwable $e) {
            $code = 500;
            $enmsg = 'System fatal error';
            $cnmsg = '服务器出现内部错误，请稍后重试';
            $data = null;
        }
        if (isset($router) && 'GET' === $router->getMethod() &&
            \session::getAgentMap()->echoErrorMsgWhenMethodGet(\session::getAgent())) {
            $response->header('Content-Type', 'text/html; charset=UTF-8');
            $response->end($cnmsg);
        } elseif (isset($controller)) {
            $controller->jsonResponse($data, $enmsg, $cnmsg, $code);
        } else {
            $response->header('Content-Type', 'application/json');
            $response->end(str_replace([
                '\\n',
                '\\r'
            ], [
                '\\' . 'n',
                '\\' . 'r'
            ], \Json::encode([
                'code' => $code,
                'enmsg' => $enmsg,
                'cnmsg' => $cnmsg,
                'data' => $data
            ])));
        }
        $err_msg = $e->getMessage();
        if ($e instanceof \Swango\Model\Exception\ModelNotFoundException) {
            $err_msg .= '(' . \Json::encode($e->getIndex()) . ')';
        }
        \FileLog::logThrowable($e, \Swango\Environment::getDir()->log . 'error/',
            sprintf('%s : %s | %s | %s | %s | ', $request->header['x-forwarded-for'] ?? $request->server['remote_addr'],
                $err_msg, $cnmsg, ($request->header['host'] ?? '') . $request->server['request_uri'] .
                (isset($request->server['query_string']) ? '?' . $request->server['query_string'] : ''),
                \SysContext::has('request_post') ? \Json::encode(\SysContext::get('request_post')) : ''));
        if (isset($controller)) {
            $controller->rollbackTransaction();
        }
        return [
            $code,
            $enmsg,
            $cnmsg
        ];
    }
    /**
     *
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @param Controller $controller
     * @return bool|NULL 是否在本函数中开启了Session，若为null表示触发了重放请求缓存
     * @throws \ExceptionToResponse
     * @throws \ExceptionToResponse\TimeTooDifferentException
     * @throws \ExceptionToResponse\DuplicatedRandException
     * @throws \Exception
     */
    private static function parseRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response, Controller $controller): ?bool {
        $inputcontent = $request->rawContent();
        if ($inputcontent) {
            $server_info = $request->header;
            $flag = (array_key_exists('rsa-certificate-id', $server_info) &&
                    is_numeric($server_info['rsa-certificate-id'])) ||
                (array_key_exists('mango-rsa-cert', $server_info) && is_numeric($server_info['mango-rsa-cert']));
            if ($flag) {
                if (array_key_exists('mango-rsa-cert', $server_info) && is_numeric($server_info['mango-rsa-cert'])) {
                    $index = (int)$server_info['mango-rsa-cert'];
                    $crypt_type = 2;
                } else {
                    $index = (int)$server_info['rsa-certificate-id'];
                    $crypt_type = 1;
                }
                if (strlen($inputcontent) < 350) {
                    static $certs = [];
                    if (! array_key_exists($index, $certs)) {
                        $certname = \Swango\Environment::getDir()->data . 'cert/rsa_private_key_' . $index . '.pem';
                        if (! file_exists($certname)) {
                            throw new \Exception('Invalid rsa certificate id: ' . $index);
                        }
                        $key = include $certname;
                        mangoParseRequest_SetPrivateKey($index, $key);
                        $certs[$index] = null;
                    }
                    if ($crypt_type === 1) {
                        $data = mangoParseRequest($inputcontent, $index, true);
                    } else {
                        $data = mangoParseRequestRaw($inputcontent, $index, true);
                    }
                } else {
                    $data = \Swango\HttpServer::getWorker()->taskwait(pack('CC', $crypt_type, $index) . $inputcontent,
                        20);
                    if ($data === false) {
                        $data = -5;
                    } elseif (! is_int($data)) {
                        try {
                            $data = \Json::decodeAsObject($data);
                        } catch (\JsonDecodeFailException $e) {
                            $data = -4;
                        }
                    }
                }
                if (is_int($data)) {
                    if ($crypt_type === 2) {
                        $inputcontent = base64_encode($inputcontent);
                    }
                    $fp = fopen(\Swango\Environment::getDir()->log . 'http_server/unrecognizable_data_' .
                        date('Y-m-d') . '.log', 'a');
                    fwrite($fp, date('-------------[H:i:s]-------------') . $request->header['host'] .
                        $request->server['request_uri'] . " ($crypt_type)\n{$inputcontent}\n\n");
                    fclose($fp);
                    throw new \ExceptionToResponse('Unrecognizable data', '报文无法识别，错误代码' . $data);
                }
                unset($inputcontent);
            } else {
                try {
                    $data = \Json::decodeAsObject($inputcontent);
                    unset($inputcontent);
                } catch (\JsonDecodeFailException $e) {
                    throw new \ExceptionToResponse('Unrecognizable data', '报文无法识别');
                }
            }
            if ($flag) {
                if (! property_exists($data, 'rand') || ! is_string($data->rand) || strlen($data->rand) != 16) {
                    throw new \ExceptionToResponse('Unrecognizable data', '报文无法识别');
                }
                $controller->setEncryptKey($data->rand);
            }
            if (! property_exists($data, 'data')) {
                $data->data = new \stdClass();
            }
            // throw new \ExceptionToResponse('Unrecognizable data', '报文无法识别');
            if (property_exists($data, 'sid')) {
                $sid = $data->sid;
                if ((isset($sid) && ! preg_match('/[a-zA-Z0-9]{' . \session::SESSION_ID_LENGTH . '}$/', $sid))) {
                    throw new \ExceptionToResponse('Unrecognizable data', '报文无法识别');
                }
            } else {
                $sid = null;
            }
            if (! isset($data->ua)) {
                throw new \ExceptionToResponse('Unrecognizable data', '报文无法识别');
            }
            if (isset($data->v) && preg_match('/[a-zA-Z0-9\.]{1,16}$/', $data->v)) {
                \SysContext::set('client_version', (string)$data->v);
            } else {
                throw new \ExceptionToResponse('Unrecognizable data', '报文无法识别');
            }
            \session::getAgentMap()->getAgentId($data->ua);
            if ($flag) {
                if (! property_exists($data, 'timestamp') || abs($data->timestamp / 1000 - \Time\now()) > 1200 * 3600) {
                    throw new \ExceptionToResponse\TimeTooDifferentException();
                }
                $controller->client_request_pack_timestamp = (int)$data->timestamp;
                $unique_request_id = '';
                if (isset($sid)) {
                    $unique_request_id .= $sid . '_';
                }
                $unique_request_id .= $data->rand . '_' . $data->timestamp;
                $key = 'rand_lock_' . $unique_request_id;
                \SysContext::set('unique_request_id', $unique_request_id);
                if (\cache::exists($key)) {
                    \cache::select(2);
                    try {
                        $response_string_arr = \cache::blPop($unique_request_id, 1);
                        // 为防止多次重放，再放回队列
                        if (isset($response_string_arr) && is_array($response_string_arr) &&
                            count($response_string_arr) === 2) {
                            $response_string = $response_string_arr[1];
                            \cache::rPush($unique_request_id, $response_string);
                            \cache::setTimeout($unique_request_id, 300);
                        } else {
                            $response_string = null;
                        }
                        \cache::select(1);
                    } catch (\Swango\Cache\RedisErrorException $e) {
                        \cache::select(1);
                        throw $e;
                    }
                    if (isset($response_string) && is_string($response_string)) {
                        $response->header('Access-Control-Allow-Headers',
                            'Rsa-Certificate-Id, Mango-Rsa-Cert, Mango-Request-Rand, Content-Type');
                        $response->header('Mango-Response-Crypt', 'On');
                        if (IS_DEV) {
                            $response->header('Access-Control-Expose-Headers', 'Mango-Response-Crypt');
                            $response->header('Access-Control-Allow-Origin', '*');
                        }
                        $controller->endRequest($response_string);
                        return null;
                    }
                    throw new \ExceptionToResponse\DuplicatedRandException($data->rand . '_' . $data->timestamp);
                }
                \cache::setex($key, isset($sid) ? (12 * 3600 + 1) : 600, 1);
            }
            \SysContext::set('request_post', is_array($data->data) ? new \stdClass() : $data->data);
            if ($controller::USE_SESSION && ! $controller::START_SESSION_LATER && property_exists($data, 'sid')) {
                \session::start($request, $response, $sid, $data->ua);
                return true;
            } else {
                return false;
            }
        } else {
            \SysContext::set('request_post', new \stdClass());
        }
        return false;
    }
    public static function end() {
        \session::end();
        try {
            Router::getInstance()->detachSwooleRequest();
        } catch (\Throwable $e) {
        }
        $controller = Controller::getInstance(false);
        if (isset($controller)) {
            $controller->detachSwooleObject();
        }
    }
}