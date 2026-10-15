<?php

namespace rewish\craft\mcp\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use rewish\craft\mcp\http\oauth\ConnectedApps;
use rewish\craft\mcp\http\oauth\Grants;
use rewish\craft\mcp\http\oauth\RecordOAuthStore;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\records\ActivityRecord;
use rewish\craft\mcp\records\TokenRecord;
use rewish\craft\mcp\services\Catalog;
use rewish\craft\mcp\services\Setup;
use rewish\craft\mcp\services\Tokens;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CpController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission('craft-mcp:access');
        $settings = Mcp::settings();
        $catalog = new Catalog();
        $tools = $catalog->all();
        $setup = new Setup();

        return $this->renderTemplate('craft-mcp/dashboard/index', [
            'settings' => $settings,
            'toolCount' => \count(array_filter($tools, static fn (array $tool) => $tool['exposed'])),
            'toolTotal' => \count($tools),
            'tokenCount' => (int) TokenRecord::find()->where(['grantId' => null])->count(),
            'callsToday' => $this->callsToday(),
            'draftCount' => (int) Entry::find()->drafts()->status(null)->count(),
            'activity' => $this->activity(8),
            'stdioSnippet' => $setup->stdioSnippet(),
            'httpSnippet' => $setup->httpSnippet(),
            'endpointUrl' => $setup->endpointUrl(),
        ]);
    }

    public function actionDrafts(): Response
    {
        $this->requirePermission('craft-mcp:access');
        $drafts = Entry::find()->drafts()->status(null)->orderBy(['dateUpdated' => \SORT_DESC])->limit(40)->all();

        return $this->renderTemplate('craft-mcp/drafts/index', ['drafts' => $drafts]);
    }

    public function actionPublishDraft(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('craft-mcp:access');
        $id = (int) $this->request->getRequiredBodyParam('entryId');
        $entry = Entry::find()->id($id)->drafts()->status(null)->one();
        if (!$entry instanceof Entry) {
            $this->setFailFlash('That draft is gone.');

            return $this->redirect('craft-mcp/drafts');
        }

        $user = \Craft::$app->getUser()->getIdentity();
        $section = $entry->getSection();
        if ($user && $section && !$user->can('saveEntries:'.$section->uid)) {
            $this->setFailFlash('You cannot publish that draft.');

            return $this->redirect('craft-mcp/drafts');
        }

        \Craft::$app->getDrafts()->applyDraft($entry);
        $this->setSuccessFlash('Draft published.');

        return $this->redirect('craft-mcp/drafts');
    }

    public function actionTools(): Response
    {
        $this->requirePermission('craft-mcp:access');

        return $this->renderTemplate('craft-mcp/tools/index', [
            'tools' => (new Catalog())->all(),
            'canManage' => \Craft::$app->getUser()->getIdentity()?->can('craft-mcp:manageSettings') ?? false,
        ]);
    }

    public function actionToggleTool(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('craft-mcp:manageSettings');
        $name = (string) $this->request->getRequiredBodyParam('tool');
        $settings = Mcp::settings();
        $disabled = $settings->disabledTools;
        if (\in_array($name, $disabled, true)) {
            $disabled = array_values(array_diff($disabled, [$name]));
        } else {
            $disabled[] = $name;
        }
        if ($this->persist(['disabledTools' => $disabled])) {
            $this->setSuccessFlash('Tool list updated.');
        }

        return $this->redirect('craft-mcp/tools');
    }

    public function actionTokens(): Response
    {
        $this->requirePermission('craft-mcp:manageTokens');
        $user = \Craft::$app->getUser()->getIdentity();
        $all = $user && $user->can('craft-mcp:manageAllTokens');
        $tokens = (new Tokens())->all($all ? null : $user?->id);
        $users = [];
        foreach ($tokens as $token) {
            $owner = \Craft::$app->getUsers()->getUserById($token->userId);
            $users[$token->id] = $owner?->username ?? 'removed user';
        }

        $settings = Mcp::settings();
        $apps = null;
        $appOwners = [];
        if ($settings->httpTransport && $settings->oauth) {
            $apps = (new ConnectedApps(new RecordOAuthStore()))->rows($all ? null : $user?->id);
            foreach ($apps as $app) {
                if (!isset($appOwners[$app['userId']])) {
                    $owner = \Craft::$app->getUsers()->getUserById($app['userId']);
                    $appOwners[$app['userId']] = $owner?->username ?? 'removed user';
                }
            }
        }

        return $this->renderTemplate('craft-mcp/tokens/index', [
            'tokens' => $tokens,
            'owners' => $users,
            'sections' => \Craft::$app->getEntries()->getAllSections(),
            'isAdmin' => (bool) $user?->admin,
            'apps' => $apps,
            'appOwners' => $appOwners,
            'showAppUser' => (bool) $all,
        ]);
    }

    public function actionDisconnectApp(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('craft-mcp:manageTokens');
        $user = \Craft::$app->getUser()->getIdentity();
        if ($user === null) {
            throw new ForbiddenHttpException('Sign in required.');
        }

        $grantId = (int) $this->request->getRequiredBodyParam('id');
        $store = new RecordOAuthStore();
        $grant = $store->findGrant($grantId);
        if ($grant === null || $grant->isRevoked()) {
            $this->setFailFlash('That connection is already gone.');

            return $this->redirect('craft-mcp/tokens');
        }

        $owns = $grant->userId === (int) $user->id;
        if (!$owns && !$user->can('craft-mcp:manageAllTokens')) {
            throw new ForbiddenHttpException('You cannot disconnect someone else’s app.');
        }

        (new Grants($store, new Tokens()))->revoke($grantId);
        $this->setSuccessFlash('App disconnected.');

        return $this->redirect('craft-mcp/tokens');
    }

    public function actionCreateToken(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('craft-mcp:manageTokens');
        $user = \Craft::$app->getUser()->getIdentity();
        if ($user === null) {
            throw new ForbiddenHttpException('Sign in required.');
        }

        $name = trim((string) $this->request->getRequiredBodyParam('name'));
        $scope = (string) $this->request->getRequiredBodyParam('scope');
        $days = $this->request->getBodyParam('days');
        $sections = array_values(array_filter((array) $this->request->getBodyParam('sections', [])));
        $tokens = new Tokens();
        if (!$tokens->userCanMint($scope, $user)) {
            $this->setFailFlash('You cannot mint that scope.');

            return $this->redirect('craft-mcp/tokens');
        }

        $expiry = null;
        if (is_numeric($days) && (int) $days > 0) {
            $expiry = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify('+'.(int) $days.' days')
                ->format('Y-m-d H:i:s')
            ;
        }

        $issued = $tokens->issue($user->id, $name !== '' ? $name : 'Token', $scope, $expiry, $sections);
        \Craft::$app->getSession()->setFlash('craftmcpToken', $issued['plain']);
        \Craft::$app->getSession()->setFlash('craftmcpTokenName', $issued['record']->name);

        return $this->redirect('craft-mcp/tokens/reveal');
    }

    public function actionRevealToken(): Response
    {
        $this->requirePermission('craft-mcp:manageTokens');
        $plain = \Craft::$app->getSession()->getFlash('craftmcpToken');
        $name = \Craft::$app->getSession()->getFlash('craftmcpTokenName', 'Token');

        return $this->renderTemplate('craft-mcp/tokens/reveal', [
            'token' => \is_string($plain) ? $plain : null,
            'name' => $name,
            'httpSnippet' => \is_string($plain) ? (new Setup())->httpSnippet($plain) : null,
            'showSnippet' => Mcp::settings()->showClientConfigSnippet,
        ]);
    }

    public function actionRevokeToken(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('craft-mcp:manageTokens');
        $id = (int) $this->request->getRequiredBodyParam('tokenId');
        $record = TokenRecord::find()->where(['id' => $id])->one();
        $user = \Craft::$app->getUser()->getIdentity();
        if ($record instanceof TokenRecord && $user) {
            $owns = (int) $record->userId === (int) $user->id;
            if ($owns || $user->can('craft-mcp:manageAllTokens')) {
                (new Tokens())->revoke($id);
                $this->setSuccessFlash('Token revoked.');
            }
        }

        return $this->redirect('craft-mcp/tokens');
    }

    public function actionActivity(): Response
    {
        $this->requirePermission('craft-mcp:access');

        return $this->renderTemplate('craft-mcp/activity/index', [
            'rows' => $this->activity(80),
        ]);
    }

    public function actionSetup(): Response
    {
        $this->requirePermission('craft-mcp:access');
        $setup = new Setup();

        return $this->renderTemplate('craft-mcp/setup/index', [
            'stdioSnippet' => $setup->stdioSnippet(),
            'httpSnippet' => $setup->httpSnippet(),
            'endpointUrl' => $setup->endpointUrl(),
            'settings' => Mcp::settings(),
        ]);
    }

    public function actionSettings(): Response
    {
        $this->requirePermission('craft-mcp:manageSettings');

        return $this->renderTemplate('craft-mcp/settings/index', [
            'settings' => Mcp::settings(),
        ]);
    }

    public function actionSaveSettings(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('craft-mcp:manageSettings');
        $posted = (array) $this->request->getBodyParam('settings', []);
        $bools = ['enabled', 'enableDangerousTools', 'enableTinker', 'httpTransport', 'oauth', 'showClientConfigSnippet', 'publishServerCard', 'previewUrls'];
        foreach ($bools as $key) {
            $posted[$key] = !empty($posted[$key]);
        }
        $posted['allowedIps'] = $this->lines($posted['allowedIps'] ?? '');
        $posted['oauthRedirectUris'] = $this->lines($posted['oauthRedirectUris'] ?? '');
        $posted['disabledScopes'] = array_values(array_filter((array) ($posted['disabledScopes'] ?? [])));
        $duration = $posted['previewTokenDuration'] ?? null;
        $posted['previewTokenDuration'] = is_numeric($duration) && (int) $duration > 0 ? (int) $duration : null;
        if (isset($posted['oauthAccessTokenTtl']) && is_numeric($posted['oauthAccessTokenTtl'])) {
            $posted['oauthAccessTokenTtl'] = (int) $posted['oauthAccessTokenTtl'];
        }
        if (isset($posted['oauthRefreshTokenTtl']) && is_numeric($posted['oauthRefreshTokenTtl'])) {
            $posted['oauthRefreshTokenTtl'] = (int) $posted['oauthRefreshTokenTtl'];
        }
        if ($this->persist($posted)) {
            $this->setSuccessFlash('Settings saved.');
        }

        return $this->redirect('craft-mcp/settings');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function persist(array $overrides): bool
    {
        $plugin = Mcp::getInstance();
        if (!$plugin instanceof Mcp) {
            return false;
        }
        $settings = Mcp::settings();
        $data = $settings->toArray();
        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }
        $settings->setAttributes($data, false);
        if (!$settings->validate()) {
            $this->setFailFlash(implode(' ', $settings->getFirstErrors()));

            return false;
        }
        \Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

        return true;
    }

    private function callsToday(): int
    {
        try {
            return (int) ActivityRecord::find()->where(['>=', 'dateCreated', date('Y-m-d 00:00:00')])->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<ActivityRecord>
     */
    private function activity(int $limit): array
    {
        try {
            return ActivityRecord::find()->orderBy(['id' => \SORT_DESC])->limit($limit)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function lines(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        $lines = preg_split('/\R/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $lines)));
    }
}
