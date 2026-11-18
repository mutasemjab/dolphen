<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\{Attendance, Employee, Invoice, Partner, Payroll, Product, StockMovement, Unit, Warehouse, WorkShift};
use App\Services\Admin\{AttendanceService, HrSettings, InvoiceService, LeaveService};
use App\Support\Admin\Decimal as D;
use Carbon\{Carbon, CarbonPeriod};
use Database\Seeders\ErpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

class WarehouseDocumentsAndOvertimeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Warehouse $warehouse;
    private Product $product;
    private Partner $customer;
    private Partner $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.timezone' => 'Asia/Amman']);
        date_default_timezone_set('Asia/Amman'); $this->travelTo(Carbon::parse('2026-10-19 12:00:00', 'Asia/Amman'));
        $this->seed(ErpSeeder::class);
        $this->admin = Admin::factory()->create(['is_super' => true]);
        $this->warehouse = Warehouse::create(['code' => 'W1', 'name_ar' => 'المستودع', 'name_en' => 'Warehouse']);
        $this->product = Product::factory()->create(['warehouse_id' => $this->warehouse->id]);
        $this->customer = Partner::create(['code' => 'C1', 'kind' => 'customer', 'name_ar' => 'العميل', 'name_en' => 'Customer']);
        $this->supplier = Partner::create(['code' => 'S1', 'kind' => 'supplier', 'name_ar' => 'المورد', 'name_en' => 'Supplier']);
        $this->actingAs($this->admin, 'admin');
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }
    private function decimal(string $expected, $actual): void { $this->assertSame(0, D::cmp($expected, (string) $actual), "Expected $expected, got $actual"); }
    private function stock(?Product $product = null): string { return app(InvoiceService::class)->quantity(($product ?? $this->product)->id, $this->warehouse->id); }

    private function invoice(string $type, array $overrides = [], string $quantity = '10', string $price = '2'): Invoice
    {
        $data = array_replace(['date' => '2026-10-19', 'partner_id' => $type === 'purchase_invoice' ? $this->supplier->id : $this->customer->id,
            'warehouse_id' => $this->warehouse->id, 'request_id' => (string) Str::uuid(), 'paid' => '0', 'tax_rate' => '0', 'discount' => '0',
            'lines' => [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $price]]], $overrides);
        $this->post(route('admin.invoices.store', $type), $data)->assertSessionHasNoErrors();
        return Invoice::where('type', $type)->latest('id')->firstOrFail();
    }

    private function voucher(string $type, string $quantity, ?Product $product = null, array $overrides = [])
    {
        return $this->post(route('admin.vouchers.store', $type), array_replace(['date' => '2026-10-19', 'warehouse_id' => $this->warehouse->id,
            'request_id' => (string) Str::uuid(), 'notes' => 'Count', 'lines' => [['product_id' => ($product ?? $this->product)->id, 'quantity' => $quantity]]], $overrides));
    }

    // 1 + 2: product warehouse and item type

    public function test_raw_material_has_no_selling_price_and_cannot_be_sold(): void
    {
        $unit = Unit::first();
        $base = ['name_ar' => 'حبيبات بلاستيك', 'item_type' => 'raw', 'warehouse_id' => $this->warehouse->id, 'base_unit_id' => $unit->id, 'cost' => '1.5', 'minimum_stock' => '0', 'active' => 1];
        $this->post(route('admin.masters.store', 'products'), $base + ['selling_price' => '9'])->assertSessionHasNoErrors();
        $raw = Product::where('name_ar', 'حبيبات بلاستيك')->firstOrFail();
        $this->assertSame('raw', $raw->item_type); $this->decimal('0', $raw->selling_price); $this->assertSame($this->warehouse->id, $raw->warehouse_id);
        // Finished goods still require a selling price; a product needs a warehouse.
        $this->post(route('admin.masters.store', 'products'), array_replace($base, ['name_ar' => 'منتج', 'item_type' => 'finished']))->assertSessionHasErrors('selling_price');
        $this->post(route('admin.masters.store', 'products'), array_replace($base, ['name_ar' => 'منتج', 'warehouse_id' => null]))->assertSessionHasErrors('warehouse_id');

        $this->invoice('purchase_invoice', ['lines' => [['product_id' => $raw->id, 'quantity' => '5', 'unit_price' => '1.5']]]);
        $this->decimal('5', $this->stock($raw));
        $this->post(route('admin.invoices.store', 'sales_invoice'), ['date' => '2026-10-19', 'partner_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'request_id' => (string) Str::uuid(), 'lines' => [['product_id' => $raw->id, 'quantity' => '1', 'unit_price' => '3']]])->assertSessionHasErrors();
        $this->decimal('5', $this->stock($raw));
        $this->get(route('admin.invoices.create', 'sales_invoice'))->assertOk()->assertDontSee('حبيبات بلاستيك · ')->assertSee($this->product->name_ar.' · ');
        $this->get(route('admin.invoices.create', 'purchase_invoice'))->assertOk()->assertSee('حبيبات بلاستيك · ');
        $this->get(route('admin.masters.index', ['products', 'item_type' => 'raw']))->assertOk()->assertSee('حبيبات بلاستيك')->assertDontSee($this->product->name_ar);
    }

    // 6: vouchers

    public function test_invoices_post_stock_through_automatic_vouchers_and_cancel_them_together(): void
    {
        $purchase = $this->invoice('purchase_invoice');
        $receipt = Invoice::where('source_id', $purchase->id)->firstOrFail();
        $this->assertSame('receipt_voucher', $receipt->type); $this->assertStringStartsWith('GR-2026-', $receipt->number);
        $this->assertSame(1, $receipt->lines()->count()); $this->decimal('10', $this->stock());
        $this->assertSame(0, StockMovement::where('document_id', $purchase->id)->count());
        $this->assertSame(1, StockMovement::where('document_id', $receipt->id)->count());

        $sale = $this->invoice('sales_invoice', [], '4', '3');
        $issue = Invoice::where('source_id', $sale->id)->firstOrFail();
        $this->assertSame('issue_voucher', $issue->type); $this->decimal('6', $this->stock());

        // An automatic voucher is reversed only by cancelling its invoice.
        $this->post(route('admin.vouchers.cancel', $issue), ['reason' => 'x'])->assertSessionHasErrors();
        $this->decimal('6', $this->stock());
        $this->post(route('admin.invoices.cancel', $purchase), ['reason' => 'x'])->assertSessionHasErrors();
        $this->post(route('admin.invoices.cancel', $sale), ['reason' => 'Wrong customer'])->assertSessionHasNoErrors();
        $this->decimal('10', $this->stock());
        $this->assertSame('cancelled', $issue->fresh()->status); $this->assertSame('cancelled', $sale->fresh()->status);

        $this->get(route('admin.vouchers.show', $receipt))->assertOk()->assertSee($purchase->number);
        $this->get(route('admin.invoices.show', $purchase))->assertOk()->assertSee($receipt->number);
        $this->get(route('admin.invoices.show', $receipt))->assertRedirect(route('admin.vouchers.show', $receipt));
        $this->get(route('admin.vouchers.index', ['receipt_voucher', 'origin' => 'invoice']))->assertOk()->assertSee($receipt->number);
    }

    public function test_manual_vouchers_move_stock_once_and_can_be_cancelled(): void
    {
        $data = ['date' => '2026-10-19', 'warehouse_id' => $this->warehouse->id, 'request_id' => (string) Str::uuid(), 'notes' => 'Opening',
            'lines' => [['product_id' => $this->product->id, 'quantity' => '12.5']]];
        for ($i = 0; $i < 2; $i++) { $this->post(route('admin.vouchers.store', 'receipt_voucher'), $data)->assertSessionHasNoErrors(); }
        $this->assertSame(1, Invoice::where('type', 'receipt_voucher')->count()); $this->decimal('12.5', $this->stock());

        $this->voucher('issue_voucher', '13')->assertSessionHasErrors();
        $this->decimal('12.5', $this->stock());
        $this->voucher('issue_voucher', '2.5')->assertSessionHasNoErrors();
        $issue = Invoice::where('type', 'issue_voucher')->firstOrFail(); $this->decimal('10', $this->stock());

        $receipt = Invoice::where('type', 'receipt_voucher')->firstOrFail();
        // Cancelling the receipt would leave negative stock while the issue is posted.
        $this->voucher('issue_voucher', '5')->assertSessionHasNoErrors();
        $this->post(route('admin.vouchers.cancel', $receipt), ['reason' => 'Wrong count'])->assertSessionHasErrors();
        $this->post(route('admin.vouchers.cancel', $issue), ['reason' => 'Returned'])->assertSessionHasNoErrors();
        $this->post(route('admin.vouchers.cancel', $issue), ['reason' => 'Returned'])->assertSessionHasNoErrors();
        $this->decimal('7.5', $this->stock()); $this->assertSame('cancelled', $issue->fresh()->status);

        $staff = Admin::factory()->create(); $staff->assignRole('erp_hr'); $this->actingAs($staff, 'admin');
        $this->get(route('admin.vouchers.index', 'receipt_voucher'))->assertForbidden();
        $this->voucher('receipt_voucher', '1')->assertForbidden();
    }

    // 5: pro forma

    public function test_proforma_changes_nothing_and_converts_once_with_edited_lines(): void
    {
        $this->invoice('purchase_invoice', [], '20');
        $proforma = $this->invoice('proforma_invoice', ['discount' => '1', 'tax_rate' => '16', 'valid_until' => '2026-11-19'], '5', '3');
        $this->assertSame('open', $proforma->status); $this->assertStringStartsWith('PF-2026-', $proforma->number);
        $this->decimal('16.24', $proforma->total); $this->decimal('0', $proforma->paid);
        $this->decimal('20', $this->stock()); $this->assertSame(0, Invoice::where('source_id', $proforma->id)->count());
        $this->post(route('admin.invoices.pay', $proforma), ['amount' => '1', 'request_id' => (string) Str::uuid()])->assertNotFound();

        $this->get(route('admin.invoices.create', ['sales_invoice', 'proforma' => $proforma->id]))->assertOk()
            ->assertSee('name="proforma_id" value="'.$proforma->id.'"', false)->assertSee('value="5"', false)->assertSee('value="3"', false);

        $sale = $this->invoice('sales_invoice', ['proforma_id' => $proforma->id, 'discount' => '0', 'tax_rate' => '0',
            'lines' => [['product_id' => $this->product->id, 'quantity' => '4', 'unit_price' => '3.5']]]);
        $this->decimal('14', $sale->total); $this->assertSame($proforma->id, $sale->source_id);
        $this->assertSame('converted', $proforma->fresh()->status); $this->decimal('16', $this->stock());
        $this->assertSame($sale->id, $proforma->fresh()->metadata['converted_to']);

        // A second conversion of the same pro forma is refused.
        $this->post(route('admin.invoices.store', 'sales_invoice'), ['date' => '2026-10-19', 'partner_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'request_id' => (string) Str::uuid(), 'proforma_id' => $proforma->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1', 'unit_price' => '3']]])->assertSessionHasErrors();
        $this->assertSame(1, Invoice::where('type', 'sales_invoice')->count());
        $this->get(route('admin.invoices.create', ['sales_invoice', 'proforma' => $proforma->id]))->assertRedirect(route('admin.invoices.show', $proforma));
        $this->get(route('admin.invoices.show', $proforma))->assertOk()->assertSee($sale->number);

        $other = $this->invoice('proforma_invoice');
        $this->post(route('admin.invoices.cancel', $other), ['reason' => 'Customer declined'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $other->fresh()->status);
        $this->post(route('admin.invoices.cancel', $proforma), ['reason' => 'Late'])->assertSessionHasErrors();
    }

    // 4: account statements

    public function test_customer_and_supplier_statements_show_running_balances(): void
    {
        $this->invoice('purchase_invoice', ['date' => '2026-10-01', 'paid' => '5'], '20', '2');        // supplier: +40, -5
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00', 'Asia/Amman'));
        $first = $this->invoice('sales_invoice', ['date' => '2026-10-10', 'paid' => '4'], '5', '3');   // customer: +15, -4
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'Asia/Amman'));
        $this->post(route('admin.invoices.pay', $first), ['amount' => '6', 'request_id' => (string) Str::uuid()])->assertSessionHasNoErrors(); // -6
        $second = $this->invoice('sales_invoice', ['date' => '2026-10-15', 'paid' => '2'], '2', '5');  // +10, -2
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00', 'Asia/Amman'));
        $this->post(route('admin.invoices.cancel', $second), ['reason' => 'Returned'])->assertSessionHasNoErrors(); // -10, +2

        $response = $this->get(route('admin.statements', ['customers', 'partner_id' => $this->customer->id]))->assertOk();
        $rows = $response->viewData('rows');
        $this->assertSame(['sales_invoice', 'payment_received', 'payment_received', 'sales_invoice', 'payment_received', 'invoice_cancelled', 'cancelled_payment_settled'], array_column($rows, 'description'));
        $this->decimal('5', $response->viewData('balance'));
        $this->decimal('27', $response->viewData('debit')); $this->decimal('22', $response->viewData('credit'));

        $later = $this->get(route('admin.statements', ['customers', 'partner_id' => $this->customer->id, 'from' => '2026-10-12']))->assertOk();
        $this->decimal('11', $later->viewData('opening')); $this->decimal('5', $later->viewData('balance'));

        $supplier = $this->get(route('admin.statements', ['suppliers', 'partner_id' => $this->supplier->id]))->assertOk();
        $this->decimal('35', $supplier->viewData('balance'));
        $this->get(route('admin.statements', 'suppliers'))->assertOk()->assertSee('35.000');
        // A customer cannot be opened as a supplier statement.
        $this->get(route('admin.statements', ['suppliers', 'partner_id' => $this->customer->id]))->assertNotFound();

        $hr = Admin::factory()->create(); $hr->assignRole('erp_hr'); $this->actingAs($hr, 'admin');
        $this->get(route('admin.statements', 'customers'))->assertForbidden();
    }

    // 7: holiday and foreign worker overtime

    private function hrSetup(array $policy = []): Employee
    {
        HrSettings::save(array_replace(HrSettings::DEFAULTS, ['latitude' => '31.95', 'longitude' => '35.91', 'work_days' => [1, 2, 3, 4, 5],
            'overtime_hour_rate' => '4', 'holiday_overtime_hour_rate' => '6', 'holidays' => [['date' => '2026-09-15', 'name' => 'Holiday']]], $policy));
        $shift = WorkShift::create(['name_ar' => 'صباحية', 'name_en' => 'Day', 'starts_at' => '08:00', 'ends_at' => '16:00']);
        return Employee::create(['code' => 'E1', 'name_ar' => 'أحمد', 'name_en' => 'Ahmad', 'hire_date' => '2026-09-01', 'shift_id' => $shift->id,
            'monthly_salary' => '528', 'annual_leave_days' => '14', 'username' => 'ahmad', 'password' => Hash::make('employee-test-password')]);
    }

    private function attend(Employee $employee, string $date, string $in = '08:00', string $out = '16:00'): void
    {
        Attendance::create(app(AttendanceService::class)->manual(['employee_id' => $employee->id, 'date' => $date, 'status' => 'present', 'check_in' => $in, 'check_out' => $out], new Attendance()));
    }

    private function september(Employee $employee): Payroll
    {
        foreach (CarbonPeriod::create('2026-09-01', '2026-09-30') as $day) {
            if ($day->isWeekday() && $day->toDateString() !== '2026-09-15' && !Attendance::where('employee_id', $employee->id)->where('date', $day->toDateString())->exists()) { $this->attend($employee, $day->toDateString()); }
        }
        $this->attend($employee, '2026-09-05', '08:00', '12:00'); // Saturday: rest day
        $this->post(route('admin.payroll.store'), ['employee_id' => $employee->id, 'month' => '2026-09', 'allowance' => '0', 'deduction' => '0'])->assertSessionHasNoErrors();
        return Payroll::where('employee_id', $employee->id)->firstOrFail();
    }

    public function test_official_holiday_is_paid_and_rest_day_work_is_holiday_overtime(): void
    {
        $employee = $this->hrSetup();
        $this->attend($employee, '2026-09-02', '08:00', '17:00'); // one regular overtime hour
        Attendance::where('date', '2026-09-02')->delete();
        $payroll = $this->september($employee);
        $this->decimal('528', $payroll->salary); $this->decimal('0', $payroll->absence_deduction);
        $this->decimal('0', $payroll->overtime_pay); $this->decimal('24', $payroll->holiday_overtime_pay); // 4h × 6
        $this->decimal('552', $payroll->net);
        $this->assertSame(22, $payroll->details['scheduled_days']); $this->assertSame(1, $payroll->details['holiday_days']);
        $this->assertSame(1, $payroll->details['holiday_worked_days']); $this->assertSame(240, $payroll->details['holiday_overtime_minutes']);
        $this->assertSame(0, $payroll->details['absent_days']);
        $this->post(route('admin.payroll.paid', $payroll))->assertSessionHasNoErrors();
        $this->get(route('admin.payroll.show', $payroll))->assertOk()->assertSee('552.000');
    }

    public function test_foreign_workers_use_their_rates_and_blank_rates_fall_back(): void
    {
        $employee = $this->hrSetup(['foreign_holiday_overtime_hour_rate' => '9']);
        $employee->update(['worker_type' => 'foreign']);
        Attendance::query()->delete();
        $this->attend($employee, '2026-09-01', '08:00', '18:00'); // two regular overtime hours
        $payroll = $this->september($employee);
        $this->decimal('8', $payroll->overtime_pay);           // foreign regular rate blank → local 4
        $this->decimal('36', $payroll->holiday_overtime_pay);  // 4h × 9
        $this->assertSame('foreign', $payroll->details['worker_type']);

        $this->assertSame(['regular' => '4', 'holiday' => '4'], HrSettings::overtimeRates(['overtime_hour_rate' => '4', 'holiday_overtime_hour_rate' => ''], 'local'));
        $this->assertSame(['regular' => '5', 'holiday' => '7'], HrSettings::overtimeRates(['overtime_hour_rate' => '4', 'holiday_overtime_hour_rate' => '7',
            'foreign_overtime_hour_rate' => '5', 'foreign_holiday_overtime_hour_rate' => ''], 'foreign'));
    }

    public function test_day_off_accepts_only_work_and_portal_punch_needs_permission(): void
    {
        $employee = $this->hrSetup();
        $this->post(route('admin.masters.store', 'attendance'), ['employee_id' => $employee->id, 'date' => '2026-09-05', 'status' => 'absent'])->assertSessionHasErrors();
        $this->post(route('admin.masters.store', 'attendance'), ['employee_id' => $employee->id, 'date' => '2026-09-05', 'status' => 'present', 'check_in' => '08:00', 'check_out' => '11:00'])->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-10-24 08:05:00', 'Asia/Amman')); // Saturday
        Auth::guard('admin')->logout();
        $this->post(route('employee.login.submit'), ['username' => 'ahmad', 'password' => 'employee-test-password'])->assertSessionHasNoErrors();
        $location = ['latitude' => '31.95', 'longitude' => '35.91', 'accuracy' => '10', 'action' => 'in'];
        $this->post(route('employee.attendance'), $location)->assertSessionHasErrors();
        HrSettings::save(array_replace(HrSettings::get(), ['employee_holiday_punch' => true]));
        $this->post(route('employee.attendance'), $location)->assertSessionHasNoErrors();
        $this->assertTrue(Attendance::where('date', '2026-10-24')->exists());
    }

    public function test_annual_leave_does_not_charge_official_holidays(): void
    {
        $employee = $this->hrSetup(['holidays' => [['date' => '2026-10-21', 'name' => 'Holiday']]]);
        $leave = app(LeaveService::class)->submit($employee, ['request_id' => (string) Str::uuid(), 'type' => 'annual',
            'start_date' => '2026-10-20', 'end_date' => '2026-10-22', 'reason' => 'Trip']);
        $this->decimal('2', $leave->charged_days); $this->assertArrayNotHasKey('2026-10-21', $leave->days);
    }

    public function test_settings_save_holidays_and_rates_and_all_new_screens_render_in_both_languages(): void
    {
        $this->hrSetup();
        $this->post(route('admin.hr.settings.save'), array_replace(HrSettings::get(), ['holidays' => [['date' => '2026-12-25', 'name' => 'B'], ['date' => '2026-01-01', 'name' => 'A']],
            'holiday_overtime_hour_rate' => '', 'foreign_overtime_hour_rate' => '5.5', 'employee_holiday_punch' => '1']))->assertSessionHasNoErrors();
        $policy = HrSettings::get();
        $this->assertSame(['2026-01-01', '2026-12-25'], HrSettings::holidayDates($policy));
        $this->assertSame('', $policy['holiday_overtime_hour_rate']); $this->decimal('5.5', $policy['foreign_overtime_hour_rate']); $this->assertTrue($policy['employee_holiday_punch']);
        $this->post(route('admin.hr.settings.save'), array_replace(HrSettings::get(), ['holidays' => [['date' => '2026-12-25'], ['date' => '2026-12-25']]]))->assertSessionHasErrors();

        $purchase = $this->invoice('purchase_invoice'); $sale = $this->invoice('sales_invoice', [], '1', '3'); $proforma = $this->invoice('proforma_invoice');
        $this->voucher('issue_voucher', '1')->assertSessionHasNoErrors(); $voucher = Invoice::where('type', 'issue_voucher')->latest('id')->first();
        foreach (['ar', 'en'] as $locale) {
            $this->post(route('admin.language', $locale));
            foreach ([route('admin.statements', 'customers'), route('admin.statements', ['customers', 'partner_id' => $this->customer->id]),
                route('admin.statements', 'suppliers'), route('admin.statements', ['suppliers', 'partner_id' => $this->supplier->id]),
                route('admin.vouchers.index', 'receipt_voucher'), route('admin.vouchers.index', 'issue_voucher'),
                route('admin.vouchers.create', 'receipt_voucher'), route('admin.vouchers.create', 'issue_voucher'), route('admin.vouchers.show', $voucher),
                route('admin.invoices.index', 'proforma_invoice'), route('admin.invoices.create', 'proforma_invoice'), route('admin.invoices.show', $proforma),
                route('admin.invoices.create', ['sales_invoice', 'proforma' => $proforma->id]), route('admin.invoices.show', $purchase), route('admin.invoices.show', $sale),
                route('admin.hr.settings'), route('admin.stock'), route('admin.masters.index', 'products'), route('admin.masters.create', 'products'),
                route('admin.masters.index', 'customers'), route('admin.masters.index', 'employees'), route('admin.masters.create', 'employees')] as $url) {
                $response = $this->get($url)->assertOk();
                $this->assertStringNotContainsString('erp.', strip_tags($response->getContent()), $url);
            }
        }
    }
}
