<?php
namespace Swango\HttpServer;
/**
 *
 * @author fdream
 * @property string $encrypt_key
 * @property int $client_request_pack_timestamp
 */
abstract class Controller {
    const WITH_PAR = false, USE_SESSION = true, START_SESSION_LATER = false, USE_ROUTER_CACHE = true;
    protected $par_validate, $get_validate, $post_validate, $transformer;
    protected $swoole_http_request, $swoole_http_response;
    protected $par, $auth, $agent, $method, $action, $response_finished = false;
    protected $post, $get;
    private $client_time_difference;
    public $json_response_code, $json_response_enmsg, $json_response_cnmsg;
    /**
     *
     * @return \Controller
     */
    public static function getInstance(bool $create_if_not_exists = true): ?Controller {
        $ob = \SysContext::get('controller');
        if (isset($ob))
            return $ob;
        elseif ($create_if_not_exists) {
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
        $this->transformer = new Transformer\Ob();
        $this->auth = new \SplQueue();
        $this->agent = [];
        $this->config();
    }
    public function setSwooleHttpObject(\Swoole\Http\Request $request, \Swoole\Http\Response $response): self {
        if (! isset($request->post))
            $request->post = new \stdClass();
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
        if (isset($this->swoole_http_request->header['x-forwarded-for']))
            return current(explode(', ', $this->swoole_http_request->header['x-forwarded-for']));
        return $this->swoole_http_request->server['remote_addr'];
    }
    /**
     * 单位为毫秒
     *
     * @return int|NULL 正数表示服务器时间快，负数表示客户端时间快，0表示在误差允许范围内，NULL表示无法确定客户端时间
     */
    public function getClientTimeDifference(): ?int {
        if (isset($this->client_time_difference))
            if ($this->client_time_difference === false)
                return null;
            else
                return $this->client_time_difference;
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
        } else
            $diff = 0;
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
        $this->agent = $agent;
        return $this;
    }
    public function checkAuthority(): self {
        if (! empty($this->agent)) {
            $pass = false;
            $current_agent = \session::getAgent();
            foreach ($this->agent as $agent)
                if ($current_agent === $agent) {
                    $pass = true;
                    break;
                }
            if (! $pass)
                throw new \ExceptionToResponse\InsufficientPermissionsException();
        }
        if (! $this->auth->isEmpty()) {
            foreach ($this->auth as $authes) {
                if (count($authes) === 1 && current($authes) === Authorization::AUTH_NONE && \session::hasNoAuthAtAll())
                    return $this;
                $pass = true;
                foreach ($authes as $auth)
                    if (! \session::hasAuth($auth)) {
                        $pass = false;
                        break;
                    }
                if ($pass)
                    return $this;
            }
            throw new \ExceptionToResponse\InsufficientPermissionsException();
        }
        return $this;
    }
    protected function config(): void {}
    public function validate(): Controller {
        $this->par_validate->validate('URL', $this->par);
        $this->get_validate->validate('QUERY', $this->swoole_http_request->get);
        $this->post_validate->validate('POST', $this->swoole_http_request->post);
        $this->par_validate = $this->get_validate = $this->post_validate = null;
        $this->get = $this->swoole_http_request->get;
        $this->post = $this->swoole_http_request->post;
        return $this;
    }
    public function begin(): Controller {
        $ret = $this->handle(...$this->par);
        if (isset($ret) && is_string($ret) && strlen($ret) > 0)
            throw new \ExceptionToResponse('Alert message', $ret);
        return $this;
    }
    public function endRequest(?string $body = null): bool {
        if ($this->response_finished)
            return false;
        $this->swoole_http_response->end($body ?? '');
        $this->response_finished = true;
        if (! isset($this->json_response_code))
            $this->json_response_code = 200;
        return true;
    }
    protected static $cache_response_func;
    protected function echoJson(int $code, string $enmsg, ?string $cnmsg, $data): void {
        $this->json_response_code = $code;
        $this->json_response_enmsg = $enmsg;
        $this->json_response_cnmsg = $cnmsg;
        $data = [
            'code' => $code,
            'enmsg' => $enmsg,
            'cnmsg' => $cnmsg,
            'data' => $data
        ];
        $echo = str_replace('\\n', '\\' . 'n', \Json::encode($data));
        if (isset($this->encrypt_key)) {
            $this->swoole_http_response->header('Access-Control-Allow-Headers',
                'Rsa-Certificate-Id, Mango-Rsa-Cert, Mango-Request-Rand, Content-Type');
            $this->swoole_http_response->header('Mango-Response-Crypt', 'On');
            $echo = base64_encode(
                openssl_encrypt($echo, 'aes-128-cbc', $this->encrypt_key, OPENSSL_RAW_DATA, '1234567890123456'));
        } else {
            $this->swoole_http_response->header('Access-Control-Allow-Headers', 'Content-Type, Mango-Request-Rand');
            $this->swoole_http_response->header('Content-Type', 'application/json');
        }
        if (IS_DEV) {
            $this->swoole_http_response->header('Access-Control-Expose-Headers', 'Mango-Response-Crypt');
            $this->swoole_http_response->header('Access-Control-Allow-Origin', '*');
        }

        $this->endRequest($echo);
        if (isset($this->encrypt_key)) {
            $unique_request_id = \SysContext::get('unique_request_id');
            if (isset($unique_request_id)) {
                if (self::$cache_response_func === null)
                    self::$cache_response_func = function ($unique_request_id, $echo) {
                        \cache::select(2);
                        \cache::rPush($unique_request_id, $echo);
                        \cache::setTimeout($unique_request_id, 300);
                    };
                \Swlib\Archer::task(self::$cache_response_func,
                    [
                        $unique_request_id,
                        $echo
                    ]);
            }
        }
    }
    public function jsonResponse(?array $data = null, ?string $enmsg = 'ok', ?string $cnmsg = '成功', int $code = 200): void {
        if ($this->response_finished)
            return;
        if (isset($this->transformer))
            $this->transformer->transform($data);
        $this->echoJson($code, $enmsg, $cnmsg, $data);
    }
    public function jsonRedirect(string $url, string $enmsg = 'Redirect', ?string $cnmsg = null): void {
        if ($this->response_finished)
            return;
        $this->echoJson(302, $enmsg, $cnmsg, [
            'url' => $url
        ]);
    }
    public function jsonButton(string $msg, string $title, array ...$button): void {
        if ($this->response_finished)
            return;
        if (empty($button))
            $button[] = [
                'words' => '知道了',
                'action' => null,
                'style' => 0
            ];

        $this->echoJson(300, 'Alert', null,
            [
                'msg' => $msg,
                'title' => $title,
                'buttons' => $button
            ]);
    }
    public function getInputData(): string {
        return $this->swoole_http_request->rawContent();
    }
    public function setCookie($key, $value, $lifetime = 0, $path = '/'): void {
        if ($lifetime == 0)
            $expired = 0;
        else
            $expired = $this->swoole_http_request->server['request_time'] + $lifetime;
        $this->swoole_http_response->cookie($key, $value, $expired, $path);
    }
    public function deleteCookie($key, $path = '/'): void {
        $this->swoole_http_response->cookie($key, '', \Time\now() - 3600, $path);
    }
    protected function isWeiXin(): bool {
        return \session::getAgent() == \session::Agent_wx;
    }
    protected function isAlipay(): bool {
        return \session::getAgent() == \session::Agent_ali;
    }
    protected function getAction($classname): string {
        return substr($classname, strpos($classname, "\\") + 1);
    }
    protected function redirect(string $path, bool $visible = false) {
        if ($this->response_finished)
            return false;
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
        if ($this->method === 'GET')
            $this->swoole_http_response->end(
                '<html><head><title>404 Not Found</title></head><body bgcolor="white"><center><h1>404 Not Found</h1></center><hr><center>Tengine</center></body></html>');
        else
            $this->swoole_http_response->end();
        $this->json_response_code = 404;
        $this->response_finished = true;
    }
}