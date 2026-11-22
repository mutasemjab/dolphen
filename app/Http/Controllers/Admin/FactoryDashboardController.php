<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\{Invoice, Product, Employee};
use App\Services\Admin\InvoiceService;
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;

class FactoryDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $actor = $request->user('admin'); $cards = []; $types = [];
        foreach (InvoiceService::TYPES as $type => $module) {
            if ($actor->can('erp.'.$module.'.view')) {
                $types[] = $type;
                $rows = Invoice::where('type', $type)->where('status', 'posted')->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->get();
                $cards[$type] = D::sum($rows->map(fn ($row) => $row->base($row->total))); // converted to the base currency
            }
        }
        if ($actor->can('erp.products.view')) { $cards['products'] = Product::where('active', true)->count(); }
        if ($actor->can('erp.hr.view')) { $cards['employees'] = Employee::where('active', true)->count(); }
        $invoices = Invoice::with('partner')->whereIn('type', $types)->latest('id')->limit(8)->get();
        return view('admin.overview', compact('cards', 'invoices'));
    }
}
