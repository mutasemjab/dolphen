<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $definition = config('erp.masters.'.$this->route('resource'));
        return $definition && $this->user('admin')?->can('erp.'.$definition['module'].'.manage');
    }

    public function rules(): array
    {
        $definition = config('erp.masters.'.$this->route('resource')); if (!$definition) { return []; }
        $table = (new $definition['model'])->getTable();
        $rules = $definition['fields'] ?? (['code' => 'required|string|max:50|unique:'.$table.',code', 'name_ar' => 'required|string|max:200'] + ($definition['extra'] ?? []) + ['active' => 'required|boolean', 'notes' => 'nullable|string|max:2000']);
        $id = (int) $this->route('record');
        if ($id) { foreach ($rules as &$rule) { $rule = preg_replace('/(unique:[^|]+)/', '$1,'.$id, $rule); } }
        return $rules;
    }

    public function attributes(): array
    {
        return collect(array_keys($this->rules()))->mapWithKeys(fn ($field) => [$field => __('erp.'.$field)])->all();
    }
}
