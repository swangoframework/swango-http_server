<?php
namespace Swango\HttpServer\Session;
interface AgentMapInterface {
    public function getAgentId(string $agent): int;
    public function getAgent(int $agent_id): string;
    public function getWebAgentId(): int;
    public function getWebhookAgentId(): int;
    public function getAgentFromUserAgent(string $user_agent): int;
    public function useCookieForSession(int $agent_id): bool;
}