<?php

namespace rewish\craft\mcp\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use rewish\craft\mcp\installer\clients\ClaudeCode;
use rewish\craft\mcp\installer\clients\Codex;
use rewish\craft\mcp\installer\clients\Cursor;
use rewish\craft\mcp\installer\contracts\Client;
use rewish\craft\mcp\installer\EnvironmentDetector;
use rewish\craft\mcp\installer\JsonConfigWriter;
use rewish\craft\mcp\installer\TomlConfigWriter;
use rewish\craft\mcp\installer\WriteResult;
use yii\console\ExitCode;

/**
 * MCP client configuration installer.
 *
 * php craft craft-mcp/install
 */
class InstallController extends Controller
{
    public ?string $environment = null;

    public string $serverName = 'craft-mcp';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        if ($actionID === 'index') {
            $options[] = 'environment';
            $options[] = 'serverName';
        }

        return $options;
    }

    public function optionAliases(): array
    {
        return [
            'e' => 'environment',
            's' => 'serverName',
        ];
    }

    public function actionIndex(): int
    {
        $craftRoot = (string) \Craft::getAlias('@root');
        $detector = new EnvironmentDetector($craftRoot);
        $jsonWriter = new JsonConfigWriter();
        $tomlWriter = new TomlConfigWriter();

        $this->stdout("\nMCP Client Configuration Wizard\n", Console::FG_CYAN, Console::BOLD);
        $this->stdout(str_repeat('=', 32)."\n\n");

        $environment = $this->resolveEnvironment($detector);
        $configRoot = $craftRoot;
        $craftSubdirectory = null;

        if ($this->interactive) {
            $this->stdout('Craft subdirectory (blank if Craft is the project root): ');
            $input = trim((string) fgets(\STDIN));
            $input = trim($input, '/ ');
            if ($input !== '') {
                $levels = \count(explode('/', $input));
                $configRoot = \dirname($craftRoot, $levels);
                $craftSubdirectory = $input;
            }
        }

        $clients = $this->selectClients($configRoot, $detector);
        if ($clients === []) {
            $this->stdout("\nNo clients selected. Nothing to do.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $projectPath = $this->resolveProjectPath($clients, $configRoot);
        $serverName = $this->resolveServerName($clients, $jsonWriter, $tomlWriter);

        $this->stdout("\nGenerating configuration files...\n\n");
        foreach ($clients as $client) {
            $config = $client->generateServerConfig($environment, $projectPath, $craftSubdirectory);

            try {
                $result = $client->format() === 'toml'
                    ? $tomlWriter->write($client->getConfigPath(), $serverName, $config)
                    : $jsonWriter->write($client->getConfigPath(), $serverName, $config);
                $this->printResult($client, $result);
            } catch (\Throwable $e) {
                $this->stderr(' ✗ '.$client->getName().': '.$e->getMessage()."\n", Console::FG_RED);
            }

            if ($client->getId() === 'codex') {
                $this->printCodexHint($serverName, $config);
            }
        }

        $this->stdout("\nSuccess. Restart your MCP clients to pick up the new configuration.\n", Console::FG_GREEN);
        $this->stdout("Docs: https://github.com/rewishapp/craft-mcp/blob/main/docs/connect.md\n\n");

        return ExitCode::OK;
    }

    private function resolveEnvironment(EnvironmentDetector $detector): string
    {
        if ($this->environment !== null) {
            return $this->environment;
        }

        $detected = $detector->detect();
        $label = $detected === EnvironmentDetector::DDEV ? 'DDEV' : 'Native PHP';
        $this->stdout("Detected environment: {$label}\n");

        if (!$this->interactive) {
            return $detected;
        }

        if ($this->confirm('Use this environment?', true)) {
            return $detected;
        }

        return $this->select('Select environment:', [
            EnvironmentDetector::DDEV => 'DDEV',
            EnvironmentDetector::NATIVE => 'Native PHP',
        ]);
    }

    /**
     * @return list<Client>
     */
    private function selectClients(string $configRoot, EnvironmentDetector $detector): array
    {
        $craftRoot = (string) \Craft::getAlias('@root');
        $available = [
            new ClaudeCode($craftRoot, $configRoot, $detector),
            new Cursor($craftRoot, $configRoot, $detector),
            new Codex($craftRoot, $configRoot, $detector),
        ];

        if (!$this->interactive) {
            // Non-interactive: Claude Code + Cursor only (Codex offered but not preselected).
            return [
                new ClaudeCode($craftRoot, $configRoot, $detector),
                new Cursor($craftRoot, $configRoot, $detector),
            ];
        }

        $this->stdout("\nWhich MCP clients would you like to configure?\n\n");
        $selected = [];
        foreach ($available as $client) {
            $default = $client->getId() !== 'codex';
            $label = $client->getName().' ('.$client->getDescription().')';
            if ($this->confirm("  Configure {$label}?", $default)) {
                $selected[] = $client;
            }
        }

        return $selected;
    }

    /**
     * Absolute project path when a client (Codex) needs cwd / absolute binaries.
     *
     * @param list<Client> $clients
     */
    private function resolveProjectPath(array $clients, string $defaultPath): ?string
    {
        $need = array_filter($clients, static fn (Client $c): bool => $c->requiresAbsolutePaths());
        if ($need === []) {
            return null;
        }

        if (!$this->interactive) {
            return $defaultPath;
        }

        $this->stdout("\n");
        $names = array_map(static fn (Client $c): string => $c->getName(), $need);

        return $this->prompt('Project path for '.implode(' and ', $names), [
            'default' => $defaultPath,
            'required' => true,
        ]);
    }

    /**
     * @param list<Client> $clients
     */
    private function resolveServerName(array $clients, JsonConfigWriter $jsonWriter, TomlConfigWriter $tomlWriter): string
    {
        $serverName = $this->interactive
            ? $this->prompt('Server name', ['default' => $this->serverName, 'required' => true])
            : $this->serverName;

        $conflicts = [];
        foreach ($clients as $client) {
            $exists = $client->format() === 'toml'
                ? $tomlWriter->serverExists($client->getConfigPath(), $serverName)
                : $jsonWriter->serverExists($client->getConfigPath(), $serverName);
            if ($exists) {
                $conflicts[] = $client->getName();
            }
        }

        if ($conflicts !== [] && $this->interactive) {
            $this->stdout("\nWarning: Server '{$serverName}' already exists in: ".implode(', ', $conflicts)."\n", Console::FG_YELLOW);
            if (!$this->confirm('Overwrite existing configuration?', false)) {
                return $this->prompt('Enter a different server name', ['required' => true]);
            }
        }

        return $serverName;
    }

    private function printResult(Client $client, WriteResult $result): void
    {
        $this->stdout(' ✓ ', Console::FG_GREEN);
        $this->stdout($client->getName().': '.$result->getDescription().' '.$this->displayPath($result->path)."\n");
    }

    /**
     * Codex ignores a project’s .codex/config.toml until the project is trusted
     * (fails silently). Offer the user-level `codex mcp add` command instead.
     *
     * @param array{command: string, args: list<string>, cwd?: string} $config
     */
    private function printCodexHint(string $serverName, array $config): void
    {
        $this->stdout("   Note: Codex only loads project config for trusted projects.\n", Console::FG_YELLOW);
        $args = array_map(static fn (string $a) => escapeshellarg($a), $config['args']);
        $cmd = 'codex mcp add '.escapeshellarg($serverName).' -- '.escapeshellarg($config['command']).' '.implode(' ', $args);
        $this->stdout("   User-level Codex (works without trusting the project): {$cmd}\n", Console::FG_GREY);
    }

    private function displayPath(string $path): string
    {
        $home = getenv('HOME');
        if (\is_string($home) && str_starts_with($path, $home)) {
            return '~'.substr($path, \strlen($home));
        }
        $root = (string) \Craft::getAlias('@root');
        if (str_starts_with($path, $root)) {
            return '.'.substr($path, \strlen($root));
        }

        return $path;
    }
}
