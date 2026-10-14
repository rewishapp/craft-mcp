<?php

namespace rewish\craft\mcp\tools;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use rewish\craft\mcp\attributes\ToolMeta;
use rewish\craft\mcp\services\Activity;
use rewish\craft\mcp\support\Page;

final class CommerceTools
{
    public static function isAvailable(): bool
    {
        return \Craft::$app->getPlugins()->isPluginInstalled('commerce');
    }

    #[McpTool(name: 'list_product_types', description: 'List Commerce product types.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'commerce')]
    public function listProductTypes(): array
    {
        return Activity::run('list_product_types', static function () {
            $types = [];
            foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $type) {
                $types[] = ['id' => $type->id, 'handle' => $type->handle, 'name' => $type->name];
            }

            return ['count' => \count($types), 'productTypes' => $types];
        });
    }

    #[McpTool(name: 'list_products', description: 'List Commerce products.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'commerce', minScope: 'full')]
    public function listProducts(?string $type = null, int $limit = 25): array
    {
        return Activity::run('list_products', static function () use ($type, $limit) {
            $query = Product::find()->limit(Page::limit($limit));
            if ($type) {
                $query->type($type);
            }
            $rows = [];
            foreach ($query->all() as $product) {
                $rows[] = ['id' => $product->id, 'title' => $product->title, 'slug' => $product->slug, 'type' => $product->getType()?->handle];
            }

            return ['count' => \count($rows), 'products' => $rows];
        });
    }

    #[McpTool(name: 'get_product', description: 'Get one Commerce product and its variants.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'commerce', minScope: 'full')]
    public function getProduct(int $id): array
    {
        return Activity::run('get_product', static function () use ($id) {
            $product = Product::find()->id($id)->one();
            if ($product === null) {
                throw new ToolCallException('No product matched.');
            }
            $variants = [];
            foreach ($product->getVariants() as $variant) {
                $variants[] = ['id' => $variant->id, 'sku' => $variant->sku, 'price' => $variant->price, 'stock' => $variant->stock];
            }

            return [
                'found' => true,
                'product' => ['id' => $product->id, 'title' => $product->title, 'slug' => $product->slug],
                'variants' => $variants,
            ];
        });
    }

    #[McpTool(name: 'list_order_statuses', description: 'List Commerce order statuses.', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    #[ToolMeta(category: 'commerce')]
    public function listOrderStatuses(): array
    {
        return Activity::run('list_order_statuses', static function () {
            $rows = [];
            foreach (Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $status) {
                $rows[] = ['id' => $status->id, 'handle' => $status->handle, 'name' => $status->name];
            }

            return ['count' => \count($rows), 'statuses' => $rows];
        });
    }

    #[McpTool(name: 'list_orders', description: 'List Commerce orders.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'commerce', minScope: 'full')]
    public function listOrders(?string $status = null, int $limit = 25): array
    {
        return Activity::run('list_orders', static function () use ($status, $limit) {
            $query = Order::find()->isCompleted(true)->limit(Page::limit($limit));
            if ($status) {
                $query->orderStatus($status);
            }
            $rows = [];
            foreach ($query->all() as $order) {
                $rows[] = ['id' => $order->id, 'number' => $order->number, 'email' => $order->email, 'totalPrice' => $order->totalPrice];
            }

            return ['count' => \count($rows), 'orders' => $rows];
        });
    }

    #[McpTool(name: 'get_order', description: 'Get one Commerce order by id or number.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[ToolMeta(category: 'commerce', minScope: 'full')]
    public function getOrder(?int $id = null, ?string $number = null): array
    {
        return Activity::run('get_order', static function () use ($id, $number) {
            $query = Order::find();
            if ($id) {
                $query->id($id);
            } elseif ($number) {
                $query->number($number);
            } else {
                throw new ToolCallException('Pass an order id or number.');
            }
            $order = $query->one();
            if ($order === null) {
                throw new ToolCallException('No order matched.');
            }

            return [
                'found' => true,
                'order' => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'email' => $order->email,
                    'totalPrice' => $order->totalPrice,
                    'dateOrdered' => $order->dateOrdered?->format(\DATE_ATOM),
                ],
            ];
        });
    }
}
