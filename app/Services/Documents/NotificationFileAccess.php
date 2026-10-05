<?php

namespace App\Services\Documents;

use App\Models\User;
use App\Services\Commercial\CommercialPermission;
use App\Services\Platform\BranchAccess;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;

class NotificationFileAccess
{
    public function belongsToCompany(object $notification, CompanyContext $context): bool
    {
        $data = json_decode($notification->data, true);
        if (!is_array($data) || $notification->notifiable_type !== User::class
            || (int) ($data['receiver_id'] ?? 0) !== (int) $notification->notifiable_id) {
            return false;
        }
        $sender = (int) ($data['sender_id'] ?? 0);
        $recipient = (int) $notification->notifiable_id;
        $commonCompanies = DB::table('company_user')->where('user_id', $sender)
            ->whereIn('company_id', DB::table('company_user')->select('company_id')->where('user_id', $recipient))
            ->pluck('company_id');
        if (isset($data['company_id'])) {
            return (int) $data['company_id'] === $context->companyId
                && $commonCompanies->contains($context->companyId);
        }
        // Do not assign an ambiguous old message to whichever company happens to be selected.
        return $commonCompanies->count() === 1 && (int) $commonCompanies->sole() === $context->companyId;
    }

    public function canRead(object $notification, CompanyContext $context, int $actor): bool
    {
        if (!$this->belongsToCompany($notification, $context)) {
            return false;
        }
        $data = json_decode($notification->data, true);
        if (!empty($data['branch_id'])
            && !in_array((int) $data['branch_id'], app(BranchAccess::class)->authorizedBranchIds($context, $actor), true)) {
            return false;
        }
        // A recipient has personal read permission. Reviewing other recipients requires the existing audit permission.
        return (int) $notification->notifiable_id === $actor
            || app(CommercialPermission::class)->allows('all_notification', $context, $actor);
    }
}
