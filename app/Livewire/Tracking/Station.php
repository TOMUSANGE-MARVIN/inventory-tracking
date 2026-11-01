<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\ScanAction;
use App\Models\StockScan;
use App\Services\StockTrackingService;
use App\Support\AccessRoles;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Scan station')]
class Station extends Component
{
    #[Url]
    public string $action = 'served_client';

    /** Clients worked on with the item in this scan (client-served mode). */
    public int $clients = 1;

    public ?array $flash = null;

    /** The last client scan, for the running count and Undo. */
    public ?int $lastServeId = null;

    public function mount(): void
    {
        abort_if(Gate::denies('station_scan'), 403);
    }

    /** Workers record clients and mark items done; opening items is for branch admins. */
    private function isWorker(): bool
    {
        return AccessRoles::isWorkerOnly(auth()->user());
    }

    /** served_client | done | in_use (in_use is not offered to workers). */
    private function mode(): string
    {
        $modes = $this->isWorker() ? ['served_client', 'done'] : ['served_client', 'done', 'in_use'];

        return in_array($this->action, $modes, true) ? $this->action : 'served_client';
    }

    /** Used up: refillable items go to "Needs refill", others are disposed of. */
    private function finishItem(\App\Models\StockItem $item, StockTrackingService $service): void
    {
        try {
            $service->finish($item);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $clients = (int) StockScan::query()->where('stock_item_id', $item->id)->where('action', ScanAction::SERVED_CLIENT)->sum('quantity');
        $this->lastServeId = null;
        $this->flash = ['type' => 'success', 'message' => ($item->product->is_refillable
            ? __(':code – :product is empty and waiting for a refill (same barcode).', ['code' => $item->code, 'product' => $item->product->name])
            : __(':code – :product is finished and disposed of.', ['code' => $item->code, 'product' => $item->product->name]))
            . ' ' . trans_choice('It served :n client.|It served :n clients.', $clients, ['n' => $clients])];
    }

    /** "Mark as done" for the item just scanned for a client. */
    public function finishLast(StockTrackingService $service): void
    {
        $scan = $this->lastServeId ? StockScan::query()->find($this->lastServeId) : null;
        $item = $scan?->item;

        if (! $item || ! Branches::allows($item->warehouse_id)) {
            return;
        }

        $this->finishItem($item, $service);
    }

    public function scanned(string $code, StockTrackingService $service): void
    {
        $item = $service->findItem($code);

        if (! $item) {
            $this->flash = ['type' => 'error', 'message' => $service->findBox($code)
                ? __(':code is a box. Scan the barcode on the unit you are using.', ['code' => strtoupper(trim($code))])
                : __('No item found for code :code. Scan the item\'s SP- label.', ['code' => $code])];

            return;
        }

        if (! Branches::allows($item->warehouse_id)) {
            $this->flash = ['type' => 'error', 'message' => __(':code is not at your branch (:where).', ['code' => $item->code, 'where' => $item->whereabouts() ?: '—'])];

            return;
        }

        abort_if(Gate::denies('station_scan'), 403);

        $mode = $this->mode();

        if ($mode === 'served_client') {
            $this->serve($item, $service);

            return;
        }

        if ($mode === 'done') {
            $this->finishItem($item, $service);

            return;
        }

        $action = ScanAction::IN_USE;

        try {
            $service->record($item, $action);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->flash = ['type' => 'success', 'message' => $action->label() . ': ' . $item->code . ' – ' . $item->product->name . ' (' . $item->warehouse?->name . ')'];
    }

    private function serve(\App\Models\StockItem $item, StockTrackingService $service): void
    {
        try {
            $scan = $service->serveClient($item, max(1, min(50, $this->clients)));
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->lastServeId = $scan->id;
        $this->clients = 1;
        $total = (int) StockScan::query()->where('stock_item_id', $item->id)->where('action', ScanAction::SERVED_CLIENT)->sum('quantity');

        $this->flash = ['type' => 'success', 'message' => trans_choice(':code – :product: :n client recorded at :where.|:code – :product: :n clients recorded at :where.', $scan->quantity, [
            'code' => $item->code, 'product' => $item->product->name, 'n' => $scan->quantity, 'where' => $item->warehouse?->name,
        ]) . ' ' . trans_choice('This item has now served :total client.|This item has now served :total clients.', $total, ['total' => $total])];
    }

    public function undo(StockTrackingService $service): void
    {
        $scan = $this->lastServeId ? StockScan::query()->find($this->lastServeId) : null;

        if (! $scan) {
            return;
        }

        try {
            $service->undoServe($scan);
            $this->flash = ['type' => 'success', 'message' => __('Undone – :code client scan removed.', ['code' => $scan->code])];
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];
        }

        $this->lastServeId = null;
    }

    public function render()
    {
        $worker = $this->isWorker();

        $this->action = $this->mode();
        $last = $this->lastServeId ? StockScan::query()->with('item.product:id,name,is_refillable')->find($this->lastServeId) : null;

        // A worker sees only their own scans; admins see the branch's.
        $mine = fn ($q) => $q->when($worker, fn ($q) => $q->where('user_id', auth()->id()));

        return view('livewire.tracking.station', [
            'worker' => $worker,
            'modes' => array_filter([
                'served_client' => ['user-check', __('Client served'), 'bg-teal-100 text-teal-800'],
                'done' => ['flag-checkered', __('Item finished'), 'bg-gray-200 text-gray-800'],
                'in_use' => $worker ? null : ['box-open', __('Open (put into use)'), 'bg-blue-100 text-blue-800'],
            ]),
            'lastItem' => $last?->item,
            'servedToday' => (int) Branches::scope(StockScan::query())->tap($mine)
                ->where('action', ScanAction::SERVED_CLIENT)->whereDate('created_at', today())->sum('quantity'),
            'recent' => Branches::scope(StockScan::query())->tap($mine)
                ->with('product:id,name', 'warehouse:id,name', 'user:id,name')
                ->whereIn('action', ScanAction::stationActions())
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
