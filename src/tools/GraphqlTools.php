<?php

namespace rewish\craft\mcp\tools;

use craft\models\GqlSchema;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;

final class GraphqlTools
{
    #[McpTool(name: 'list_graphql_schemas', description: 'List GraphQL schemas.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'graphql')]
    public function listGraphqlSchemas(): array
    {
        return Activity::run('list_graphql_schemas', function () {
            $schemas = [];
            foreach (\Craft::$app->getGql()->getSchemas() as $schema) {
                $schemas[] = $this->schemaRow($schema);
            }

            return ['count' => \count($schemas), 'schemas' => $schemas];
        });
    }

    #[McpTool(name: 'get_graphql_schema', description: 'Describe one GraphQL schema by uid or name, including whether it is the public schema.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'graphql')]
    public function getGraphqlSchema(string $uidOrName): array
    {
        return Activity::run('get_graphql_schema', function () use ($uidOrName) {
            $schema = $this->find($uidOrName);

            return ['found' => true, 'schema' => $this->schemaRow($schema)];
        });
    }

    #[McpTool(name: 'query_graphql', description: 'Run a read-only GraphQL query. Mutations are rejected.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'graphql', minScope: 'full')]
    public function queryGraphql(string $query, ?string $schema = null, #[Schema(type: 'object', additionalProperties: true)] ?array $variables = null): array
    {
        return Activity::run('query_graphql', function () use ($query, $schema, $variables) {
            if (preg_match('/\bmutation\b/i', $query)) {
                throw new ToolCallException('query_graphql does not run mutations. Use execute_graphql.');
            }

            return $this->execute($query, $schema, $variables);
        });
    }

    #[McpTool(name: 'execute_graphql', description: 'Execute a GraphQL query or mutation.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[ToolMeta(category: 'graphql', minScope: 'full', dangerous: true)]
    public function executeGraphql(string $query, ?string $schema = null, #[Schema(type: 'object', additionalProperties: true)] ?array $variables = null): array
    {
        return Activity::run('execute_graphql', fn () => $this->execute($query, $schema, $variables));
    }

    #[McpTool(name: 'list_graphql_tokens', description: 'List GraphQL API tokens and the schema each one uses.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'graphql', minScope: 'full')]
    public function listGraphqlTokens(): array
    {
        return Activity::run('list_graphql_tokens', static function () {
            $tokens = [];
            foreach (\Craft::$app->getGql()->getTokens() as $token) {
                $tokens[] = [
                    'id' => $token->id,
                    'name' => $token->name,
                    'enabled' => (bool) $token->enabled,
                    'schemaId' => $token->schemaId,
                ];
            }

            return ['count' => \count($tokens), 'tokens' => $tokens];
        });
    }

    /**
     * @param null|array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function execute(string $query, ?string $schemaName, ?array $variables): array
    {
        $schema = $schemaName ? $this->find($schemaName) : (\Craft::$app->getGql()->getPublicSchema() ?? $this->findFirst());
        $result = \Craft::$app->getGql()->executeQuery($schema, $query, $variables);

        return ['schema' => $schema->name, 'result' => $result];
    }

    private function find(string $uidOrName): GqlSchema
    {
        $gql = \Craft::$app->getGql();
        $schema = $gql->getSchemaByUid($uidOrName);
        if ($schema instanceof GqlSchema) {
            return $schema;
        }

        foreach ($gql->getSchemas() as $candidate) {
            if (strcasecmp((string) $candidate->name, $uidOrName) === 0) {
                return $candidate;
            }
        }

        throw new ToolCallException("Unknown GraphQL schema {$uidOrName}.");
    }

    private function findFirst(): GqlSchema
    {
        $schemas = \Craft::$app->getGql()->getSchemas();
        $first = $schemas[0] ?? null;
        if (!$first instanceof GqlSchema) {
            throw new ToolCallException('This install has no GraphQL schema.');
        }

        return $first;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaRow(GqlSchema $schema): array
    {
        return [
            'id' => $schema->id,
            'uid' => $schema->uid,
            'name' => $schema->name,
            'isPublic' => (bool) $schema->isPublic,
        ];
    }
}
