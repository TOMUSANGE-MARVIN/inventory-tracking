<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Services\StockTakeService;
use App\Services\StockTrackingService;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Count everything physically at a location and compare it with the system.
 * Super admins can count any location; branch admins only their own branch.
 */
#[Layout('layouts.app', ['plain' => true])]
#[Title('Stock-take')]
class StockTakes extends Component
{
    #[Url(as: 'take')]
    public ?int $takeId = null;

    public string $warehouse = '';

    public bool $markMissing = true;

    public string $note = '';

    public ?array $flash = null;

    public function mount(): void
    {
        abort_if(Gate::denies('adjustment_create'), 403);

        $this->warehouse = (string) (Branches::warehouses()->first()?->id ?? '');
    }

    private function take(): ?StockTake
    {
        $take = $this->takeId ? StockTake::query()->with('warehouse:id,name', 'starter:id,name', 'finisher:id,name')->find($this->takeId) : null;

        return $take && Branches::allows($take->warehouse_id) ? $take : null;
    }

    public function start(StockTakeService $stockTakes): void
    {
        abort_if(Gate::denies('adjustment_create'), 403);
        Branches::authorize((int) $this->warehouse);

        try {
            $take = $stockTakes->start((int) $this->warehouse);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->takeId = $take->id;
        $this->flash = ['type' => 'success', 'message' => __('Stock-take :ref started at :where. Scan every item and box that is physically here.', ['ref' => $take->reference, 'where' => $take->warehouse?->name])];
    }

    public function scanned(string $code, StockTakeService $stockTakes, StockTrackingService $tracking): void
    {
        $take = $this->take();
        abort_unless($take !== null, 403);

        try {
            $this->flash = $stockTakes->scan($take, $code, $tracking);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];
        }
    }

    public function finish(StockTakeService $stockTakes): void
    {
        $take = $this->take();
        abort_unless($take !== null, 403);

        try {
            $take = $stockTakes->finish($take, $this->markMissing, $this->note ?: null);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->flash = ['type' => 'success', 'message' => __('Stock-take :ref finished: :found found, :missing not found, :unexpected unexpected.', [
            'ref' => $take->reference, 'found' => $take->found, 'missing' => $take->not_found, 'unexpected' => $take->unexpected,
        ])];
        $this->reset('note');
    }

    public function cancel(StockTakeService $stockTakes): void
    {
        $take = $this->take();
        abort_unless($take !== null, 403);

        $stockTakes->cancel($take);
        $this->takeId = null;
        $this->flash = ['type' => 'success', 'message' => __('Stock-take cancelled. Nothing was changed.')];
    }

    public function close(): void
    {
        $this->takeId = null;
        $this->flash = null;
    }

    public function render()
    {
        abort_if(Gate::denies('adjustment_create'), 403);

        $stockTakes = app(StockTakeService::class);
        $take = $this->take();
        $locations = Branches::warehouses();

        $data = ['take' => $take, 'locations' => $locations];

        if ($take) {
            $lines = $take->lines()->with('item.product:id,name', 'systemWarehouse:id,name')->latest('id')->get();
            $data += [
                'lines' => $lines,
                'found' => $lines->where('result', StockTakeItem::FOUND),
                'unexpected' => $lines->where('result', StockTakeItem::UNEXPECTED),
                'notFound' => $lines->where('result', StockTakeItem::NOT_FOUND),
                'pending' => $take->status === StockTake::OPEN
                    ? $stockTakes->notYetCounted($take)->with('product:id,name')->orderBy('product_id')->orderBy('id')->get()
                    : collect(),
            ];
        } else {
            $data += [
                'open' => Branches::scope(StockTake::query())->with('warehouse:id,name', 'starter:id,name')->where('status', StockTake::OPEN)->get(),
                'history' => Branches::scope(StockTake::query())->with('warehouse:id,name', 'finisher:id,name')->where('status', StockTake::FINISHED)->latest('finished_at')->limit(20)->get(),
            ];
        }

        return view('livewire.tracking.stock-takes', $data);
    }
}
