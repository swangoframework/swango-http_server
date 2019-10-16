<?php
namespace Swango\HttpServer\Controller;
class StaticResourceController extends \Swango\HttpServer\Controller {
    private static $file_exists_cache = [];
    protected static function file_exists(string $file): bool {
        if (array_key_exists($file, self::$file_exists_cache))
            return self::$file_exists_cache[$file];
        $result = file_exists($file);
        if (count(self::$file_exists_cache) > 2048) {
            array_shift(self::$file_exists_cache);
            array_shift(self::$file_exists_cache);
            array_shift(self::$file_exists_cache);
        }
        self::$file_exists_cache[$file] = $result;
        return $result;
    }
    protected function handle(): void {
        $uri = $this->swoole_http_request->server['request_uri'];
        $type = substr($uri, strrpos($uri, '.') + 1);
        if (! array_key_exists($type, \Swlib\Http\ContentType::MAP)) {
            $this->E_404();
            return;
        }
        static $appdir;
        if (! isset($appdir))
            $appdir = substr(\Swango\Environment::getDir()->app, 0, - 1);
        $file = $appdir . $uri;

        if (array_key_exists('accept-encoding', $this->swoole_http_request->header) &&
             strpos($this->swoole_http_request->header['accept-encoding'], 'gzip') !== false) {
            $gz_file = "$file.gz";
            if (self::file_exists($gz_file)) {
                $this->swoole_http_response->header('Cache-Control', 'max-age=315360000');
                $this->swoole_http_response->header('Content-Encoding', 'gzip');
                $this->swoole_http_response->header('Content-Type', \Swlib\Http\ContentType::MAP[$type]);
                if ($this->method === 'GET')
                    $this->swoole_http_response->sendfile($gz_file);
                else
                    $this->swoole_http_response->end();

                $this->json_response_code = 200;
                $this->response_finished = true;
                return;
            }
        }
        if (! self::file_exists($file)) {
            self::E_404();
            return;
        }
        $this->swoole_http_response->header('Content-Type', \Swlib\Http\ContentType::MAP[$type]);
        if ($this->method === 'GET')
            $this->swoole_http_response->sendfile($file);
        else
            $this->swoole_http_response->end();
        $this->json_response_code = 200;
        $this->response_finished = true;
    }
}