<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\ScanAction;
use App\Services\TrackingReports;
use App\Support\Branches;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Reports')]
class Reports extends Component
{
    use WithPagination;

    public const TABS = ['losses', 'transfers', 'usage', 'compare', 'value', 'movements', 'corrections'];

    #[Url]
    public string $tab = 'losses';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $action = '';

    /** Product shown in the branch comparison chart. */
    #[Url(as: 'product')]
    public string $compareProduct = '';

    public function mount(): void
    {
        abort_if(Gate::denies('transfer_access'), 403);

        $this->from = $this->validDate($this->from) ?? today()->startOfMonth()->toDateString();
        $this->to = $this->validDate($this->to) ?? today()->toDateString();
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'losses';
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    /** Quick ranges: this week, this month, last month, last 30 days. */
    public function range(string $preset): void
    {
        [$from, $to] = match ($preset) {
            'week' => [today()->startOfWeek(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            '30' => [today()->subDays(29), today()],
            default => [today()->startOfMonth(), today()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->resetPage();
    }

    private function validDate(string $value): ?string
    {
        try {
            return $value !== '' ? Carbon::parse($value)->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function reports(): TrackingReports
    {
        $from = Carbon::parse($this->validDate($this->from) ?? today()->startOfMonth());
        $to = Carbon::parse($this->validDate($this->to) ?? today());

        // A branch admin's location filter can only pick their own branches.
        $warehouse = $this->warehouse !== '' && Branches::allows((int) $this->warehouse) ? (int) $this->warehouse : null;

        return new TrackingReports($from->min($to), $to->max($from), $warehouse);
    }

    /** Query string for the Excel/PDF download links, matching what is on screen. */
    public function exportQuery(string $format): string
    {
        return http_build_query(array_filter([
            'tab' => $this->tab, 'from' => $this->from, 'to' => $this->to,
            'warehouse' => $this->warehouse, 'action' => $this->action, 'format' => $format,
        ]));
    }

    public function render()
    {
        abort_if(Gate::denies('transfer_access'), 403);

        $reports = $this->reports();

        return view('livewire.tracking.reports', [
            'locations' => Branches::warehouses(),
            'actions' => ScanAction::cases(),
            'data' => match ($this->tab) {
                'transfers' => $reports->transfers(),
                'value' => $reports->stockValue(),
                'usage' => $reports->usage(),
                'compare' => $reports->branchComparison(),
                'corrections' => ['rows' => $reports->corrections()],
                'movements' => [
                    'summary' => $reports->movementSummary(),
                    'scans' => $reports->movementsQuery($this->action ?: null)->paginate(25),
                ],
                default => $reports->losses(),
            },
        ]);
    }
}
