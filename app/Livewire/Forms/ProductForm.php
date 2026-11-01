<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Product;
use App\Support\Barcode;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The basics a salon product needs. Stock is never typed in here: it arrives
 * through "Receive new stock" so every unit gets its own label.
 */
class ProductForm extends Form
{
    public const UNITS = ['pcs', 'bottle', 'tub', 'tube', 'jar', 'drum', 'jerrycan', 'sachet', 'pack', 'kit'];

    public ?string $productId = null;

    public string $name = '';

    public ?string $code = null;

    public ?int $category_id = null;

    public string $unit = 'bottle';

    public $price = null;

    public $cost = null;

    public int $stock_alert = 0;

    public bool $is_refillable = false;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('products', 'code')->ignore($this->productId)->whereNull('deleted_at')],
            'category_id' => ['required', 'exists:categories,id'],
            'unit' => ['required', Rule::in(self::UNITS)],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock_alert' => ['required', 'integer', 'min:0'],
            'is_refillable' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'code' => __('barcode'),
            'category_id' => __('category'),
            'price' => __('selling price'),
            'cost' => __('cost price'),
            'stock_alert' => __('re-order level'),
        ];
    }

    public function setProduct(Product $product): void
    {
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->code = $product->code;
        $this->category_id = $product->category_id;
        $this->unit = in_array($product->unit, self::UNITS, true) ? $product->unit : 'pcs';
        $this->price = (float) $product->price;
        $this->cost = (float) $product->cost;
        $this->stock_alert = (int) ($product->warehouses()->max('product_warehouse.stock_alert') ?? 0);
        $this->is_refillable = (bool) $product->is_refillable;
    }

    /** Values for the products table. */
    public function toProduct(): array
    {
        return [
            'name' => trim($this->name),
            'code' => trim((string) $this->code),
            'barcode_symbology' => Barcode::detect($this->code),
            'category_id' => $this->category_id,
            'unit' => $this->unit,
            'price' => (float) $this->price,
            'cost' => (float) ($this->cost ?? 0),
            'is_refillable' => $this->is_refillable,
        ];
    }
}
