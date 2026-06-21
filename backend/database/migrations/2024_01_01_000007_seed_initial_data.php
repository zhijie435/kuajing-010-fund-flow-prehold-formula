<?php

use App\Services\Database;

class SeedInitialData
{
    public function up(Database $db): void
    {
        $stmt = $db->query("SELECT COUNT(*) as count FROM extensions");
        $count = $stmt->fetch()['count'];

        if ($count == 0) {
            $extensions = [
                ['name' => 'order-core', 'description' => '订单核心扩展包', 'version' => '2.1.0', 'vendor' => 'ecommerce'],
                ['name' => 'inventory-sync', 'description' => '库存同步扩展包', 'version' => '1.5.2', 'vendor' => 'ecommerce'],
                ['name' => 'payment-gateway', 'description' => '支付网关扩展包', 'version' => '3.0.0', 'vendor' => 'payment'],
                ['name' => 'shipping-service', 'description' => '物流服务扩展包', 'version' => '1.2.1', 'vendor' => 'logistics'],
                ['name' => 'user-center', 'description' => '用户中心扩展包', 'version' => '2.0.0', 'vendor' => 'user'],
            ];

            foreach ($extensions as $ext) {
                $stmt = $db->query(
                    "INSERT INTO extensions (name, description, version, vendor) VALUES (?, ?, ?, ?)",
                    [$ext['name'], $ext['description'], $ext['version'], $ext['vendor']]
                );
            }

            $migrations = [
                [1, '2024_01_01_000001_create_orders_table.php', '2.1.0', '创建订单主表', 1, 'database/migrations'],
                [1, '2024_01_02_000002_create_order_items_table.php', '2.1.0', '创建订单明细表', 1, 'database/migrations'],
                [1, '2024_01_15_000003_add_order_status_index.php', '2.1.0', '添加订单状态索引', 2, 'database/migrations'],
                [2, '2024_02_01_000001_create_inventory_table.php', '1.5.0', '创建库存表', 1, 'database/migrations'],
                [2, '2024_02_10_000002_add_inventory_lock_field.php', '1.5.2', '添加库存锁定字段', 2, 'database/migrations'],
                [3, '2024_03_01_000001_create_payments_table.php', '3.0.0', '创建支付记录表', 1, 'database/migrations'],
                [3, '2024_03_15_000002_add_payment_callback_table.php', '3.0.0', '添加支付回调表', 1, 'database/migrations'],
                [4, '2024_04_01_000001_create_shipments_table.php', '1.2.0', '创建物流表', 1, 'database/migrations'],
                [5, '2024_05_01_000001_create_users_table.php', '2.0.0', '创建用户表', 1, 'database/migrations'],
                [5, '2024_05_10_000002_add_user_level_field.php', '2.0.0', '添加用户等级字段', 2, 'database/migrations'],
            ];

            foreach ($migrations as $m) {
                $stmt = $db->query(
                    "INSERT INTO migrations (extension_id, filename, version, description, batch, migrate_path, status) VALUES (?, ?, ?, ?, ?, ?, 1)",
                    $m
                );
            }

            $services = [
                ['name' => 'order-service', 'description' => '订单服务', 'service_type' => 'micro_service', 'endpoint' => 'http://order-service/api'],
                ['name' => 'inventory-service', 'description' => '库存服务', 'service_type' => 'micro_service', 'endpoint' => 'http://inventory-service/api'],
                ['name' => 'payment-service', 'description' => '支付服务', 'service_type' => 'micro_service', 'endpoint' => 'http://payment-service/api'],
                ['name' => 'shipping-service', 'description' => '物流服务', 'service_type' => 'micro_service', 'endpoint' => 'http://shipping-service/api'],
                ['name' => 'user-service', 'description' => '用户服务', 'service_type' => 'micro_service', 'endpoint' => 'http://user-service/api'],
                ['name' => 'message-queue', 'description' => '消息队列服务', 'service_type' => 'mq', 'endpoint' => 'kafka://localhost:9092'],
                ['name' => 'cache-service', 'description' => '缓存服务', 'service_type' => 'cache', 'endpoint' => 'redis://localhost:6379'],
            ];

            foreach ($services as $s) {
                $stmt = $db->query(
                    "INSERT INTO services (name, description, service_type, endpoint) VALUES (?, ?, ?, ?)",
                    [$s['name'], $s['description'], $s['service_type'], $s['endpoint']]
                );
            }

            $binds = [
                [1, 1, 'direct', 1, '{"readonly":true}'],
                [2, 1, 'direct', 2, '{"readonly":true}'],
                [3, 1, 'direct', 3, '{"readonly":false}'],
                [4, 2, 'direct', 1, '{"sync":"realtime"}'],
                [5, 2, 'direct', 2, '{"sync":"realtime"}'],
                [6, 3, 'direct', 1, '{}'],
                [7, 3, 'direct', 2, '{}'],
                [8, 4, 'direct', 1, '{}'],
                [9, 5, 'direct', 1, '{}'],
                [10, 5, 'direct', 2, '{}'],
                [1, 6, 'event', 10, '{"topic":"order_created"}'],
                [4, 6, 'event', 10, '{"topic":"inventory_updated"}'],
                [6, 6, 'event', 10, '{"topic":"payment_success"}'],
                [1, 7, 'cache', 20, '{"prefix":"order:"}'],
                [4, 7, 'cache', 20, '{"prefix":"inventory:"}'],
            ];

            foreach ($binds as $b) {
                $stmt = $db->query(
                    "INSERT INTO migration_service_bind (migration_id, service_id, bind_type, priority, config) VALUES (?, ?, ?, ?, ?)",
                    $b
                );
            }

            $ledgerRecords = [
                ['extension', 1, null, null, null, '创建扩展包', 'admin', '初始化订单核心扩展包'],
                ['extension', 2, null, null, null, '创建扩展包', 'admin', '初始化库存同步扩展包'],
                ['migration', 1, 1, null, null, '添加迁移文件', 'admin', '创建订单主表迁移'],
                ['migration', 1, 2, null, null, '添加迁移文件', 'admin', '创建订单明细迁移'],
                ['bind', 1, 1, 1, 1, '绑定服务', 'developer', '迁移文件绑定订单服务'],
                ['bind', 1, 1, 6, 11, '绑定服务', 'developer', '迁移文件绑定消息队列'],
            ];

            foreach ($ledgerRecords as $r) {
                $stmt = $db->query(
                    "INSERT INTO ledger_records (record_type, extension_id, migration_id, service_id, bind_id, action, operator, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    $r
                );
            }
        }
    }

    public function down(Database $db): void
    {
    }

    public function batch(): int
    {
        return 99;
    }

    public function description(): string
    {
        return '初始化示例数据（扩展包、迁移文件、服务、绑定关系、台账记录）';
    }
}
