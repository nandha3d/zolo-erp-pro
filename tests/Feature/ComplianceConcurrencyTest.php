<?php

namespace Tests\Feature;

use App\Services\Commercial\ReturnService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\ReturnsDocumentTestCase;

class ComplianceConcurrencyTest extends ReturnsDocumentTestCase
{
    public function test_concurrent_approvals_cannot_over_return_or_post_the_same_note_twice(): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') $this->markTestSkipped('Return row-lock proof requires an opted-in disposable MySQL database.');
        $sale = $this->gstSale(); $service = app(ReturnService::class);
        $data = $this->noteData($sale, ['items' => [['line_id' => $sale->productSales->first()->id, 'qty' => 2]]]);
        $one = $service->create('sale', $sale->id, $data, 'race-one', $this->context(), 1);
        $two = $service->create('sale', $sale->id, $data, 'race-two', $this->context(), 1);
        $results = $this->race([$one->id, $two->id]);
        $success = array_values(array_filter($results, fn ($r) => $r['posted']));
        $this->assertCount(1, $success);
        $failed = array_values(array_filter($results, fn ($r) => !$r['posted']));
        $this->assertNotEmpty(array_intersect(['qty', 'amount'], $failed[0]['fields']));
        foreach ($this->race([$success[0]['id'], $success[0]['id']]) as $r) $this->assertTrue($r['posted']);
        $this->assertSame(2, DB::table('journal_entries')->count());
        $this->assertSame(2, DB::table('stock_movements')->count());
        $this->assertSame(1, DB::table('returns')->whereNotNull('posted_at')->count());
        $this->assertEquals(0, DB::table('account_open_items')->sum('open_amount'));
        $this->assertEquals(20, DB::table('products')->value('qty'));
    }

    private function race(array $ids): array
    {
        $start = microtime(true) + 2; $processes = [];
        foreach ($ids as $id) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/return_approval_worker.php'), (string) $this->company->id,
                (string) $this->branch->id, (string) $this->year->id, (string) $id, (string) $start]);
            $process->setTimeout(60); $process->start(); $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    }
}
