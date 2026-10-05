<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\SendNotification;
use Auth;
use Illuminate\Support\Facades\Validator;
use DB;
use Spatie\Permission\Models\Role;
use App\Services\Commercial\CommercialPermission;
use App\Services\Documents\NotificationFileAccess;
use App\Services\Documents\PrivateFileStorage;
use App\Services\Platform\CompanyContextResolver;
use App\Services\Platform\BranchAccess;

class NotificationController extends Controller
{
    public function index()
    {
        $context = app(CompanyContextResolver::class)->forActor();
        app(CommercialPermission::class)->assert('all_notification', $context, Auth::id());
        $lims_notification_all = DB::table('notifications')->get()
            ->filter(fn ($row) => app(NotificationFileAccess::class)->canRead($row, $context, Auth::id()));
        return view('backend.notification.index', compact('lims_notification_all'));
    }
    public function store(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        app(CommercialPermission::class)->assert('send_notification', $context, Auth::id());
        $request->validate([
            'receiver_id' => 'required|integer', 'message' => 'required|string',
            'reminder_date' => 'required|date', 'document' => 'nullable|file|max:10240',
        ]);
        $user = User::whereKey($request->receiver_id)->where('is_active', true)->where('is_deleted', false)->firstOrFail();
        abort_unless(DB::table('company_user')->where('company_id', $context->companyId)->where('user_id', $user->id)->exists(), 403);
        abort_unless(in_array($context->branchId, app(BranchAccess::class)->authorizedBranchIds($context, $user->id), true), 403);
        $request->merge(['sender_id' => Auth::id(), 'company_id' => $context->companyId,
            'branch_id' => $context->branchId, 'document_name' => null]);
        $document = $request->document;
        if($document) {
            $v = Validator::make(
                [
                    'extension' => strtolower($document->getClientOriginalExtension()),
                ],
                [
                    'extension' => 'in:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
                ]
            );
            if ($v->fails())
                return redirect()->back()->withErrors($v->errors());

            $request->merge(['document_name' => app(PrivateFileStorage::class)->store($document, 'notification')]);
        }
        try {
            $user->notify(new SendNotification($request));
        } catch (\Throwable $exception) {
            app(PrivateFileStorage::class)->delete('notification', $request->document_name);
            throw $exception;
        }
    	return redirect()->back()->with('message', __('db.Notification send successfully'));
    }

    public function markAsRead()
    {
    	Auth::user()->unreadNotifications->where('data.reminder_date', date('Y-m-d'))->markAsRead();
    }
}
