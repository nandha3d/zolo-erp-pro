<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fieldUsers = [
            [
                'name' => 'driver',
                'email' => 'driver@dkenterprises.animazon.in',
                'password' => Hash::make('driver123'),
                'phone' => '9842100010',
                'role_id' => 4,
                'company_id' => 3, // DK Waters
            ],
            [
                'name' => 'tanker_driver',
                'email' => 'tanker@dkenterprises.animazon.in',
                'password' => Hash::make('driver123'),
                'phone' => '9842100011',
                'role_id' => 4,
                'company_id' => 2, // TMWS
            ],
            [
                'name' => 'agent',
                'email' => 'agent@dkenterprises.animazon.in',
                'password' => Hash::make('agent123'),
                'phone' => '9842100012',
                'role_id' => 4,
                'company_id' => 3, // DK Waters
            ],
            [
                'name' => 'cashier',
                'email' => 'cashier@dkenterprises.animazon.in',
                'password' => Hash::make('cashier123'),
                'phone' => '9842100013',
                'role_id' => 4,
                'company_id' => 1, // Aran Cafe
            ],
        ];

        foreach ($fieldUsers as $u) {
            $user = User::where('name', $u['name'])->orWhere('email', $u['email'])->first();
            if (!$user) {
                $userId = DB::table('users')->insertGetId([
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'password' => $u['password'],
                    'phone' => $u['phone'],
                    'role_id' => $u['role_id'],
                    'is_active' => true,
                    'is_deleted' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $userId = $user->id;
                $user->update([
                    'password' => $u['password'],
                    'is_active' => true,
                    'is_deleted' => false,
                ]);
            }

            // Resolve target company (fallback to primary company if specific company doesn't exist)
            $targetCompanyId = DB::table('companies')->where('id', $u['company_id'])->exists()
                ? $u['company_id']
                : DB::table('companies')->value('id');

            // Assign to company_user
            if ($targetCompanyId && Schema::hasTable('company_user')) {
                DB::table('company_user')->updateOrInsert(
                    ['company_id' => $targetCompanyId, 'user_id' => $userId],
                    ['is_default' => true, 'updated_at' => now()]
                );
            }

            // Assign to company branch
            if ($targetCompanyId && Schema::hasTable('company_branches') && Schema::hasTable('company_user_branches')) {
                $branchId = DB::table('company_branches')
                    ->where('company_id', $targetCompanyId)
                    ->where('is_active', true)
                    ->value('id');

                if ($branchId) {
                    DB::table('company_user_branches')->updateOrInsert(
                        ['company_id' => $targetCompanyId, 'user_id' => $userId, 'branch_id' => $branchId],
                        []
                    );
                }
            }
        }
    }

    public function down(): void
    {
        $names = ['driver', 'tanker_driver', 'agent', 'cashier'];
        $userIds = DB::table('users')->whereIn('name', $names)->pluck('id');

        DB::table('company_user_branches')->whereIn('user_id', $userIds)->delete();
        DB::table('company_user')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }
};

