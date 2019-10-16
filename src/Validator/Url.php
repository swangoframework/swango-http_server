<?php
namespace Swango\HttpServer\Validator;
class Url extends Anything {
    public function __construct(?string $key = null) {
        parent::__construct($key ?? '链接', 1, 1024);
        $this->cnmsg = '填写正确URL地址';
    }
    protected function check(?string $key, &$value): void {
        parent::check($key, $value);
        if ($value{0} == '/')
            return;
        if (! preg_match(
            '/^http[s]?:\/\/' . '(([0-9]{1,3}\.){3}[0-9]{1,3}' . // IP形式的URL- 199.194.52.184
'|' . // 允许IP和DOMAIN（域名）
'([0-9a-z_!~*\'()-]+\.)*' . // 三级域验证- www.
'([0-9a-z][0-9a-z-]{0,61})?[0-9a-z]\.' . // 二级域验证
'[a-z]{2,6})' . // 顶级域验证.com or .museum
'(:[0-9]{1,4})?' . // 端口- :80
'((\/\?)|' . // 如果含有文件对文件部分进行校验
'(\/[0-9a-zA-Z_!~\*\'\(\)\.;\?:@&=\+\$,%#-\/]*)?)$/', $value))
            throw new \ExceptionToResponse('Invalid URL', '请填写正确的链接');
    }
}

