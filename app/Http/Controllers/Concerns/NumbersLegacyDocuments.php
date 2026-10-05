<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DocumentNumberReservation;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use App\Services\Platform\DocumentNumberService;
use Illuminate\Database\Eloquent\Model;

/**
 * Authoritative document numbers for the original business screens. Numbers come from the company's
 * atomic series (DocumentNumberService) inside the posting transaction, so a failed save rolls the
 * counter back; timestamps, counts and random values are never used.
 */
trait NumbersLegacyDocuments
{
    /** The authorized request context, or the signed-in user's default company/branch/year. */
    protected function documentContext(): CompanyContext
    {
        return app(CompanyContextResolver::class)->forActor(request()->attributes->get(CompanyContext::class), auth()->id());
    }

    /** Call inside the document's transaction, before creating the document. */
    protected function reserveNumber(string $type, mixed $businessDate = null): DocumentNumberReservation
    {
        $date = $businessDate ? \Carbon\Carbon::parse($businessDate)->toDateString() : now()->toDateString();

        return app(DocumentNumberService::class)->reserve($type, $this->documentContext(), $date, (int) auth()->id());
    }

    /** Bind the reserved number to the document that carries it, in the same transaction. */
    protected function assignNumber(DocumentNumberReservation $reservation, Model $document): void
    {
        if ($document->getAttribute('company_id') === null) {
            // Created outside company middleware (e.g. console): the number's company owns the document.
            $document->getConnection()->table($document->getTable())->where($document->getKeyName(), $document->getKey())
                ->update(['company_id' => $reservation->company_id]);
            $document->setAttribute('company_id', $reservation->company_id)->syncOriginalAttribute('company_id');
        }
        app(DocumentNumberService::class)->assign($reservation, $document);
    }
}
