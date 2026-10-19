<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\{Attendance, Employee, Invoice, Payroll, Partner, Product, StockMovement, Unit, Warehouse, WorkShift};
use App\Services\Admin\InvoiceService;
use App\Support\Admin\Decimal as D;
use Database\Seeders\ErpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimpleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Product $product;
    private Warehouse $warehouse;
    private Partner $supplier;
    private Partner $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-19 12:00:00'));
        $this->seed(ErpSeeder::class);
        $this->admin = Admin::factory()->create(['is_super' => true]);
        $this->product = Product::factory()->create();
        $this->warehouse = Warehouse::create(['code' => 'W1', 'name_ar' => 'المستودع', 'name_en' => 'Warehouse']);
        $this->supplier = Partner::create(['code' => 'S1', 'kind' => 'supplier', 'name_ar' => 'المورد', 'name_en' => 'Supplier']);
        $this->customer = Partner::create(['code' => 'C1', 'kind' => 'customer', 'name_ar' => 'العميل', 'name_en' => 'Customer']);
        $this->actingAs($this->admin, 'admin');
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }

    private function data(string $type, string $quantity = '10', string $price = '2'): array
    {
        return ['date' => '2026-10-19', 'partner_id' => $type === 'purchase_invoice' ? $this->supplier->id : $this->customer->id,
            'warehouse_id' => $this->warehouse->id, 'request_id' => (string) Str::uuid(), 'paid' => '0', 'tax_rate' => '0', 'discount' => '0',
            'lines' => [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $price]]];
    }

    private function saveInvoice(string $type, string $quantity = '10', string $price = '2', array $overrides = []): Invoice
    {
        $this->post(route('admin.invoices.store', $type), array_replace($this->data($type, $quantity, $price), $overrides))->assertRedirect()->assertSessionHasNoErrors();
        return Invoice::latest('id')->firstOrFail();
    }

    private function stock(): string { return app(InvoiceService::class)->quantity($this->product->id, $this->warehouse->id); }
    private function decimal(string $expected, $actual): void { $this->assertSame(0, D::cmp($expected, (string) $actual)); }

    public function test_purchase_and_sale_update_stock_immediately_once(): void
    {
        $data = $this->data('purchase_invoice', '10.125', '2');
        for ($i = 0; $i < 2; $i++) { $this->post(route('admin.invoices.store', 'purchase_invoice'), $data)->assertSessionHasNoErrors(); }
        $this->assertSame(1, Invoice::count()); $this->decimal('10.125', $this->stock());
        $sale = $this->saveInvoice('sales_invoice', '2.125', '3');
        $this->assertSame('posted', $sale->status); $this->decimal('6.375', $sale->total); $this->decimal('8', $this->stock());
        $this->assertSame(2, StockMovement::count());
    }

    public function test_shortage_rolls_back_the_whole_invoice_including_duplicate_product_lines(): void
    {
        $this->saveInvoice('purchase_invoice', '5');
        $data = $this->data('sales_invoice', '3'); $data['lines'][] = $data['lines'][0];
        $this->post(route('admin.invoices.store', 'sales_invoice'), $data)->assertSessionHasErrors();
        $this->assertSame(1, Invoice::count()); $this->assertSame(1, StockMovement::count()); $this->decimal('5', $this->stock());
    }

    public function test_invoice_discount_tax_and_paid_balance_use_decimal_calculations(): void
    {
        $invoice = $this->saveInvoice('purchase_invoice', '3', '1.125', ['discount' => '0.125', 'tax_rate' => '16', 'paid' => '1']);
        $this->decimal('3.375', $invoice->subtotal); $this->decimal('0.52', $invoice->tax_total);
        $this->decimal('3.77', $invoice->total); $this->decimal('2.77', D::sub($invoice->total, $invoice->paid));
        $this->get(route('admin.invoices.show', $invoice))->assertOk()->assertSee('3.770');
    }

    public function test_payments_cannot_repeat_or_exceed_invoice_balance(): void
    {
        $invoice = $this->saveInvoice('purchase_invoice'); $token = (string) Str::uuid();
        for ($i = 0; $i < 2; $i++) { $this->post(route('admin.invoices.pay', $invoice), ['amount' => '7', 'request_id' => $token])->assertSessionHasNoErrors(); }
        $this->decimal('7', $invoice->fresh()->paid);
        $this->post(route('admin.invoices.pay', $invoice), ['amount' => '14', 'request_id' => (string) Str::uuid()])->assertSessionHasErrors();
        $this->decimal('7', $invoice->fresh()->paid);
    }

    public function test_cancellation_restores_stock_once_and_retains_invoice(): void
    {
        $this->saveInvoice('purchase_invoice'); $sale = $this->saveInvoice('sales_invoice', '3');
        for ($i = 0; $i < 2; $i++) { $this->post(route('admin.invoices.cancel', $sale), ['reason' => 'Incorrect invoice'])->assertSessionHasNoErrors(); }
        $this->decimal('10', $this->stock()); $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame(3, StockMovement::count()); $this->assertSame(2, Invoice::count());
        $this->post(route('admin.invoices.pay', $sale), ['amount' => '1', 'request_id' => (string) Str::uuid()])->assertSessionHasErrors();
    }

    public function test_purchase_cannot_be_cancelled_after_stock_was_consumed(): void
    {
        $purchase = $this->saveInvoice('purchase_invoice'); $this->saveInvoice('sales_invoice', '4');
        $this->post(route('admin.invoices.cancel', $purchase), ['reason' => 'Test'])->assertSessionHasErrors();
        $this->decimal('6', $this->stock()); $this->assertSame('posted', $purchase->fresh()->status);
    }

    public function test_manual_stock_entry_is_simple_reasoned_and_idempotent(): void
    {
        $data = ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '12.5', 'notes' => 'Opening count', 'request_id' => (string) Str::uuid()];
        for ($i = 0; $i < 2; $i++) { $this->post(route('admin.stock.adjust'), $data)->assertSessionHasNoErrors(); }
        $this->decimal('12.5', $this->stock());
        $this->post(route('admin.stock.adjust'), array_replace($data, ['quantity' => '-13', 'request_id' => (string) Str::uuid()]))->assertSessionHasErrors();
        $this->decimal('12.5', $this->stock());
    }

    public function test_staff_cannot_read_hr_or_manage_users_and_hr_cannot_save_invoices(): void
    {
        $staff = Admin::factory()->create(); $staff->assignRole('erp_staff'); $this->actingAs($staff, 'admin');
        foreach (['employees', 'attendance'] as $resource) { $this->get(route('admin.masters.index', $resource))->assertForbidden(); }
        $this->get(route('admin.payroll.index'))->assertForbidden(); $this->get(route('admin.access'))->assertForbidden();
        $this->get(route('admin.invoices.create', 'sales_invoice'))->assertOk();
        $hr = Admin::factory()->create(); $hr->assignRole('erp_hr'); $this->actingAs($hr, 'admin');
        $this->post(route('admin.invoices.store', 'purchase_invoice'), $this->data('purchase_invoice'))->assertForbidden();
        $this->get(route('admin.masters.index', 'employees'))->assertOk();
    }

    public function test_salary_is_monthly_simple_and_paid_salary_cannot_be_deleted(): void
    {
        $shift = WorkShift::create(['name_ar'=>'صباحي','name_en'=>'Day','starts_at'=>'08:00','ends_at'=>'16:00']);
        $employee = Employee::create(['code' => 'E1', 'name_ar' => 'الموظف', 'name_en' => 'Employee', 'hire_date' => '2026-01-01', 'monthly_salary' => '500', 'shift_id'=>$shift->id]);
        foreach (\Carbon\CarbonPeriod::create('2026-09-01','2026-09-30') as $day) {
            if ($day->dayOfWeek !== 5) { Attendance::create(['employee_id'=>$employee->id,'date'=>$day->toDateString(),'status'=>'leave']); }
        }
        $data = ['employee_id' => $employee->id, 'month' => '2026-09', 'allowance' => '25.5', 'deduction' => '10'];
        $this->post(route('admin.payroll.store'), $data)->assertSessionHasNoErrors();
        $payroll = Payroll::firstOrFail(); $this->decimal('515.5', $payroll->net);
        $this->post(route('admin.payroll.store'), $data)->assertSessionHasNoErrors(); $this->assertSame(1, Payroll::count());
        $this->get(route('admin.payroll.show', $payroll))->assertOk()->assertSee('515.500');
        $this->post(route('admin.payroll.paid', $payroll))->assertSessionHasNoErrors();
        $this->post(route('admin.payroll.paid', $payroll))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-19', $payroll->fresh()->paid_on);
        $this->delete(route('admin.payroll.destroy', $payroll))->assertSessionHasErrors(); $this->assertSame(1, Payroll::count());
    }

    public function test_simple_master_forms_save_and_reject_duplicate_attendance_and_changed_used_unit(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-19 18:00:00'));
        $shift = WorkShift::create(['name_ar'=>'صباحي','name_en'=>'Day','starts_at'=>'08:00','ends_at'=>'16:00']);
        $employeeData = ['code' => 'E1', 'name_ar' => 'موظف', 'hire_date' => '2026-01-01', 'monthly_salary' => '400', 'active' => '1', 'shift_id'=>$shift->id];
        $this->post(route('admin.masters.store', 'employees'), $employeeData)->assertSessionHasNoErrors();
        $employee = Employee::firstOrFail(); $this->decimal('400', $employee->monthly_salary);
        $data = ['employee_id' => $employee->id, 'date' => '2026-10-19', 'status' => 'present', 'check_in' => '08:00', 'check_out' => '16:00'];
        $this->post(route('admin.masters.store', 'attendance'), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.masters.store', 'attendance'), $data)->assertSessionHasErrors(); $this->assertSame(1, Attendance::count());
        $this->saveInvoice('purchase_invoice');
        $this->put(route('admin.masters.update', ['products', $this->product->id]), ['sku' => $this->product->sku, 'name_ar' => 'صنف', 'base_unit_id' => Unit::where('code', 'kg')->first()->id, 'cost' => '2', 'selling_price' => '3', 'minimum_stock' => '0', 'active' => '1'])->assertSessionHasErrors();
    }

    public function test_both_languages_all_remaining_pages_and_removed_modules(): void
    {
        $this->saveInvoice('purchase_invoice');
        foreach (['ar', 'en'] as $locale) {
            $this->post(route('admin.language', $locale));
            foreach (['admin.dashboard', 'admin.stock', 'admin.payroll.index', 'admin.access'] as $route) {
                $response = $this->get(route($route))->assertOk();
                $this->assertStringNotContainsString('erp.', strip_tags($response->getContent()));
                if (getenv('SIMPLE_CAPTURE') && $route === 'admin.dashboard') {
                    $html = str_replace('http://localhost/assets', 'file:///'.str_replace('\\', '/', base_path('assets')), $response->getContent());
                    file_put_contents(storage_path('framework/factory-dashboard-'.$locale.'.html'), $html);
                }
            }
            foreach (array_keys(config('erp.masters')) as $resource) {
                $this->get(route('admin.masters.index', $resource))->assertOk();
                $this->get(route('admin.masters.create', $resource))->assertOk();
            }
            foreach (array_keys(InvoiceService::TYPES) as $type) {
                $this->get(route('admin.invoices.index', $type))->assertOk();
                $this->get(route('admin.invoices.create', $type))->assertOk();
            }
        }
        foreach (['admin/boms', 'admin/production/create', 'admin/reports', 'admin/settings', 'admin/masters/accounts', 'admin/invoices/journal'] as $path) { $this->get($path)->assertNotFound(); }
        foreach (['erp_accounts', 'erp_boms', 'erp_journals', 'erp_productions', 'erp_fiscal_periods'] as $table) { $this->assertFalse(Schema::hasTable($table)); }
    }

    public function test_user_management_has_only_fixed_roles_and_cannot_create_another_super_admin(): void
    {
        $data = ['name' => 'Operator', 'username' => 'simple-operator', 'password' => 'test-only-password', 'password_confirmation' => 'test-only-password', 'role' => 'erp_staff', 'is_super' => true];
        $this->post(route('admin.access.store'), $data)->assertSessionHasNoErrors();
        $operator = Admin::where('username', 'simple-operator')->firstOrFail();
        $this->assertFalse((bool) $operator->is_super); $this->assertTrue($operator->hasRole('erp_staff'));
        $this->post(route('admin.access.store'), array_replace($data, ['username' => 'forbidden-admin', 'role' => 'erp_super_admin']))->assertSessionHasErrors();
        $this->assertFalse(Admin::where('username', 'forbidden-admin')->exists());
        $this->actingAs($operator, 'admin')->post(route('admin.access.store'), $data)->assertForbidden();
    }
}
