<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\ReturnPurchase;
use App\Models\Returns;
use App\Models\Sale;
use App\Models\Transfer;

/**
 * Attachments of company documents are not served straight from public/documents (the web server refuses
 * those paths). A file is delivered only to a signed-in member of the owning company, and only when a
 * document of that company, found through the company-scoped model, actually references it.
 */
class DocumentFileController extends Controller
{
    /** folder => [model, column] */
    private const FOLDERS = [
        'sale' => [Sale::class, 'document'],
        'purchase' => [Purchase::class, 'document'],
        'sale_return' => [Returns::class, 'document'],
        'purchase_return' => [ReturnPurchase::class, 'document'],
        'quotation' => [Quotation::class, 'document'],
        'expense' => [Expense::class, 'document'],
        'delivery' => [Delivery::class, 'file'],
        'transfer' => [Transfer::class, 'document'],
        'adjustment' => [Adjustment::class, 'document'],
        'add-payment' => [Payment::class, 'document'],
    ];

    public function show(string $folder, string $file)
    {
        abort_unless(isset(self::FOLDERS[$folder]) && $file === basename($file), 404);
        [$model, $column] = self::FOLDERS[$folder];
        abort_unless($model::query()->where($column, $file)->exists(), 404);
        $path = public_path('documents/'.$folder.'/'.$file);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }
}
