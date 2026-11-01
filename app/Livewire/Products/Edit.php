<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Livewire\Forms\ProductForm;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\Barcode;
use App\Traits\WithAlert;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Edit product')]
class Edit extends Component
{
    use WithAlert;

    public Product $product;

    public ProductForm $form;

    public function mount(int|string $id): void
    {
        abort_if(Gate::denies('product_update'), 403);

        $this->product = Product::query()->findOrFail($id);
        $this->form->setProduct($this->product);
    }

    public function update(ProductService $productService): void
    {
        abort_if(Gate::denies('product_update'), 403);

        $this->form->validate();
        $productService->update($this->product, $this->form->toProduct(), $this->form->stock_alert);

        $this->alert('success', __('Product updated.'));
    }

    #[Computed]
    public function categories(): array
    {
        return Category::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function generateCode(): void
    {
        $this->form->code = Barcode::nextCatalogueCode();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'code') {
            $this->form->code = trim((string) $this->form->code);
        }
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        return view('livewire.products.edit');
    }
}
