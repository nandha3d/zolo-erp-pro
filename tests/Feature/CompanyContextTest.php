<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveCompanyContext;
use App\Models\Accounting\FiscalYear;
use App\Models\Company;
use App\Models\CompanyBranch;
use App\Models\User;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ErpServiceTestCase;

class CompanyContextTest extends ErpServiceTestCase
{
    private Company $company;
    private Company $other;
    private CompanyBranch $branch;
    private CompanyBranch $otherBranch;
    private FiscalYear $year;
    private FiscalYear $otherYear;
    private CompanyContextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-03 06:00:00 UTC');
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
        });
        DB::table('users')->insert([['id' => 1, 'name' => 'First operator'], ['id' => 2, 'name' => 'Other operator']]);
        (require database_path('migrations/2026_10_03_000001_create_company_context_tables.php'))->up();
        (require database_path('migrations/2026_10_03_000002_add_nullable_company_keys_to_core_tables.php'))->up();

        $this->company = Company::create(['code' => 'A', 'legal_name' => 'Company A', 'timezone' => 'Asia/Kolkata']);
        $this->other = Company::create(['code' => 'B', 'legal_name' => 'Company B', 'timezone' => 'UTC']);
        $this->company->users()->attach(1, ['is_default' => true]);
        $this->other->users()->attach(2, ['is_default' => true]);
        $this->branch = $this->company->branches()->create(['code' => 'MAIN', 'name' => 'First branch']);
        $this->otherBranch = $this->other->branches()->create(['code' => 'MAIN', 'name' => 'Other branch']);
        DB::table('company_user_branches')->insert([
            ['company_id' => $this->company->id, 'user_id' => 1, 'branch_id' => $this->branch->id],
            ['company_id' => $this->other->id, 'user_id' => 2, 'branch_id' => $this->otherBranch->id],
        ]);
        $this->year = FiscalYear::create([
            'company_id' => $this->company->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
        ]);
        $this->otherYear = FiscalYear::create([
            'company_id' => $this->other->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
        ]);
        $this->resolver = new CompanyContextResolver();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_default_context_uses_authorized_company_branch_and_existing_year(): void
    {
        $context = $this->resolver->resolve(1);
        $this->assertSame($this->company->id, $context->companyId);
        $this->assertSame($this->branch->id, $context->branchId);
        $this->assertSame($this->year->id, $context->financialYearId);
        $this->assertSame('2026-01-01', $this->year->fresh()->start_date->toDateString());
    }

    public function test_spoofed_company_is_rejected(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->resolver->resolve(1, $this->other->id);
    }

    public function test_other_company_branch_is_rejected(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->resolver->resolve(1, $this->company->id, $this->otherBranch->id);
    }

    public function test_unassigned_branch_in_same_company_is_rejected(): void
    {
        $restricted = $this->company->branches()->create(['code' => 'RESTRICTED', 'name' => 'Restricted branch']);
        $this->expectException(AuthorizationException::class);
        $this->resolver->resolve(1, $this->company->id, $restricted->id);
    }

    public function test_other_company_financial_year_is_rejected(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->resolver->resolve(1, $this->company->id, $this->branch->id, $this->otherYear->id);
    }

    public function test_inactive_company_is_rejected(): void
    {
        $this->company->update(['status' => 'inactive']);
        $this->expectException(AuthorizationException::class);
        $this->resolver->resolve(1);
    }

    public function test_multiple_default_memberships_require_explicit_selection(): void
    {
        $this->other->users()->attach(1, ['is_default' => true]);
        $this->expectException(ValidationException::class);
        $this->resolver->resolve(1);
    }

    public function test_historical_closed_year_is_readable_but_cannot_receive_postings(): void
    {
        $this->year->update(['is_closed' => true, 'status' => 'closed']);
        $context = $this->resolver->resolve(1, financialYearId: $this->year->id);
        $this->assertSame($this->year->id, $context->financialYearId);
        $this->expectException(ValidationException::class);
        $this->resolver->assertPostingDate($context, '2026-10-03');
    }

    public function test_closing_one_company_does_not_lock_another_company(): void
    {
        $this->year->update(['is_closed' => true, 'status' => 'closed']);
        $context = $this->resolver->resolve(2);
        $this->resolver->assertPostingDate($context, '2026-10-03');
        $this->assertSame($this->other->id, $context->companyId);
    }

    public function test_lock_date_is_inclusive_and_later_dates_are_allowed(): void
    {
        $this->year->update(['lock_date' => '2026-03-31']);
        $context = $this->resolver->resolve(1);
        $this->resolver->assertPostingDate($context, '2026-04-01');
        $this->expectException(ValidationException::class);
        $this->resolver->assertPostingDate($context, '2026-03-31');
    }

    public function test_soft_closed_year_cannot_receive_postings(): void
    {
        $this->year->update(['status' => 'soft_closed']);
        $context = $this->resolver->resolve(1);
        $this->expectException(ValidationException::class);
        $this->resolver->assertPostingDate($context, '2026-10-03');
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_or_out_of_year_dates_are_rejected(string $date): void
    {
        $context = $this->resolver->resolve(1);
        $this->expectException(ValidationException::class);
        $this->resolver->assertPostingDate($context, $date);
    }

    public static function invalidDates(): array
    {
        return [['1970-01-01'], ['2027-01-01'], ['2026-02-30'], ['03/10/2026'], ['2026-10-03 00:00:00']];
    }

    public function test_overlapping_years_cannot_be_implicitly_selected(): void
    {
        FiscalYear::create([
            'company_id' => $this->company->id, 'name' => 'Overlap',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);
        $this->expectException(ValidationException::class);
        $this->resolver->resolve(1);
    }

    public function test_middleware_exposes_context_only_during_request_and_ignores_body_ids(): void
    {
        $request = Request::create('/', 'POST', ['company_id' => $this->other->id]);
        $request->setUserResolver(fn () => User::find(1));
        $middleware = new ResolveCompanyContext($this->resolver);
        $middleware->handle($request, function (Request $request) {
            $this->assertSame($this->company->id, $request->attributes->get(CompanyContext::class)->companyId);
            return response('success');
        });

        $this->assertFalse($request->attributes->has(CompanyContext::class));
    }

    public function test_middleware_clears_context_after_downstream_exception(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => User::find(1));
        try {
            (new ResolveCompanyContext($this->resolver))->handle($request, function () {
                throw new RuntimeException('Downstream failure');
            });
            $this->fail('Request must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Downstream failure', $error->getMessage());
        }
        $this->assertFalse($request->attributes->has(CompanyContext::class));
    }

    public function test_middleware_rejects_malformed_header_without_running_handler(): void
    {
        $request = Request::create('/', server: ['HTTP_X_COMPANY_ID' => '1anything']);
        $request->setUserResolver(fn () => User::find(1));
        $this->expectException(ValidationException::class);
        (new ResolveCompanyContext($this->resolver))->handle($request, fn () => $this->fail('Handler must not run.'));
    }

    public function test_middleware_rejects_unauthorized_header_without_running_handler(): void
    {
        $request = Request::create('/', server: ['HTTP_X_COMPANY_ID' => $this->other->id]);
        $request->setUserResolver(fn () => User::find(1));
        $this->expectException(AuthorizationException::class);
        (new ResolveCompanyContext($this->resolver))->handle($request, fn () => $this->fail('Handler must not run.'));
    }

    public function test_middleware_rejects_inactive_users(): void
    {
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => User::find(1));
        $this->expectException(AuthorizationException::class);
        (new ResolveCompanyContext($this->resolver))->handle($request, fn () => $this->fail('Handler must not run.'));
    }

    public function test_deleted_user_with_active_identity_cannot_resolve_request_context(): void
    {
        DB::table('users')->where('id', 1)->update(['is_active' => true, 'is_deleted' => true]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => User::find(1));
        $this->expectException(AuthorizationException::class);
        (new ResolveCompanyContext($this->resolver))->handle($request, fn () => $this->fail('Handler must not run.'));
    }

    public function test_resolver_itself_rejects_inactive_users(): void
    {
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        $this->resolver->resolve(1);
    }

    public function test_session_context_is_validated_for_web_requests(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => User::find(1));
        $session = new Store('context-test', new ArraySessionHandler(120));
        $session->put([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'financial_year_id' => $this->year->id,
        ]);
        $request->setLaravelSession($session);

        (new ResolveCompanyContext($this->resolver))->handle($request, function (Request $request) {
            $this->assertSame($this->year->id, $request->attributes->get(CompanyContext::class)->financialYearId);
            return response('success');
        });
    }

    public function test_headers_take_precedence_over_session_context(): void
    {
        $this->other->users()->attach(1);
        DB::table('company_user_branches')->insert([
            'company_id' => $this->other->id, 'user_id' => 1, 'branch_id' => $this->otherBranch->id,
        ]);
        $request = Request::create('/', server: [
            'HTTP_X_COMPANY_ID' => $this->company->id, 'HTTP_X_BRANCH_ID' => $this->branch->id,
            'HTTP_X_FINANCIAL_YEAR_ID' => $this->year->id,
        ]);
        $request->setUserResolver(fn () => User::find(1));
        $session = new Store('context-test', new ArraySessionHandler(120));
        $session->put([
            'company_id' => $this->other->id, 'branch_id' => $this->otherBranch->id,
            'financial_year_id' => $this->otherYear->id,
        ]);
        $request->setLaravelSession($session);

        (new ResolveCompanyContext($this->resolver))->handle($request, function (Request $request) {
            $this->assertSame($this->company->id, $request->attributes->get(CompanyContext::class)->companyId);
            return response('success');
        });
    }

    public function test_implicit_year_selection_uses_each_company_timezone(): void
    {
        CarbonImmutable::setTestNow('2026-12-31 21:00:00 UTC');
        $nextYear = FiscalYear::create([
            'company_id' => $this->company->id, 'name' => '2027',
            'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
        ]);

        $this->assertSame($nextYear->id, $this->resolver->resolve(1)->financialYearId);
        $this->assertSame($this->otherYear->id, $this->resolver->resolve(2)->financialYearId);
    }
}
