<?php
namespace Swango\HttpServer\Session;
class Agent implements AgentMapInterface {
    public const Agent_web = 1;
    public const Agent_wx = 2;
    public const Agent_ali = 3;
    public const Agent_app = 4;
    public const Agent_wmp = 5;
    public const Agent_webhook = 6;
    public const Agent_qmp = 7;
    public const Agent_oppo = 8;
    public const Agent_badam = 9;
    public const Agent_toutiao = 10;
    public const Agent_baidu = 11;
    private const MAP_ID_AGENT = [
        self::Agent_web => 'web',
        self::Agent_wx => 'mp',
        self::Agent_ali => 'ali',
        self::Agent_app => 'app',
        self::Agent_wmp => 'wmp',
        self::Agent_webhook => 'webhook',
        self::Agent_qmp => 'qmp',
        self::Agent_oppo => 'oppo',
        self::Agent_badam => 'badam',
        self::Agent_toutiao => 'toutiao',
        self::Agent_baidu => 'baidu'
    ];
    private const MAP_AGENT_ID = [
        'web' => self::Agent_web,
        'mp' => self::Agent_wx,
        'ali' => self::Agent_ali,
        'app' => self::Agent_app,
        'wmp' => self::Agent_wmp,
        'webhook' => self::Agent_webhook,
        'qmp' => self::Agent_qmp,
        'oppo' => self::Agent_oppo,
        'badam' => self::Agent_badam,
        'toutiao' => self::Agent_toutiao,
        'baidu' => self::Agent_baidu
    ];
    public function getAgentId(string $agent): int {
        if (array_key_exists($agent, self::MAP_AGENT_ID)) {
            return self::MAP_AGENT_ID[$agent];
        } else {
            throw new \Swango\HttpServer\Session\Exception\InvalidAgentException();
        }
    }
    public function getAgent(int $agent_id): string {
        if (array_key_exists($agent_id, self::MAP_ID_AGENT)) {
            return self::MAP_AGENT_ID[$agent_id];
        } else {
            throw new \Swango\HttpServer\Session\Exception\InvalidAgentException();
        }
    }
    public function getWebAgentId(): int {
        return self::Agent_web;
    }
    public function getWebhookAgentId(): int {
        return self::Agent_webhook;
    }
    public function getAgentFromUserAgent(string $user_agent): int {
        if (($pos = strpos($user_agent, 'MicroMessenger')) !== false) {
            return self::Agent_wx;
        } elseif (($pos = strpos($user_agent, 'Alipay')) !== false) {
            return self::Agent_ali;
        } else {
            return self::Agent_web;
        }
    }
    public function useCookieForSession(int $agent_id): bool {
        return self::Agent_web === $agent_id || self::Agent_wx === $agent_id || self::Agent_ali === $agent_id;
    }
    public function echoErrorMsgWhenMethodGet(?int $agent_id): bool {
        return self::Agent_web === $agent_id || self::Agent_wx === $agent_id || self::Agent_ali === $agent_id;
    }
}