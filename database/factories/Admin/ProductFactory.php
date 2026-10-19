<?php

namespace Database\Factories\Admin;

use App\Models\Admin\{Product, Unit};
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;
    public function definition(): array
    {
        $unit = Unit::firstOrCreate(['code' => 'piece'], ['name_ar' => 'قطعة', 'name_en' => 'Piece']);
        return ['sku' => 'TEST-'.$this->faker->unique()->bothify('??######'), 'name_ar' => 'صنف للاختبار', 'name_en' => 'Test item', 'item_type' => 'raw', 'base_unit_id' => $unit->id, 'purchase_unit_id' => $unit->id, 'sales_unit_id' => $unit->id, 'cost' => '2.000000', 'selling_price' => '3.000000'];
    }
}
