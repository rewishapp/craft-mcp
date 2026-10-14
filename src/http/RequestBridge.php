<?php

namespace rewish\craft\mcp\http;

use craft\web\Request;
use craft\web\Response;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RequestBridge
{
    public function __construct(private readonly Psr17Factory $psr17 = new Psr17Factory()) {}

    public function toPsr7(Request $request): ServerRequestInterface
    {
        $body = $this->psr17->createStream($request->getRawBody());
        $psr = $this->psr17->createServerRequest($request->getMethod(), $request->getAbsoluteUrl())->withBody($body);

        foreach ($request->getHeaders()->toArray() as $name => $values) {
            if (\in_array(strtolower((string) $name), ['host', 'content-length'], true)) {
                continue;
            }
            $psr = $psr->withHeader((string) $name, $values);
        }

        return $psr;
    }

    public function apply(ResponseInterface $psr, Response $response): Response
    {
        $response->format = Response::FORMAT_RAW;
        $response->setStatusCode($psr->getStatusCode());
        foreach ($psr->getHeaders() as $name => $values) {
            if (\in_array(strtolower($name), ['transfer-encoding', 'content-length'], true)) {
                continue;
            }
            $response->getHeaders()->set($name, $values);
        }
        $response->content = (string) $psr->getBody();

        return $response;
    }
}
