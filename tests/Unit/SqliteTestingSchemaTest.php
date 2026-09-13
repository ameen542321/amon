<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SqliteTestingSchemaTest extends TestCase
{
    public function test_purchase_order_limit_settings_matches_the_current_migration_contract(): void
    {
        $schema = file_get_contents(dirname(__DIR__, 2).'/database/testing/sqlite-schema.sql');

        self::assertIsString($schema);
        self::assertStringContainsString('CREATE TABLE "purchase_order_limit_settings"', $schema);
        self::assertStringContainsString('"weekly_limit" INTEGER NOT NULL DEFAULT 4', $schema);
        self::assertStringContainsString('"counted_statuses" TEXT NOT NULL', $schema);
        self::assertStringContainsString('"exception_weekly_limit" INTEGER DEFAULT NULL', $schema);
        self::assertStringContainsString('"exception_expires_at" timestamp NULL DEFAULT NULL', $schema);
        self::assertStringContainsString('"exception_reason" text DEFAULT NULL', $schema);
        self::assertStringContainsString('"exception_admin_id" INTEGER DEFAULT NULL', $schema);
        self::assertStringContainsString('purchase_order_limit_settings_store_id_unique', $schema);
        self::assertStringContainsString('ON DELETE SET NULL', $schema);
    }

    public function test_credit_collection_note_is_available_with_the_required_limit(): void
    {
        $schema = file_get_contents(dirname(__DIR__, 2).'/database/testing/sqlite-schema.sql');

        self::assertIsString($schema);
        self::assertMatchesRegularExpression(
            '/CREATE TABLE "employee_credit_collections"[\s\S]*?"note" varchar\(30\) DEFAULT NULL[\s\S]*?;/',
            $schema
        );
    }
}
