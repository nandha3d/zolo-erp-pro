<?php

namespace Tests\Feature;

use App\Http\Middleware\Common;
use App\Models\User;
use App\Services\Documents\PrivateFileStorage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CompanyContextTestCase;

class PrivateFileIsolationTest extends CompanyContextTestCase
{
    private array $files = [];
    private int $restrictedBranch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(Common::class);
        DB::table('warehouses')->where('id', 1)->update(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
        DB::table('warehouses')->where('id', 2)->update(['company_id' => $this->other->id, 'branch_id' => $this->otherBranch->id]);
        $this->restrictedBranch = $this->company->branches()->create(['code' => 'NORTH', 'name' => 'Restricted'])->id;
        DB::table('warehouses')->insert(['id' => 3, 'name' => 'Restricted stock', 'company_id' => $this->company->id, 'branch_id' => $this->restrictedBranch]);
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('permission_id');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('productions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('warehouse_id');
            $table->string('document');
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('company_id');
            $table->integer('warehouse_id')->nullable();
            $table->string('image');
            $table->boolean('is_active')->default(true);
        });
        $this->actingAs(User::findOrFail(1));
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_company_member_can_read_only_owned_live_document_and_guest_cannot(): void
    {
        $file = $this->file('documents/sale');
        $sale = $this->sale($file);
        $served = $this->getJson($this->url('sale', $file), $this->headers());
        $served->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('private fixture', file_get_contents($served->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString('attachment', $served->headers->get('Content-Disposition'));
        $this->actingAs(User::findOrFail(2))->getJson($this->url('sale', $file))->assertNotFound();
        $this->getJson($this->url('sale', $file), $this->headers())->assertForbidden();
        auth()->forgetGuards();
        $this->getJson($this->url('sale', $file))->assertUnauthorized();
        $this->actingAs(User::findOrFail(1));
        DB::table('sales')->where('id', $sale)->update(['deleted_at' => now()]);
        $this->getJson($this->url('sale', $file), $this->headers())->assertNotFound();
        $this->getJson($this->url('sale', $this->file('documents/sale')), $this->headers())->assertNotFound();
    }

    public function test_same_company_branch_files_require_grant_and_admin_does_not_bypass_grants(): void
    {
        $file = $this->file('documents/sale');
        $this->sale($file, 3);
        $this->getJson($this->url('sale', $file), $this->headers())->assertNotFound();
        DB::table('company_user_branches')->insert(['company_id' => $this->company->id, 'branch_id' => $this->restrictedBranch, 'user_id' => 1]);
        $this->getJson($this->url('sale', $file), $this->headers())->assertOk();
    }

    public function test_module_read_permission_is_required(): void
    {
        $file = $this->file('documents/sale');
        $this->sale($file);
        DB::table('users')->where('id', 1)->update(['role_id' => 4]);
        $this->actingAs(User::findOrFail(1));
        $this->getJson($this->url('sale', $file), $this->headers())->assertForbidden();
        DB::table('permissions')->insert(['id' => 1, 'name' => 'sales-index']);
        DB::table('role_has_permissions')->insert(['permission_id' => 1, 'role_id' => 4]);
        $this->getJson($this->url('sale', $file), $this->headers())->assertOk();
    }

    public function test_notification_personal_permission_cannot_read_another_recipient_or_company(): void
    {
        DB::table('users')->insert(['id' => 3, 'name' => 'Sender', 'role_id' => 4]);
        $this->company->users()->attach(3);
        DB::table('company_user_branches')->insert(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'user_id' => 3]);
        DB::table('users')->where('id', 1)->update(['role_id' => 4]);
        $this->actingAs(User::findOrFail(1));
        $file = $this->file('documents/notification');
        $id = $this->notification($file, 3, 1);
        $this->getJson($this->url('notification', $file), $this->headers())->assertOk();
        $this->actingAs(User::findOrFail(3))->getJson($this->url('notification', $file), $this->headers())->assertNotFound();
        $this->actingAs(User::findOrFail(2))->getJson($this->url('notification', $file))->assertNotFound();
        $this->actingAs(User::findOrFail(1));
        DB::table('notifications')->where('id', $id)->delete();
        $this->getJson($this->url('notification', $file), $this->headers())->assertNotFound();
    }

    public function test_legacy_notification_requires_unambiguous_shared_company_and_recorded_branch_is_enforced(): void
    {
        $file = $this->file('documents/notification');
        $id = $this->notification($file, 1, 1, false);
        $this->getJson($this->url('notification', $file), $this->headers())->assertOk();
        $this->other->users()->attach(1);
        $this->getJson($this->url('notification', $file), $this->headers())->assertNotFound();
        DB::table('notifications')->where('id', $id)->update(['data' => json_encode([
            'sender_id' => 1, 'receiver_id' => 1, 'document_name' => $file,
            'company_id' => $this->company->id, 'branch_id' => $this->restrictedBranch,
        ])]);
        $this->getJson($this->url('notification', $file), $this->headers())->assertNotFound();
    }

    public function test_production_uses_actual_warehouse_parent_for_company_and_branch(): void
    {
        $files = [];
        foreach ([1, 2, 3] as $warehouse) {
            $files[$warehouse] = $this->file('documents/production');
            DB::table('productions')->insert(['warehouse_id' => $warehouse, 'document' => $files[$warehouse]]);
        }
        $this->getJson($this->url('production', $files[1]), $this->headers())->assertOk();
        $this->getJson($this->url('production', $files[2]), $this->headers())->assertNotFound();
        $this->getJson($this->url('production', $files[3]), $this->headers())->assertNotFound();
    }

    public function test_hr_portraits_require_active_owner_company_and_authorized_warehouse(): void
    {
        $own = $this->file('images/employee');
        $foreign = $this->file('images/employee');
        $restricted = $this->file('images/employee');
        foreach ([[$own, $this->company->id, 1], [$foreign, $this->other->id, 2], [$restricted, $this->company->id, 3]] as [$file, $company, $warehouse]) {
            DB::table('employees')->insert(['company_id' => $company, 'warehouse_id' => $warehouse, 'image' => $file]);
        }
        $this->getJson($this->url('employee', $own), $this->headers())->assertOk();
        $this->getJson($this->url('employee', $foreign), $this->headers())->assertNotFound();
        $this->getJson($this->url('employee', $restricted), $this->headers())->assertNotFound();
        DB::table('employees')->where('image', $own)->update(['is_active' => false]);
        $this->getJson($this->url('employee', $own), $this->headers())->assertNotFound();
    }

    public function test_supplier_record_image_is_private_and_html_disguised_as_portrait_is_not_rendered(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
        });
        $file = $this->file('images/supplier');
        DB::table('suppliers')->where('id', 1)->update(['company_id' => $this->company->id, 'image' => $file]);
        $this->getJson($this->url('supplier', $file), $this->headers())->assertOk();
        $this->actingAs(User::findOrFail(2))->getJson($this->url('supplier', $file))->assertNotFound();
        $this->actingAs(User::findOrFail(1));
        file_put_contents(public_path('images/supplier/'.$file), '<html><script>alert(1)</script></html>');
        $this->getJson($this->url('supplier', $file), $this->headers())->assertNotFound();
    }

    public function test_new_uploads_are_private_and_traversal_is_refused(): void
    {
        $storage = new PrivateFileStorage();
        $file = $storage->store(UploadedFile::fake()->create('record.txt', 1, 'text/plain'), 'notification');
        $path = $storage->path('notification', $file);
        $this->files[] = $path;
        $this->assertFileExists($path);
        $this->assertFalse(is_file(public_path('documents/notification/'.$file)));
        foreach (['../record.txt', '..\\record.txt', '%2e%2e%2frecord.txt', 'record.txt'.chr(0), '..'] as $invalid) {
            $this->assertNull($storage->path('notification', $invalid));
        }
        $this->getJson($this->url('notification', '..%5Crecord.txt'), $this->headers())->assertNotFound();
        $this->getJson($this->url('unknown', $file), $this->headers())->assertNotFound();
    }

    public function test_new_notification_stamps_trusted_sender_company_branch_and_stores_attachment_privately(): void
    {
        DB::table('users')->insert(['id' => 3, 'name' => 'Recipient']);
        $this->company->users()->attach(3);
        DB::table('company_user_branches')->insert(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'user_id' => 3]);
        $this->post(route('notifications.store'), [
            'receiver_id' => 3, 'sender_id' => 2, 'company_id' => $this->other->id, 'branch_id' => $this->otherBranch->id,
            'document_name' => 'forged.txt', 'document' => UploadedFile::fake()->create('private.txt', 1, 'text/plain'),
            'message' => 'Reviewed notification', 'reminder_date' => '2026-10-03',
        ], $this->headers())->assertRedirect();
        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);
        $path = app(PrivateFileStorage::class)->path('notification', $data['document_name']);
        $this->files[] = $path;
        $this->assertSame(1, $data['sender_id']);
        $this->assertSame($this->company->id, $data['company_id']);
        $this->assertSame($this->branch->id, $data['branch_id']);
        $this->assertNotSame('forged.txt', $data['document_name']);
        $this->assertFileExists($path);
        $this->assertFalse(is_file(public_path('documents/notification/'.$data['document_name'])));
        $this->actingAs(User::findOrFail(3));
        $this->getJson($this->url('notification', $data['document_name']), $this->headers())->assertOk();
    }

    public function test_notification_cannot_send_file_to_foreign_company_recipient(): void
    {
        $this->postJson(route('notifications.store'), [
            'receiver_id' => 2, 'message' => 'Forbidden send', 'reminder_date' => '2026-10-03',
        ], $this->headers())->assertForbidden();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_legacy_shared_filename_cannot_grant_overwritten_foreign_content(): void
    {
        $file = $this->file('documents/sale');
        $own = $this->sale($file);
        $foreign = (array) DB::table('sales')->where('id', $own)->first();
        unset($foreign['id']);
        $foreign['company_id'] = $this->other->id;
        $foreign['warehouse_id'] = 2;
        $foreign['reference_no'] = (string) Str::uuid();
        DB::table('sales')->insert($foreign);
        $this->getJson($this->url('sale', $file), $this->headers())->assertNotFound();
    }

    public function test_linked_file_cannot_escape_family_directory(): void
    {
        $file = $this->file('documents/sale');
        $source = public_path('documents/sale/'.$file);
        $link = public_path('documents/sale/'.Str::uuid().'.txt');
        if (!@symlink($source, $link)) {
            $this->markTestSkipped('Host does not permit creating symlinks; CI Linux exercises this check.');
        }
        $this->files[] = $link;
        $this->sale(basename($link));
        $this->getJson($this->url('sale', basename($link)), $this->headers())->assertNotFound();
    }

    public function test_direct_private_urls_are_denied_in_apache_configuration(): void
    {
        $rules = file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('|notification|production)(/|$) - [F,L,NC]', $rules);
        $this->assertStringContainsString('^images/(employee|sale_agent|supplier)(/|$) - [F,L,NC]', $rules);
    }

    private function file(string $directory): string
    {
        $isImage = str_starts_with($directory, 'images/');
        $directory = public_path($directory);
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $file = Str::uuid().($isImage ? '.png' : '.txt');
        $this->files[] = $directory.'/'.$file;
        file_put_contents($directory.'/'.$file, $isImage
            ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfCgAAAAASUVORK5CYII=')
            : 'private fixture');
        return $file;
    }

    private function sale(string $file, int $warehouse = 1): int
    {
        return DB::table('sales')->insertGetId([
            'reference_no' => (string) Str::uuid(), 'company_id' => $this->company->id,
            'customer_id' => 1, 'user_id' => 1, 'warehouse_id' => $warehouse, 'biller_id' => 1,
            'item' => 0, 'total_qty' => 0, 'total_discount' => 0, 'total_tax' => 0, 'total_price' => 0,
            'grand_total' => 0, 'sale_status' => 1, 'payment_status' => 1, 'document' => $file,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function notification(string $file, int $sender, int $recipient, bool $explicit = true): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id, 'type' => 'fixture', 'notifiable_type' => User::class, 'notifiable_id' => $recipient,
            'data' => json_encode(['sender_id' => $sender, 'receiver_id' => $recipient, 'document_name' => $file]
                + ($explicit ? ['company_id' => $this->company->id, 'branch_id' => $this->branch->id] : [])),
        ]);
        return $id;
    }

    private function url(string $folder, string $file): string
    {
        return route('documents.file', [$folder, $file]);
    }

    private function headers(): array
    {
        return ['X-Company-ID' => (string) $this->company->id, 'X-Branch-ID' => (string) $this->branch->id,
            'X-Financial-Year-ID' => (string) $this->year->id];
    }
}
