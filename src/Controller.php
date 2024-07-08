<?php
namespace Swango\HttpServer;
/**
 *
 * @author fdream
 */
abstract class Controller {
    private static int $max_record_response_data_length;
    /**
     * response data will be stored in SysContext::get('response_data_to_log')
     * @param int $max_length
     * @return void
     */
    public static function setRecordResponseData(int $max_length): void {
        self::$max_record_response_data_length = $max_length;
    }
    const WITH_PAR = false, USE_SESSION = true, START_SESSION_LATER = false, USE_ROUTER_CACHE = true;
    protected ?Validator $par_validate, $get_validate, $post_validate;
    protected ?\Swoole\Http\Request $swoole_http_request;
    protected ?\Swoole\Http\Response $swoole_http_response;
    protected \SplQueue $auth;
    protected array $agent, $par, $action;
    protected string $method;
    protected bool $response_finished = false;
    protected object $post, $get;
    /**
     * @var null|int|bool
     */
    private $client_time_difference;
    public ?string $json_response_enmsg = null, $json_response_cnmsg = null;
    public ?int $json_response_code = null, $client_request_pack_timestamp = null;
    public ?string $encrypt_key = null;
    public static function getInstance(bool $create_if_not_exists = true): ?Controller {
        $ob = \SysContext::get('controller');
        if (isset($ob)) {
            return $ob;
        } elseif ($create_if_not_exists) {
            $ob = new static();
            \SysContext::set('controller', $ob);
            return $ob;
        }
        return null;
    }
    public static function getRequestAsString(): string {
        return self::getInstance()->swoole_http_request->getData();
    }
    protected function __construct() {
        $this->post_validate = new Validator\Ob('POST包体');
        $this->get_validate = new Validator\Ob('Query参数');
        $this->par_validate = new Validator\Ob('URL参数');
        $this->auth = new \SplQueue();
        $this->permit_agent = [];
        $this->config();
    }
    public function setSwooleHttpObject(\Swoole\Http\Request $request, \Swoole\Http\Response $response): self {
        $this->swoole_http_request = $request;
        $this->swoole_http_response = $response;
        return $this;
    }
    public function detachSwooleObject(): self {
        $this->swoole_http_request = null;
        $this->swoole_http_response = null;
        return $this;
    }
    public function getSwooleHttpRequest(): \Swoole\Http\Request {
        return $this->swoole_http_request;
    }
    public function getSwooleHttpResponse(): \Swoole\Http\Response {
        return $this->swoole_http_response;
    }
    public function beginTransaction(): bool {
        return \Gateway::beginTransaction();
    }
    public function submitTransaction(): bool {
        return \Gateway::submitTransaction();
    }
    public function rollbackTransaction(): bool {
        return \Gateway::rollbackTransaction();
    }
    public function getClientIp(): string {
        if (isset($this->swoole_http_request->header['x-forwarded-for'])) {
            return trim(current(explode(',', $this->swoole_http_request->header['x-forwarded-for'])));
        }
        return $this->swoole_http_request->server['remote_addr'];
    }
    /**
     * 单位为毫秒
     *
     * @return int|NULL 正数表示服务器时间快，负数表示客户端时间快，0表示在误差允许范围内，NULL表示无法确定客户端时间
     */
    public function getClientTimeDifference(): ?int {
        if (isset($this->client_time_difference)) {
            if ($this->client_time_difference === false) {
                return null;
            } else {
                return $this->client_time_difference;
            }
        }
        if (! isset($this->client_request_pack_timestamp) ||
            ! isset($this->swoole_http_request->server['request_time_float'])) {
            $this->client_time_difference = false;
            return null;
        }
        $request_timestamp = (int)($this->swoole_http_request->server['request_time_float'] * 1000);
        if ($this->client_request_pack_timestamp + 2000 < $request_timestamp) {
            // 服务器快
            $diff = $request_timestamp - $this->client_request_pack_timestamp - 2000;
        } elseif ($this->client_request_pack_timestamp - 1000 > $request_timestamp) {
            // 客户端快
            $diff = $request_timestamp - $this->client_request_pack_timestamp + 1000;
        } else {
            $diff = 0;
        }
        $this->client_time_difference = $diff;
        return $diff;
    }
    public function clientListening(): bool {
        return \Swango\HttpServer::getWorker()->exist($this->swoole_http_request->fd);
    }
    public function setPar(string ...$par): self {
        $this->par = $par;
        return $this;
    }
    public function setMethod(string $method): self {
        $this->method = $method;
        return $this;
    }
    public function setAction(array $action): self {
        $this->action = $action;
        return $this;
    }
    public function setEncryptKey(string $key): self {
        $this->encrypt_key = $key;
        return $this;
    }
    /**
     * 添加允许的权限集（参数之间是“且”的关系）
     *
     * @param int ...$auth
     * @return self
     */
    protected function addPermitCondition(int ...$auth): self {
        $this->auth->enqueue($auth);
        return $this;
    }
    /**
     * 设置允许的终端类型
     *
     * @param string ...$agent
     * @return self
     */
    protected function setPermitAgent(int ...$agent): self {
        $this->permit_agent = $agent;
        return $this;
    }
    public function checkAuthority(): self {
        if (! empty($this->permit_agent)) {
            $pass = false;
            $current_agent = \session::getAgent();
            foreach ($this->permit_agent as $agent)
                if ($current_agent === $agent) {
                    $pass = true;
                    break;
                }
            if (! $pass) {
                throw new \ExceptionToResponse\InsufficientPermissionsException();
            }
        }
        if (! $this->auth->isEmpty()) {
            foreach ($this->auth as $authes) {
                if (count($authes) === 1 && current($authes) === Authorization::AUTH_NONE &&
                    \session::hasNoAuthAtAll()) {
                    return $this;
                }
                $pass = true;
                foreach ($authes as $auth)
                    if (! \session::hasAuth($auth)) {
                        $pass = false;
                        break;
                    }
                if ($pass) {
                    return $this;
                }
            }
            throw new \ExceptionToResponse\InsufficientPermissionsException();
        }
        return $this;
    }
    protected function config(): void {
    }
    public function validate(): Controller {
        $get = \SysContext::get('request_get') ?? new \stdClass();
        $post = \SysContext::get('request_post') ?? new \stdClass();
        $this->par_validate->validate('URL', $this->par);
        $this->get_validate->validate('QUERY', $get);
        $this->post_validate->validate('POST', $post);
        $this->par_validate = $this->get_validate = $this->post_validate = null;
        $this->get = $get;
        $this->post = $post;
        return $this;
    }
    public function begin(): Controller {
        $ret = $this->handle(...$this->par);
        if (isset($ret) && is_string($ret) && strlen($ret) > 0) {
            throw new \ExceptionToResponse('Alert message', $ret);
        }
        return $this;
    }
    public function endRequest(?string $body = null): bool {
        if ($this->response_finished) {
            return false;
        }
        $this->swoole_http_response->end($body ?? '');
        $this->response_finished = true;
        if (! isset($this->json_response_code)) {
            $this->json_response_code = 200;
        }
        return true;
    }
    protected function echoJson(int $code, string $enmsg, ?string $cnmsg, mixed $data): void {
        $this->json_response_code = $code;
        $this->json_response_enmsg = $enmsg;
        $this->json_response_cnmsg = $cnmsg;

        if (isset(self::$max_record_response_data_length)) {
            $data = str_replace([
                '\\n',
                '\\r'
            ], [
                '\\' . 'n',
                '\\' . 'r'
            ], json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_IGNORE));
            $echo = sprintf('{"code":%d,"enmsg":%s,"cnmsg":%s,"data":%s}', $code, str_replace([
                '\\n',
                '\\r'
            ], [
                '\\' . 'n',
                '\\' . 'r'
            ], \Json::encode($enmsg)), str_replace([
                '\\n',
                '\\r'
            ], [
                '\\' . 'n',
                '\\' . 'r'
            ], \Json::encode($cnmsg)), $data);
            if (strlen($data) <= self::$max_record_response_data_length) {
                \SysContext::set('response_data_to_log', $data);
            }
        } else {
            $echo = str_replace([
                '\\n',
                '\\r'
            ], [
                '\\' . 'n',
                '\\' . 'r'
            ], json_encode([
                'code' => &$code,
                'enmsg' => &$enmsg,
                'cnmsg' => &$cnmsg,
                'data' => &$data
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_IGNORE));
        }
        if (isset($this->encrypt_key)) {
            if (strlen($echo) < 256) {
                $this->swoole_http_response->header('Mango-Response-Crypt', 'On');
                $echo = base64_encode(openssl_encrypt($echo, 'aes-128-cbc', $this->encrypt_key, OPENSSL_RAW_DATA,
                    '1234567890123456'));
                $this->endRequest($echo);
            } else {
                $this->swoole_http_response->detach();
                \Swango\HttpServer::getWorker()->taskwait(pack('CN', 16, $this->swoole_http_response->fd) .
                    $this->encrypt_key . $echo, 5);
                $this->response_finished = true;
                if (! isset($this->json_response_code)) {
                    $this->json_response_code = 200;
                }
            }
        } else {
            $this->swoole_http_response->header('Content-Type', 'application/json');
            $this->endRequest($echo);
        }
        if (isset($this->encrypt_key)) {
            $unique_request_id = \SysContext::get('unique_request_id');
            if (isset($unique_request_id)) {
                go(function () use ($unique_request_id, $echo) {
                    try {
                        \cache::select(2);
                        \cache::rPush($unique_request_id, $echo);
                        \cache::setTimeout($unique_request_id, 300);
                    } catch (\Throwable $e) {
                        \FileLog::logThrowable($e, \Swango\Environment::getDir()->log . 'error/', 'Redis replay cache');
                    }
                });
            }
        }
    }
    public function jsonResponse(?array $data = null, ?string $enmsg = 'ok', ?string $cnmsg = '成功',
                                 int    $code = 200): void {
        if ($this->response_finished) {
            return;
        }
        $this->echoJson($code, $enmsg, $cnmsg, $data);
    }
    public function jsonRedirect(string $url, string $enmsg = 'Redirect', ?string $cnmsg = null): void {
        if ($this->response_finished) {
            return;
        }
        $this->echoJson(302, $enmsg, $cnmsg, [
            'url' => $url
        ]);
    }
    public function jsonButton(string $msg, string $title, array ...$button): void {
        if ($this->response_finished) {
            return;
        }
        if (empty($button)) {
            $button[] = [
                'words' => '知道了',
                'action' => null,
                'style' => 0
            ];
        }
        $this->echoJson(300, 'Alert', null, [
            'msg' => $msg,
            'title' => $title,
            'buttons' => $button
        ]);
    }
    public function getInputData(): string {
        return $this->swoole_http_request->rawContent();
    }
    public function setCookie(string $key, string $value, int $lifetime = 0, string $path = '/'): void {
        if (0 === $lifetime) {
            $expired = 0;
        } else {
            $expired = $this->swoole_http_request->server['request_time'] + $lifetime;
        }
        $this->swoole_http_response->cookie($key, $value, $expired, $path);
    }
    public function deleteCookie(string $key, string $path = '/'): void {
        $this->swoole_http_response->cookie($key, '', \Time\now() - 3600, $path);
    }
    protected function getAction(string $classname): string {
        return substr($classname, strpos($classname, "\\") + 1);
    }
    protected function renderTemplate(string  $template, array $variable = [],
                                      ?string $content_type = 'text/html; charset=UTF-8',
                                      ?string $cache_control = 'no-store'): void {
        if ($this->response_finished) {
            return;
        }
        if (! file_exists($template)) {
            $this->E_404();
            return;
        }
        foreach ($variable as $k => &$v)
            ${$k} = $v;
        unset($v);
        ob_start();
        try {
            include $template;
            $content = ob_get_clean();
            if (false === $content) {
                throw new \Exception('Output buffering not active');
            }
            if (isset($content_type)) {
                $this->swoole_http_response->header('Content-Type', $content_type);
            }
            if (isset($cache_control)) {
                $this->swoole_http_response->header('Cache-Control', $cache_control);
            }
            $this->swoole_http_response->end($content);
            $this->response_finished = true;
            $this->json_response_code ??= 200;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->E_500();
            throw $e;
        }
    }
    protected function redirect(string $path, bool $visible = false): bool {
        if ($this->response_finished) {
            return false;
        }
        $this->json_response_code = 302;
        $this->json_response_enmsg = $path;
        if ($visible) {
            $this->swoole_http_response->header('Content-Type', 'text/html; charset=UTF-8');
            $this->swoole_http_response->header('Cache-Control', 'no-store');
            return $this->endRequest('<script type="text/javascript">window.location.href="' . $path . '";</script>');
        }
        $this->swoole_http_response->redirect($path);
        $this->response_finished = true;
        return true;
    }
    protected function E_404(): void {
        $this->swoole_http_response->status(404);
        $this->swoole_http_response->end('<html><head><title>404 Not Found</title></head><body bgcolor="white"><center><h1>404 Not Found</h1></center><hr><center>nginx</center></body></html>');
        $this->json_response_code = 404;
        $this->response_finished = true;
    }
    protected function E_500(): void {
        $this->swoole_http_response->status(500);
        $this->swoole_http_response->end('<html><head><title>500 Internal Server Error</title></head><body bgcolor="white"><center><h1>500 Internal Server Error</h1></center><hr><center>nginx</center></body></html>');
        $this->json_response_code = 500;
        $this->response_finished = true;
    }
}
