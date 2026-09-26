<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaOutboxContractTest extends TestCase
{
    public function test_outbox_is_allowlisted_scoped_and_bounded(): void
    {
        $store = file_get_contents(base_path('resources/js/features/pwa/outbox-store.js'));
        $drafts = file_get_contents(base_path('resources/js/features/pwa/draft-store.js'));

        self::assertStringContainsString('DATABASE_VERSION = 3', $drafts);
        self::assertStringContainsString("OUTBOX_STORE = 'outbox'", $drafts);
        self::assertStringContainsString("new Set(['inventory-count-draft'])", $store);
        self::assertStringContainsString('ALLOWED_PATH', $store);
        self::assertStringContainsString('MAX_OUTBOX_ITEMS_PER_ACCOUNT = 25', $store);
        self::assertStringContainsString('MAX_PAYLOAD_BYTES = 256 * 1024', $store);
        self::assertStringNotContainsString('Authorization', $store);
    }

    public function test_inventory_draft_sync_is_idempotent_conflict_aware_and_logout_safe(): void
    {
        $sync = file_get_contents(base_path('resources/js/features/pwa/outbox-sync.js'));
        $inventory = file_get_contents(base_path('resources/js/features/accountant/inventory-count.js'));
        $lifecycle = file_get_contents(base_path('resources/js/features/pwa/client-storage-lifecycle.js'));
        $routes = file_get_contents(base_path('routes/accountant.php'));

        self::assertStringContainsString('idempotencyKey', $sync);
        self::assertStringContainsString("status: conflict ? 'conflict' : 'failed'", $sync);
        self::assertStringContainsString('item.attempts < 5', $sync);
        self::assertStringContainsString('session_version', $inventory);
        self::assertStringContainsString('deleteOutboxForAccount', $lifecycle);
        self::assertStringContainsString("middleware('idempotency')->name('items.bulk-update')", $routes);
        self::assertStringContainsString("name=\"_idempotency_key\"", file_get_contents(base_path('resources/views/inventory-counts/accountant/show.blade.php')));
    }
}
