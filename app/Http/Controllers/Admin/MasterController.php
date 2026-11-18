<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterRequest;
use App\Models\Admin\{Classification, InvoiceLine, StockMovement, Warehouse, WorkShift};
use App\Services\Admin\AttendanceService;
use App\Services\Admin\LeaveService;
use App\Services\Admin\Context;
use App\Support\Admin\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MasterController extends Controller
{
    private function definition(string $resource): array
    {
        $definition = config('erp.masters.'.$resource); abort_unless($definition, 404);
        $table = (new $definition['model'])->getTable();
        $definition['fields'] ??= ['code' => 'required|string|max:50|unique:'.$table.',code', 'name_ar' => 'required|string|max:200']
            + ($definition['extra'] ?? []) + ['active' => 'required|boolean', 'notes' => 'nullable|string|max:2000'];
        return $definition;
    }

    public function index(Request $request, string $resource)
    {
        $definition = $this->definition($resource);
        Context::authorize($request->user('admin'), $definition['module'].'.view');
        $query = $definition['model']::where($definition['scope'] ?? []);
        if ($request->filled('q')) {
            $fields = array_intersect(array_keys($definition['fields']), ['code', 'sku', 'name_ar', 'phone']);
            if ($fields) { $query->where(function ($q) use ($fields, $request) { foreach ($fields as $field) { $q->orWhere($field, 'like', '%'.$request->q.'%'); } }); }
        }
        if ($resource === 'attendance') { $query->when($request->date, fn ($q, $d) => $q->where('date', $d)); }
        if (in_array($resource, ['products', 'customers', 'suppliers'])) { $query->when($request->classification_id, fn ($q, $id) => $q->where('classification_id', $id)); }
        if ($resource === 'products') {
            $query->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
                ->when(in_array($request->item_type, ['raw', 'finished'], true) ? $request->item_type : null, fn ($q, $type) => $q->where('item_type', $type));
        }
        return view('admin.masters.index', ['resource' => $resource, 'definition' => $definition, 'records' => $query->latest('id')->paginate(20)->withQueryString(), 'options' => $this->options(array_keys($definition['fields']), $resource)]);
    }

    public function form(Request $request, string $resource, ?int $record = null)
    {
        $definition = $this->definition($resource);
        Context::authorize($request->user('admin'), $definition['module'].'.manage');
        $record = $record ? $definition['model']::where($definition['scope'] ?? [])->findOrFail($record) : new $definition['model'];
        return view('admin.masters.form', compact('resource', 'definition', 'record') + ['options' => $this->options(array_keys($definition['fields']), $resource)]);
    }

    public function save(MasterRequest $request, string $resource, ?int $record = null)
    {
        $definition = $this->definition($resource); $data = $request->validated();
        // A raw material has no selling price; store zero instead of a stale value.
        if ($resource === 'products' && $data['item_type'] === 'raw') { $data['selling_price'] = '0'; }
        DB::transaction(function () use ($definition, $data, $resource, $record) {
            Context::lock();
            $model = $record ? $definition['model']::where($definition['scope'] ?? [])->lockForUpdate()->findOrFail($record) : new $definition['model'];
            foreach ($definition['fields'] as $field => $rule) { if (str_contains($rule, 'numeric') && array_key_exists($field, $data)) { $data[$field] = D::of($data[$field]); } }
            if (isset($data['name_ar'])) { $data['name_en'] = $data['name_ar']; }
            if (in_array($resource, ['products', 'customers', 'suppliers'])) {
                if (!$record) { $data[$resource === 'products' ? 'sku' : 'code'] = (string) Str::uuid(); }
                $kind = ['products' => 'product', 'customers' => 'customer', 'suppliers' => 'supplier'][$resource];
                $data['classification_id'] ??= null;
                D::check(!$data['classification_id'] || Classification::where('kind', $kind)->where('active', true)->whereKey($data['classification_id'])->exists(), 'invalid_category');
            }
            if ($resource === 'employees') {
                D::check(WorkShift::whereKey($data['shift_id'])->where('active', true)->exists(), 'shift_required');
                D::check(empty($data['username']) || !empty($data['password']) || (bool) $model->password, 'employee_password_required');
                if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }
                unset($data['password_confirmation']);
            }
            if ($resource === 'products') {
                D::check(!$record || (!StockMovement::where('product_id', $record)->exists() && !InvoiceLine::where('product_id', $record)->exists()) || (int) $data['base_unit_id'] === $model->base_unit_id, 'immutable_product');
                D::check((int) $data['warehouse_id'] === (int) $model->warehouse_id || Warehouse::whereKey($data['warehouse_id'])->where('active', true)->exists(), 'inactive_record');
                $data['purchase_unit_id'] = $data['sales_unit_id'] = $data['base_unit_id'];
            }
            if ($resource === 'attendance') {
                D::check(!DB::table('erp_attendance')->where('employee_id', $data['employee_id'])->where('date', $data['date'])->when($record, fn ($q) => $q->where('id', '!=', $record))->exists(), 'duplicate_record');
                $data = app(AttendanceService::class)->manual($data, $model);
            }
            $model->fill($data + ($definition['scope'] ?? []))->save();
            if ($resource === 'employees' && array_key_exists('annual_leave_days', $data)) { app(LeaveService::class)->setAllowance($model); }
        }, 3);
        return redirect()->route('admin.masters.index', $resource)->with('success', __('erp.saved'));
    }

    public function options(array $fields, string $resource = ''): array
    {
        $options = [];
        foreach ($fields as $field) {
            if ($class = config('erp.relations.'.$field)) { $options[$field] = $class::orderBy('id')->get()->mapWithKeys(fn ($row) => [$row->id => $row->label])->all(); }
        }
        if (in_array('classification_id', $fields)) {
            $kind = ['products' => 'product', 'customers' => 'customer', 'suppliers' => 'supplier'][$resource];
            $options['classification_id'] = Classification::where('kind', $kind)->orderBy('name_ar')->get()->mapWithKeys(fn ($row) => [$row->id => $row->label])->all();
        }
        return $options;
    }
}
