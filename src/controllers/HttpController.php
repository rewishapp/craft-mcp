<?php

namespace rewish\craft\mcp\controllers;

use craft\web\Controller;
use craft\web\Response;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use rewish\craft\mcp\http\DbSessionStore;
use rewish\craft\mcp\http\RequestBridge;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\services\ServerFactory;
use rewish\craft\mcp\services\Setup;
use rewish\craft\mcp\services\Tokens;
use rewish\craft\mcp\support\CallContext;

class HttpController extends Controller
{
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE;

    public function actionHandle(): Response
    {
        $settings = Mcp::settings();
        if (!$settings->enabled || !$settings->httpTransport) {
            return $this->fail(404, 'MCP HTTP transport is not enabled');
        }

        if ($settings->allowedIps !== [] && !\in_array($this->request->getUserIP(), $settings->allowedIps, true)) {
            return $this->fail(403, 'Client IP is not allowed');
        }

        if ($this->request->getMethod() === 'OPTIONS') {
            $this->response->format = Response::FORMAT_RAW;
            $this->response->setStatusCode(204);
            $this->response->getHeaders()->set('Allow', 'POST, DELETE, OPTIONS');

            return $this->response;
        }

        $token = $this->request->getBearerToken();
        if ($token === null || $token === '') {
            $this->response->getHeaders()->set('WWW-Authenticate', 'Bearer');

            return $this->fail(401, 'Invalid or missing bearer token');
        }

        $record = (new Tokens())->authenticate($token);
        if ($record === null) {
            return $this->fail(401, 'Invalid or missing bearer token');
        }

        $user = \Craft::$app->getUsers()->getUserById($record->userId);
        if ($user === null || $user->suspended) {
            return $this->fail(401, 'This token’s user cannot sign in');
        }

        \Craft::$app->getUser()->setIdentity($user);
        CallContext::reset('http');
        CallContext::$userId = $user->id;
        CallContext::$tokenId = (int) $record->id;
        CallContext::$scope = $record->scope;
        CallContext::$sections = $record->sectionHandles();

        $psr17 = new Psr17Factory();
        $bridge = new RequestBridge($psr17);
        $server = (new ServerFactory())->create($record->scope, new DbSessionStore($settings->httpSessionTtl));
        $transport = new StreamableHttpTransport(
            request: $bridge->toPsr7($this->request),
            responseFactory: $psr17,
            streamFactory: $psr17,
            middleware: [
                new CorsMiddleware(),
                new DnsRebindingProtectionMiddleware($this->allowedHttpHosts()),
                new ProtocolVersionMiddleware(),
            ],
        );

        ob_start();

        try {
            $psr = $server->run($transport);
        } finally {
            ob_end_clean();
            CallContext::reset();
        }

        return $bridge->apply($psr, $this->response);
    }

    public function actionCard(): Response
    {
        if (!Mcp::settings()->publishServerCard) {
            return $this->fail(404, 'Server card is off');
        }

        $setup = new Setup();
        $this->response->format = Response::FORMAT_JSON;
        $this->response->data = [
            'serverInfo' => [
                'name' => 'MCP - All in one',
                'version' => Mcp::getInstance()?->getVersion() ?? '1.0.0',
            ],
            'transport' => [
                'type' => 'streamable-http',
                'url' => $setup->endpointUrl(),
            ],
        ];

        return $this->response;
    }

    /**
     * The SDK’s default host list is localhost only. This endpoint is the public site, so the
     * request host and every configured site host are allowed too.
     *
     * @return list<string>
     */
    private function allowedHttpHosts(): array
    {
        $hosts = ['localhost', '127.0.0.1', '[::1]'];
        $candidates = [$this->request->getHostName()];
        $public = Mcp::settings()->httpPublicUrl;
        if (\is_string($public) && $public !== '') {
            $candidates[] = $public;
        }
        foreach (\Craft::$app->getSites()->getAllSites() as $site) {
            $candidates[] = (string) $site->getBaseUrl();
        }

        foreach ($candidates as $candidate) {
            $parsed = parse_url($candidate, \PHP_URL_HOST);
            $host = \is_string($parsed) && $parsed !== '' ? $parsed : $candidate;
            $host = strtolower(trim($host));
            if ($host !== '' && !str_contains($host, '/')) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    private function fail(int $status, string $message): Response
    {
        $this->response->format = Response::FORMAT_RAW;
        $this->response->setStatusCode($status);
        $this->response->getHeaders()->set('Content-Type', 'application/json');
        $this->response->content = json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => -32001, 'message' => $message],
        ], \JSON_THROW_ON_ERROR);

        return $this->response;
    }
}
