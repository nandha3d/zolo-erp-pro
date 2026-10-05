<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Platform\CompanyContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Seeded MySQL proof that the original business screens run inside one trusted company. */
class LegacyCompanyIsolationWebTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private int $otherCompany;

    private int $otherCustomer;

    private int $otherSale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::findOrFail(1);
        $today = now()->toDateString();
        if (!DB::table('fiscal_years')->where('company_id', 1)->where('start_date', '<=', $today)->where('end_date', '>=', $today)->exists()) {
            DB::table('fiscal_years')->insert(['company_id' => 1, 'name' => 'Isolation FY', 'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(), 'is_closed' => 0, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        }
        $company = (array) DB::table('companies')->find(1);
        unset($company['id']);
        $this->otherCompany = DB::table('companies')->insertGetId(['code' => 'OTHER', 'legal_name' => 'Other Co', 'trade_name' => 'Other Co'] + $company);
        $customer = (array) DB::table('customers')->find(1);
        unset($customer['id']);
        $this->otherCustomer = DB::table('customers')->insertGetId(['name' => 'Foreign customer', 'company_id' => $this->otherCompany] + $customer);
        $this->otherSale = DB::table('sales')->insertGetId([
            'reference_no' => 'FOREIGN-1', 'company_id' => $this->otherCompany, 'customer_id' => $this->otherCustomer, 'user_id' => 1,
            'warehouse_id' => 1, 'biller_id' => 1, 'item' => 0, 'total_qty' => 0, 'total_discount' => 0, 'total_tax' => 0, 'total_price' => 0,
            'grand_total' => 0, 'sale_status' => 1, 'payment_status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_request_scope_hides_other_company_rows_and_stamps_new_ones(): void
    {
        $context = new CompanyContext(1, (int) DB::table('company_branches')->where('company_id', 1)->value('id'), (int) DB::table('fiscal_years')->where('company_id', 1)->value('id'));
        $this->assertNotNull(Customer::find($this->otherCustomer), 'without a request context nothing is scoped');

        request()->attributes->set(CompanyContext::class, $context);
        try {
            $this->assertNull(Customer::find($this->otherCustomer));
            $this->assertNull(Sale::find($this->otherSale));
            $this->assertSame(0, Customer::where('company_id', $this->otherCompany)->count());
            $created = Customer::create(['name' => 'Scoped new customer', 'customer_group_id' => 1, 'phone_number' => '000', 'is_active' => 1]);
            $this->assertSame(1, (int) $created->company_id);
            $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
            (new Customer)->forceFill(['name' => 'Forged owner', 'customer_group_id' => 1, 'phone_number' => '000', 'is_active' => 1, 'company_id' => $this->otherCompany])->save();
        } finally {
            request()->attributes->remove(CompanyContext::class);
        }
    }

    public function test_raw_table_queries_follow_the_request_company_too(): void
    {
        $context = new CompanyContext(1, (int) DB::table('company_branches')->where('company_id', 1)->value('id'), (int) DB::table('fiscal_years')->where('company_id', 1)->value('id'));
        $this->assertNotNull(DB::table('customers')->where('id', $this->otherCustomer)->first(), 'no context, no scope');

        request()->attributes->set(CompanyContext::class, $context);
        try {
            $this->assertNull(DB::table('customers')->where('id', $this->otherCustomer)->first());
            $this->assertFalse(DB::table('customers')->where('id', $this->otherCustomer)->exists());
            $this->assertSame(0, DB::table('sales')->where('id', $this->otherSale)->count());
            // Joined tables are limited inside their ON clause, so the left side survives but the foreign side vanishes.
            $joined = DB::table('sales as s')->leftJoin('customers as c', 'c.id', '=', 's.customer_id')->count();
            $this->assertSame(DB::table('sales')->count(), $joined);
            $this->assertNull(DB::table('customers as c')->join('sales as s', 's.customer_id', '=', 'c.id')->where('s.id', $this->otherSale)->first());
            $this->assertSame(0, DB::table('customers')->where('id', $this->otherCustomer)->update(['name' => 'hijacked']));
            $this->assertSame(0, DB::table('customers')->where('id', $this->otherCustomer)->delete());
        } finally {
            request()->attributes->remove(CompanyContext::class);
        }
        $this->assertSame('Foreign customer', DB::table('customers')->where('id', $this->otherCustomer)->value('name'));
    }

    public function test_business_pages_reject_a_company_the_user_does_not_belong_to(): void
    {
        $this->actingAs($this->admin)->get(route('sales.index'), ['X-Company-ID' => (string) $this->otherCompany])->assertForbidden();
    }

    public function test_foreign_sale_cannot_be_opened_deleted_or_used_by_id(): void
    {
        $this->actingAs($this->admin);
        $this->assertNotSame(200, $this->get(route('sales.edit', $this->otherSale))->getStatusCode());
        $this->delete(route('sales.destroy', $this->otherSale));
        $this->assertNull(DB::table('sales')->where('id', $this->otherSale)->value('deleted_at'), 'a foreign sale must not be soft-deleted');
    }

    public function test_new_user_joins_the_creators_company(): void
    {
        $this->actingAs($this->admin)->post('user', [
            'name' => 'Isolation tester', 'email' => 'isolation@example.invalid', 'password' => 'secret-pass-1', 'phone_number' => '000',
            'company_name' => 'x', 'role_id' => 1, 'is_active' => 1, 'biller_id' => 1, 'warehouse_id' => 1,
        ]);
        $user = DB::table('users')->where('email', 'isolation@example.invalid')->first();
        $this->assertNotNull($user);
        $this->assertTrue(DB::table('company_user')->where('company_id', 1)->where('user_id', $user->id)->exists());
        $this->assertTrue(DB::table('company_user_branches')->where('company_id', 1)->where('user_id', $user->id)->exists());
    }
}
