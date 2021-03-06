<?php
namespace Swango\HttpServer;
use Swango\Environment;
class Router {
    public static ?string $no_static_path = null;
    private static array $cache = [];
    public static function getInstance(?\Swoole\Http\Request $request = null): Router {
        $ob = \SysContext::get('router');
        if (isset($ob)) {
            return $ob;
        }
        if (! isset($request)) {
            throw new \Exception('Need to give \\Swoole\\Http\\Request');
        }
        $v = null;
        $get = \SysContext::get('request_get');
        if (isset($get) && isset($get->v) && is_numeric($get->v) && $get->v > 1 && $get->v < 100) {
            $v = (int)$get->v;
        }
        $uri = $request->server['request_uri'];
        if (preg_match("/\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\;|\'|\"|\`|\=|\\\|\|/",
            $uri)) {
            throw new \ExceptionToResponse\BadRequestException();
        }
        $ob = new self($uri, $request->server['request_method'], $v);
        $ob->request = $request;
        $ob->host = $request->header['host'] ?? null;
        \SysContext::set('router', $ob);
        return $ob;
    }
    public static function exists(): bool {
        return \SysContext::has('router');
    }
    protected array $action;
    protected string $uri, $method, $controller_name;
    protected ?string $host;
    protected ?int $version;
    protected ?\Swoole\Http\Request $request;
    protected function __construct(string $uri, string $method, ?int $version) {
        $action = explode('/', $uri);
        array_shift($action);
        if ('' === end($action)) {
            array_pop($action);
        }
        $this->action = $action;
        $this->uri = '/' . implode('/', $this->action);
        $this->method = strtoupper($method);
        $this->version = $version;
    }
    public function getAction(): array {
        return $this->action;
    }
    public function getURI(): string {
        return $this->uri;
    }
    public function getFd(): int {
        return $this->request->fd;
    }
    public function detachSwooleRequest(): self {
        $this->request = null;
        return $this;
    }
    public function getControllerName(): string {
        return $this->controller_name;
    }
    public function isWebhook(): bool {
        $action1 = strtolower(reset($this->action));
        return 'webhook' === $action1 || 'server' === $action1 || 'service' === $action1;
    }
    public function getHost(): string {
        return $this->host ?? '';
    }
    protected static function getCacheKey(string $uri, string $method, ?int $version, int $par_count = 0): string {
        if (! isset($version)) {
            $version = 1;
        }
        $uri .= str_repeat('/*', $par_count);
        return "{$method}{$uri}-{$version}";
    }
    public function getController(\Swoole\Http\Response $response): Controller {
        if (self::$no_static_path === null || strpos($this->uri, self::$no_static_path) !== 0) {
            $point_pos = strpos($this->uri, '.');
            if ($point_pos !== false) {
                if ($this->method !== 'GET' && $this->method !== 'HEAD') {
                    throw new \ExceptionToResponse\BadRequestException();
                } else {
                    return Controller\StaticResourceController::getInstance()->setSwooleHttpObject($this->request,
                        $response)->setPar()->setMethod($this->method)->setAction($this->action);
                }
            }
        }
        $par = [];
        $action = $this->action;
        do {
            $controller_name = '/' . strtolower(implode('/', $action));
            $cache_key = self::getCacheKey($controller_name, $this->method, $this->version, count($par));
            if (array_key_exists($cache_key, self::$cache)) {
                $classname = self::$cache[$cache_key];
                return $classname::getInstance()->setSwooleHttpObject($this->request, $response)->setPar(...
                    array_reverse($par))->setMethod($this->method)->setAction($this->action);
            }
            $par[] = array_pop($action);
        } while (! empty($action));
        $par = [];
        $action = $this->action;
        $v = $this->version ?? '';
        do {
            if (in_array('', $action, true)) {
                // Empty router not supported. Use as param
                if (empty($action)) {
                    break;
                }
                $par[] = array_pop($action);
                continue;
            }
            $classname = '\\Controller\\' . (empty($action) ? '' : strtolower(implode('\\', $action)) . '\\') .
                $this->method . $v;
            $class_exists = class_exists($classname, false);
            if (! $class_exists) {
                $file_name = Environment::getDir()->controller .
                    (empty($action) ? '' : strtolower(implode('/', $action)) . '/') . $this->method . $v . '.php';
                if (file_exists($file_name)) {
                    require_once $file_name;
                    $class_exists = class_exists($classname, false);
                }
            }
            if ((! $class_exists) || (! empty($par) && ! $classname::WITH_PAR)) {
                if (empty($action)) {
                    break;
                }
                $par[] = array_pop($action);
                continue;
            }
            $controller_name = '/' . strtolower(implode('/', $action));
            $this->controller_name = $controller_name;
            if ($classname::USE_ROUTER_CACHE) {
                $cache_key = self::getCacheKey($controller_name, $this->method, $this->version, count($par));
                self::$cache[$cache_key] = $classname;
            }
            return $classname::getInstance()->setSwooleHttpObject($this->request, $response)->setPar(...
                array_reverse($par))->setMethod($this->method)->setAction($this->action);
        } while (true);
        throw new \ExceptionToResponse\ResourceNotExistsException();
    }
    public function getMethod(): string {
        return $this->method;
    }
}