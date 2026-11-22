<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\{Currency, Invoice, Partner, Product, Warehouse};
use App\Services\Admin\{Context, InvoiceService};
use App\Support\Admin\Decimal as D;
use Carbon\Carbon;
use Database\Seeders\ErpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurrenciesReceiptsAndBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Warehouse $warehouse;
    private Product $product;
    private Partner $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.timezone' => 'Asia/Amman']);
        date_default_timezone_set('Asia/Amman'); $this->travelTo(Carbon::parse('2026-11-22 12:00:00', 'Asia/Amman'));
        Currency::forget();
        $this->seed(ErpSeeder::class);
        $this->admin = Admin::factory()->create(['is_super' => true]);
        $this->warehouse = Warehouse::create(['code' => 'W1', 'name_ar' => 'المستودع', 'name_en' => 'Warehouse']);
        $this->product = Product::factory()->create(['warehouse_id' => $this->warehouse->id]);
        $this->customer = Partner::create(['code' => 'C1', 'kind' => 'customer', 'name_ar' => 'العميل', 'name_en' => 'Customer']);
        $this->actingAs($this->admin, 'admin');
        app(InvoiceService::class)->adjust($this->admin, ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'quantity' => '1000', 'notes' => 'Opening', 'request_id' => (string) Str::uuid()]);
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }
    private function decimal(string $expected, $actual): void { $this->assertSame(0, D::cmp($expected, (string) $actual), "Expected $expected, got $actual"); }

    private function sale(string $quantity, string $price, array $overrides = []): Invoice
    {
        $this->post(route('admin.invoices.store', 'sales_invoice'), array_replace(['date' => '2026-11-22', 'partner_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id, 'request_id' => (string) Str::uuid(), 'paid' => '0', 'tax_rate' => '0', 'discount' => '0',
            'lines' => [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $price]]], $overrides))->assertSessionHasNoErrors();
        return Invoice::where('type', 'sales_invoice')->latest('id')->firstOrFail();
    }

    private function receipt(string $amount, array $overrides = [], bool $ok = true)
    {
        $response = $this->post(route('admin.receipts.store'), array_replace(['date' => '2026-11-22', 'partner_id' => $this->customer->id,
            'amount' => $amount, 'payment_method' => 'cash', 'request_id' => (string) Str::uuid()], $overrides));
        if (!$ok) { return $response->assertSessionHasErrors(); }
        $response->assertSessionHasNoErrors();
        return Invoice::where('type', InvoiceService::RECEIPT)->latest('id')->firstOrFail();
    }

    public function test_company_name_and_tax_number_replace_the_old_name_everywhere(): void
    {
        $this->assertSame('مصنع عايده الحواجرة للبلاستيك', Context::setting('company_name_ar'));
        $this->assertSame('100335976', Context::setting('tax_number'));
        foreach (['ar' => 'مصنع عايده الحواجرة للبلاستيك', 'en' => 'Aida Al-Hawajreh Plastic Factory'] as $locale => $name) {
            $this->post(route('admin.language', $locale));
            $html = $this->get(route('admin.dashboard'))->assertOk()->assertSee($name)->assertSee('100335976')->getContent();
            $this->assertDoesNotMatchRegularExpression('/dolphin|دولفين/iu', $html);
        }
        auth('admin')->logout();
        $html = $this->get(route('admin.showlogin'))->assertOk()->assertSee('100335976')->getContent();
        $this->assertDoesNotMatchRegularExpression('/dolphin|دولفين/iu', $html);
    }

    public function test_jod_and_usd_are_the_default_currencies_and_documents_keep_their_rate(): void
    {
        $this->assertSame(['JOD', 'USD'], Currency::orderBy('id')->pluck('code')->all());
        $this->assertSame('JOD', Currency::base());
        $jod = $this->sale('2', '3', ['currency' => 'JOD', 'exchange_rate' => '5']);
        $this->assertSame('JOD', $jod->currency); $this->decimal('1', $jod->exchange_rate); // base rate is always 1

        $usd = $this->sale('10', '10', ['currency' => 'USD']);
        $this->assertSame('USD', $usd->currency); $this->decimal('0.709', $usd->exchange_rate); $this->decimal('100', $usd->total);
        $custom = $this->sale('1', '50', ['currency' => 'USD', 'exchange_rate' => '0.71']);
        $this->decimal('0.71', $custom->exchange_rate);
        $this->get(route('admin.invoices.show', $usd))->assertOk()->assertSee('100.00 USD')->assertSee('70.900 JOD');

        $index = $this->get(route('admin.invoices.index', 'sales_invoice'))->assertOk();
        $this->decimal('6', $index->viewData('totals')['JOD']['total']); $this->decimal('150', $index->viewData('totals')['USD']['total']);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('112.400'); // 6 + 70.9 + 35.5 in JOD

        Currency::where('code', 'USD')->update(['active' => false]); Currency::forget();
        $this->post(route('admin.invoices.store', 'sales_invoice'), ['date' => '2026-11-22', 'partner_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'currency' => 'USD', 'request_id' => (string) Str::uuid(), 'lines' => [['product_id' => $this->product->id, 'quantity' => '1', 'unit_price' => '1']]])->assertSessionHasErrors();
    }

    public function test_currency_master_rules(): void
    {
        $this->post(route('admin.masters.store', 'currencies'), ['code' => 'eur', 'name_ar' => 'يورو', 'decimals' => '2', 'exchange_rate' => '0.78', 'active' => '1'])->assertSessionHasNoErrors();
        $eur = Currency::where('code', 'EUR')->firstOrFail(); $this->decimal('0.78', $eur->exchange_rate);
        $this->post(route('admin.masters.store', 'currencies'), ['code' => 'EUR', 'name_ar' => 'مكرر', 'decimals' => '2', 'exchange_rate' => '1', 'active' => '1'])->assertSessionHasErrors();
        $jod = Currency::where('code', 'JOD')->first();
        $this->put(route('admin.masters.update', ['currencies', $jod->id]), ['code' => 'JOD', 'name_ar' => 'دينار', 'decimals' => '3', 'exchange_rate' => '2', 'active' => '0'])->assertSessionHasErrors();
        $this->put(route('admin.masters.update', ['currencies', $jod->id]), ['code' => 'JOD', 'name_ar' => 'دينار', 'decimals' => '3', 'exchange_rate' => '2', 'active' => '1'])->assertSessionHasNoErrors();
        $this->decimal('1', $jod->fresh()->exchange_rate);
        $this->sale('1', '1', ['currency' => 'EUR']);
        $this->put(route('admin.masters.update', ['currencies', $eur->id]), ['code' => 'EUX', 'name_ar' => 'يورو', 'decimals' => '2', 'exchange_rate' => '0.78', 'active' => '1'])->assertSessionHasErrors();
    }

    public function test_receipts_pay_open_invoices_oldest_first_and_keep_excess_as_credit(): void
    {
        $first = $this->sale('10', '1'); $second = $this->sale('15', '1');
        $usd = $this->sale('5', '1', ['currency' => 'USD']);
        $receipt = $this->receipt('20');
        $this->assertStringStartsWith('RC-2026-', $receipt->number); $this->decimal('20', $receipt->paid);
        $this->decimal('10', $first->fresh()->paid); $this->decimal('10', $second->fresh()->paid); $this->decimal('0', $usd->fresh()->paid);

        $request = (string) Str::uuid();
        $chosen = $this->receipt('8', ['invoice_id' => $second->id, 'request_id' => $request]);
        $this->receipt('8', ['invoice_id' => $second->id, 'request_id' => $request]); // retry is harmless
        $this->assertSame(2, Invoice::where('type', InvoiceService::RECEIPT)->count());
        $this->decimal('15', $second->fresh()->paid); $this->decimal('5', $chosen->paid); // 3 left as credit

        $this->receipt('1', ['invoice_id' => $usd->id], false); // currency mismatch
        $this->receipt('1', ['payment_method' => 'cheque'], false); // cheque number and date required
        $cheque = $this->receipt('2', ['currency' => 'USD', 'payment_method' => 'cheque', 'cheque_number' => 'A-77', 'cheque_date' => '2026-12-01', 'bank_name' => 'Arab Bank']);
        $this->assertSame('A-77', $cheque->metadata['cheque_number']); $this->decimal('2', $usd->fresh()->paid);

        // Customer balance in JOD: 25 − 20 − 8 = −3, plus 3 USD open × 0.709.
        $balances = $this->get(route('admin.statements', 'customers'))->assertOk()->viewData('balances');
        $this->decimal('-0.873', $balances[$this->customer->id]);
        $statement = $this->get(route('admin.statements', ['customers', 'partner_id' => $this->customer->id, 'currency' => 'JOD']))->assertOk();
        $this->decimal('-3', $statement->viewData('balance'));
        $this->assertSame(2, collect($statement->viewData('rows'))->where('description', InvoiceService::RECEIPT)->count());
        $this->assertSame(0, collect($statement->viewData('rows'))->where('description', 'payment_received')->count()); // no double counting
        $this->decimal('3', $this->get(route('admin.statements', ['customers', 'partner_id' => $this->customer->id, 'currency' => 'USD']))->viewData('balance'));

        // A later invoice takes the credit when it is applied.
        $third = $this->sale('4', '1');
        $this->post(route('admin.receipts.apply', $chosen))->assertSessionHasNoErrors();
        $this->decimal('3', $third->fresh()->paid); $this->decimal('8', $chosen->fresh()->paid);
        $this->get(route('admin.invoices.show', $third))->assertOk()->assertSee($chosen->number);
    }

    public function test_cancelling_an_invoice_or_receipt_keeps_balances_correct(): void
    {
        $first = $this->sale('10', '1'); $second = $this->sale('10', '1');
        $receipt = $this->receipt('15');
        // Cancelling the second invoice returns its 5 to the customer's credit.
        $this->post(route('admin.invoices.cancel', $second), ['reason' => 'Returned'])->assertSessionHasNoErrors();
        $this->decimal('0', $second->fresh()->paid); $this->decimal('10', $receipt->fresh()->paid);
        $this->assertSame([$first->id], array_column($receipt->fresh()->metadata['allocations'], 'invoice_id'));
        $statement = $this->get(route('admin.statements', ['customers', 'partner_id' => $this->customer->id]))->assertOk();
        $this->decimal('-5', $statement->viewData('balance'));

        $this->post(route('admin.receipts.cancel', $receipt), ['reason' => 'Bounced cheque'])->assertSessionHasNoErrors();
        $this->post(route('admin.receipts.cancel', $receipt), ['reason' => 'Bounced cheque'])->assertSessionHasNoErrors();
        $this->decimal('0', $first->fresh()->paid); $this->assertSame('cancelled', $receipt->fresh()->status);
        $statement = $this->get(route('admin.statements', ['customers', 'partner_id' => $this->customer->id]))->assertOk();
        $this->decimal('10', $statement->viewData('balance'));
        $this->post(route('admin.receipts.apply', $receipt))->assertSessionHasErrors();

        $hr = Admin::factory()->create(); $hr->assignRole('erp_hr'); $this->actingAs($hr, 'admin');
        $this->get(route('admin.receipts.index'))->assertForbidden();
        $this->post(route('admin.receipts.store'), ['date' => '2026-11-22', 'partner_id' => $this->customer->id, 'amount' => '1', 'payment_method' => 'cash', 'request_id' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_new_screens_render_in_both_languages(): void
    {
        $invoice = $this->sale('3', '2', ['currency' => 'USD']); $receipt = $this->receipt('1', ['currency' => 'USD', 'payment_method' => 'bank_transfer', 'bank_name' => 'Bank']);
        foreach (['ar', 'en'] as $locale) {
            $this->post(route('admin.language', $locale));
            foreach ([route('admin.receipts.index'), route('admin.receipts.create'), route('admin.receipts.create', ['invoice' => $invoice->id]), route('admin.receipts.show', $receipt),
                route('admin.masters.index', 'currencies'), route('admin.masters.create', 'currencies'), route('admin.invoices.show', $invoice),
                route('admin.invoices.create', 'sales_invoice'), route('admin.invoices.create', 'purchase_invoice'), route('admin.invoices.create', 'proforma_invoice'),
                route('admin.statements', 'customers'), route('admin.statements', ['customers', 'partner_id' => $this->customer->id]),
                route('admin.statements', ['customers', 'partner_id' => $this->customer->id, 'currency' => 'USD']), route('admin.dashboard')] as $url) {
                $response = $this->get($url)->assertOk();
                $this->assertStringNotContainsString('erp.', strip_tags($response->getContent()), $url);
            }
        }
    }
}
