<?php

namespace rewish\craft\mcp\services;

use Mcp\Server;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use rewish\craft\mcp\Mcp;
use rewish\craft\mcp\policy\Gate;

final class ServerFactory
{
    public function create(?string $scope = null, ?SessionStoreInterface $sessionStore = null): Server
    {
        $settings = Mcp::settings();
        $gate = new Gate($settings->enableDangerousTools, $settings->disabledTools, $scope);

        $builder = Server::builder()
            ->setServerInfo(
                name: 'MCP - All in one',
                version: Mcp::getInstance()?->getVersion() ?? '1.0.0',
                description: 'Read and write Craft CMS content, inspect the schema, and query the install.',
            )
            ->setInstructions($this->instructions())
            ->setPaginationLimit(max(1, $settings->paginationLimit))
            ->setLogger($this->logger())
            ->setLazyLoading(false)
        ;

        if ($sessionStore !== null) {
            $builder->setSession($sessionStore);
        }

        (new Registrar())->apply($builder, $gate);

        return $builder->build();
    }

    private function instructions(): string
    {
        $settings = Mcp::settings();
        $lines = [
            'You are connected to MCP - All in one, a Craft CMS '.\Craft::$app->getVersion().' server.',
            'Read describe_entry_schema before writing a section. Entry writes are drafts when write mode is draft.',
            'Relations use natural keys: {section, slug}, {volume, filename}, {group, slug}, or {username}.',
            'Matrix blocks use create_nested_entry and move_nested_entry.',
            'Do not publish, delete, or run dangerous tools unless the user asked for that.',
        ];

        if ($settings->additionalInstructions !== '') {
            $lines[] = $settings->additionalInstructions;
        }

        return implode("\n", $lines);
    }

    private function logger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $text = '['.$level.'] '.$message;
                if (\defined('STDOUT')) {
                    fwrite(\STDERR, $text.\PHP_EOL);
                }
            }
        };
    }
}
