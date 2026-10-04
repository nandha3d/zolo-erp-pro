<?php

namespace App\Events;

final readonly class CommercialDocumentPosted
{
    public function __construct(public string $kind, public int $documentId, public int $companyId, public int $branchId) {}
}
