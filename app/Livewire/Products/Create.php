<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Livewire\Forms\ProductForm;
use App\Models\Category;
use App\Services\ProductService;
use App\Support\Barcode;
use App\Traits\WithAlert;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Create extends Component
{
    use WithAlert;

    public bool $createModal = false;

    public ProductForm $form;

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        abort_if(Gate::denies('product_create'), 403);

        return view('livewire.products.create');
    }

    #[On('createModal')]
    public function openModal(): void
    {
        $this->resetErrorBag();
        $this->form->reset();
        $this->createModal = true;
    }

    public function create(ProductService $productService): void
    {
        abort_if(Gate::denies('product_create'), 403);

        $this->form->validate();
        $product = $productService->create($this->form->toProduct(), $this->form->stock_alert);

        $this->alert('success', __(':name added. Receive its stock under Receive new stock.', ['name' => $product->name]));
        $this->dispatch('refreshIndex')->to(Index::class);
        $this->createModal = false;
    }

    #[Computed]
    public function categories(): array
    {
        return Category::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Fill in a catalogue code for a product that has no barcode of its own. */
    public function generateCode(): void
    {
        $this->form->code = Barcode::nextCatalogueCode();
    }

    /** Clean up what a USB scanner types (it often adds spaces or a newline). */
    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'code') {
            $this->form->code = trim((string) $this->form->code);
        }
    }
}
