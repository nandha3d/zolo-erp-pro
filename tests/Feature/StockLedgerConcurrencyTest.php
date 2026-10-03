<?php

namespace Tests\Feature;

use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\InventoryLedgerTestCase;

class StockLedgerConcurrencyTest extends InventoryLedgerTestCase
{
    public function test_concurrent_last_unit_issues_exactly_one_succeeds_when_negative_stock_is_blocked(): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Row-lock concurrency requires ERP_TEST_MYSQL=1 and a disposable MySQL database.');
        }
        $product = $this->product();
        $this->receive($product, 1, 10);

        $startAt = microtime(true) + 3;
        $workers = [];
        foreach (range(1, 4) as $ignored) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/stock_issue_worker.php'), (string) $product->id, (string) $startAt]);
            $process->setTimeout(120);
            $process->start();
            $workers[] = $process;
        }
        foreach ($workers as $process) {
            $process->wait();
        }

        $succeeded = array_filter($workers, fn (Process $process) => $process->isSuccessful());
        $this->assertCount(1, $succeeded, implode("\n", array_map(fn ($p) => $p->getErrorOutput(), $workers)));
        foreach (array_diff_key($workers, $succeeded) as $process) {
            $this->assertStringContainsString('Insufficient stock', $process->getErrorOutput());
        }
        $this->assertSame(1, StockMovement::where('movement_type', 'issue')->count());
        $this->assertEquals(0, DB::table('product_warehouse')->where('product_id', $product->id)->sum('qty'));
        $this->assertEquals(0, DB::table('products')->where('id', $product->id)->value('qty'));
    }
}
