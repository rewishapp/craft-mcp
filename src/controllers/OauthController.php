<?php

namespace rewish\craft\mcp\controllers;

use craft\web\Controller;
use craft\web\Response;
use rewish\craft\mcp\http\oauth\Grants;
use rewish\craft\mcp\http\oauth\Metadata;
use rewish\craft\mcp\http\oauth\OAuthConfig;
use rewish\craft\mcp\http\oauth\OAuthError;
use rewish\craft\mcp\http\oauth\RecordOAuthStore;
use rewish\craft\mcp\http\oauth\Registration;
use rewish\craft\mcp\http\oauth\TokenExchange;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\services\Tokens;
use rewish\craft\mcp\support\OAuthPolicy;
use yii\web\NotFoundHttpException;

/**
 * The OAuth connector's machine-facing endpoints: the two discovery
 * documents, client registration, and the token endpoint. The person-facing
 * consent screen lives in the control panel (OauthConsentController).
 *
 * Anonymous by design, as OAuth requires, and without cookies, so CORS is
 * opened for every origin: a browser-based client (the MCP Inspector, for
 * one) has to read these, and nothing here acts on ambient credentials.
 */
final class OauthController extends Controller
{
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE;

    public function beforeAction($action): bool
    {
        $settings = Mcp::settings();
        if (!$settings->enabled || !$settings->httpTransport || !$settings->oauth) {
            throw new NotFoundHttpException();
        }

        $headers = $this->response->getHeaders();
        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, MCP-Protocol-Version');
        $headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');

        if ($this->request->getMethod() === 'OPTIONS') {
            $this->response->setStatusCode(204);
            $this->response->format = Response::FORMAT_RAW;
            $this->response->content = '';
            $this->response->send();

            return false;
        }

        return parent::beforeAction($action);
    }

    public function actionProtectedResource(): Response
    {
        return $this->json((new Metadata(OAuthConfig::current()))->protectedResource());
    }

    public function actionAuthorizationServer(): Response
    {
        return $this->json((new Metadata(OAuthConfig::current()))->authorizationServer());
    }

    public function actionRegister(): Response
    {
        $this->requirePostRequest();

        try {
            $metadata = json_decode($this->request->getRawBody(), true, 16, \JSON_THROW_ON_ERROR);
            if (!\is_array($metadata)) {
                throw new OAuthError('invalid_client_metadata', 'The registration request must be a JSON object.');
            }

            $registration = new Registration(new RecordOAuthStore(), OAuthConfig::current()->redirectUris);

            return $this->json($registration->register($metadata), 201);
        } catch (\JsonException) {
            return $this->json((new OAuthError('invalid_client_metadata', 'The registration request is not valid JSON.'))->toArray(), 400);
        } catch (OAuthError $error) {
            return $this->json($error->toArray(), $error->status);
        }
    }

    public function actionToken(): Response
    {
        $this->requirePostRequest();
        $this->response->getHeaders()->set('Cache-Control', 'no-store');
        $this->response->getHeaders()->set('Pragma', 'no-cache');

        $store = new RecordOAuthStore();
        $tokens = new Tokens();
        $exchange = new TokenExchange($store, $tokens, new Grants($store, $tokens), OAuthConfig::current(), mayHold: OAuthPolicy::mayHold(...));

        try {
            return $this->json($exchange->exchange($this->request->getBodyParams()));
        } catch (OAuthError $error) {
            \Craft::info(\sprintf('OAuth token request refused: %s', $error->error), __METHOD__);

            return $this->json($error->toArray(), $error->status);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body, int $status = 200): Response
    {
        $this->response->format = Response::FORMAT_RAW;
        $this->response->setStatusCode($status);
        $this->response->getHeaders()->set('Content-Type', 'application/json');
        $this->response->content = json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        return $this->response;
    }
}
