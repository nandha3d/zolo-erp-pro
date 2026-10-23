<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Master register rows are referenced by id from bills, challans and parties. Deleting a used row
 * would leave those documents pointing at nothing, so the delete is refused and the user is told
 * to mark the row inactive instead.
 */
trait BlocksDeleteOfUsedMaster
{
    /**
     * @param  array<string, string>  $references  table => column holding this master's id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|null  a refusal, or null when unused
     */
    protected function refuseDeleteIfUsed(Request $request, int $id, array $references, string $label)
    {
        foreach ($references as $table => $column) {
            if (!DB::table($table)->where($column, $id)->exists()) {
                continue;
            }
            $message = $label.' is used on existing '.str_replace('_', ' ', $table).'. Edit it and untick Active instead of deleting.';

            return $request->ajax() || $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : redirect()->back()->with('not_permitted', $message);
        }

        return null;
    }
}
