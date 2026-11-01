<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TrackingReports;
use App\Support\Branches;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Excel / PDF downloads of the stock-tracking reports, with the same filters
 * (and the same branch limits) as the Reports screen.
 */
class TrackingReportExportController extends Controller
{
    private const TITLES = [
        'losses' => 'Losses',
        'transfers' => 'Transfers',
        'usage' => 'Usage',
        'compare' => 'Branch comparison',
        'value' => 'Stock value',
        'movements' => 'Movements',
        'corrections' => 'Corrections',
    ];

    public function __invoke(Request $request): Response
    {
        abort_if(Gate::denies('transfer_access'), 403);

        $data = $request->validate([
            'tab' => ['required', 'in:losses,transfers,usage,compare,value,movements,corrections'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'warehouse' => ['nullable', 'integer'],
            'action' => ['nullable', 'string'],
            'format' => ['required', 'in:xlsx,pdf'],
        ]);

        $from = Carbon::parse($data['from'] ?? today()->startOfMonth());
        $to = Carbon::parse($data['to'] ?? today());
        $warehouse = isset($data['warehouse']) && Branches::allows((int) $data['warehouse']) ? (int) $data['warehouse'] : null;
        $reports = new TrackingReports($from->min($to), $to->max($from), $warehouse);

        [$headings, $rows, $summary] = $this->table($data['tab'], $reports, $data['action'] ?? null);

        $title = self::TITLES[$data['tab']];
        $period = $data['tab'] === 'value'
            ? 'As at ' . now()->format('j M Y H:i')
            : $from->min($to)->format('j M Y') . ' – ' . $to->max($from)->format('j M Y');
        $scope = $reports->locations()->count() === 1 ? $reports->locations()->first()->name : 'All locations';
        $filename = 'sparkle-' . str($title)->slug() . '-' . now()->format('Y-m-d');

        return $data['format'] === 'xlsx'
            ? $this->excel($filename, $title, "{$period} · {$scope}", $headings, $rows, $summary)
            : $this->pdf($filename, $title, "{$period} · {$scope}", $headings, $rows, $summary);
    }

    /** @return array{0: array<string>, 1: iterable<array>, 2: array<string, string>} */
    private function table(string $tab, TrackingReports $reports, ?string $action): array
    {
        $ugx = fn ($v) => 'UGX ' . number_format((float) $v);

        return match ($tab) {
            'losses' => (function () use ($reports, $ugx) {
                $d = $reports->losses();

                return [
                    ['Date', 'Unit', 'Product', 'Box', 'Location', 'Transfer', 'Sent by', 'Received by', 'Cost (UGX)', 'Selling (UGX)'],
                    $d['rows']->map(fn ($r) => [$r['date']->format('Y-m-d H:i'), $r['code'], $r['product'], $r['box'], $r['location'], $r['transfer'], $r['dispatched_by'], $r['received_by'], $r['cost'], $r['price']]),
                    ['Units missing' => (string) $d['totals']['units'], 'Value lost (cost)' => $ugx($d['totals']['cost']), 'Loss rate' => $d['totals']['rate'] === null ? '—' : $d['totals']['rate'] . '%', 'Units sent' => (string) $d['totals']['sent']],
                ];
            })(),
            'transfers' => (function () use ($reports) {
                $d = $reports->transfers();

                return [
                    ['Transfer', 'From', 'To', 'Dispatched', 'Dispatched by', 'Received', 'Received by', 'Hours', 'Sent', 'Received units', 'Missing', 'Status'],
                    $d['rows']->map(fn ($r) => [$r['reference'], $r['from'], $r['to'], $r['dispatched_at']->format('Y-m-d H:i'), $r['dispatched_by'], $r['received_at']?->format('Y-m-d H:i'), $r['received_by'], $r['hours'], $r['sent'], $r['received'], $r['missing'], $r['status']->label()]),
                    ['Transfers' => (string) $d['totals']['count'], 'Units sent' => (string) $d['totals']['units'], 'Average delivery' => $d['totals']['avg_hours'] === null ? '—' : $d['totals']['avg_hours'] . ' h', 'Arrived complete' => $d['totals']['full_rate'] === null ? '—' : $d['totals']['full_rate'] . '%'],
                ];
            })(),
            'usage' => (function () use ($reports) {
                $d = $reports->usage();

                return [
                    ['Item', 'Product', 'Branch', 'Status', 'Clients (period)', 'Clients (all time)', 'First used', 'Last used'],
                    $d['rows']->map(fn ($r) => [$r['code'], $r['product'], $r['location'], $r['status']?->label(), $r['clients'], $r['lifetime'], $r['first_at']->format('Y-m-d H:i'), $r['last_at']->format('Y-m-d H:i')]),
                    ['Clients served' => (string) $d['totals']['clients'], 'Items used' => (string) $d['totals']['units'], 'Items finished' => (string) $d['totals']['finished'], 'Clients per item' => $d['totals']['per_unit'] === null ? '—' : (string) $d['totals']['per_unit']]
                    + $d['byLocation']->filter(fn ($r) => $r['clients'] > 0)->mapWithKeys(fn ($r) => ['Clients – ' . $r['location'] => (string) $r['clients']])->all(),
                ];
            })(),
            'compare' => (function () use ($reports) {
                $d = $reports->branchComparison();
                $locs = $d['locations'];

                return [
                    ['Product', ...$locs->map(fn ($w) => $w->name . ' – customers per item')->all(), 'Average', 'Extra cost per customer (UGX)'],
                    $d['rows']->map(fn ($r) => [$r['product'], ...$locs->map(fn ($w) => $r['cells'][$w->id]['per_fill'])->all(), $r['average'], $r['cost_gap']]),
                    ['Products compared' => (string) $d['rows']->count(), 'Locations' => $locs->pluck('name')->join(', ')],
                ];
            })(),
            'corrections' => (function () use ($reports) {
                $rows = $reports->corrections();

                return [
                    ['Date', 'Correction', 'Reference', 'Location', 'Units', 'By', 'Reason'],
                    $rows->map(fn ($r) => [$r['date']->format('Y-m-d H:i'), $r['type'], $r['reference'], $r['location'], $r['units'], $r['by'], $r['reason']]),
                    ['Corrections' => (string) $rows->count()],
                ];
            })(),
            'value' => (function () use ($reports, $ugx) {
                $d = $reports->stockValue();

                return [
                    ['Location', 'Units in stock (sealed + in use)', 'Of which in use', 'Value at cost (UGX)', 'Value at selling price (UGX)'],
                    $d['byLocation']->map(fn ($r) => [$r['location'], $r['units'], $r['in_use'], $r['cost'], $r['price']])
                        ->push(['Total', $d['totals']['units'], $d['totals']['in_use'], $d['totals']['cost'], $d['totals']['price']])
                        ->push(['In transit', $d['inTransit']['units'], null, $d['inTransit']['cost'], $d['inTransit']['price']]),
                    ['Units in stock' => (string) $d['totals']['units'], 'Value at cost' => $ugx($d['totals']['cost']), 'Value at selling price' => $ugx($d['totals']['price'])],
                ];
            })(),
            default => [
                ['When', 'Unit', 'Product', 'Box', 'Action', 'Location', 'By', 'Transfer'],
                $reports->movementsQuery($action)->limit(20000)->get()->map(fn ($s) => [
                    $s->created_at->format('Y-m-d H:i'), $s->code, $s->product?->name, $s->box?->code, $s->action->label(), $s->warehouse?->name, $s->user?->name, $s->transfer?->reference,
                ]),
                $reports->movementSummary()->filter(fn ($s) => $s['count'] > 0)->mapWithKeys(fn ($s) => [$s['action']->label() => (string) $s['count']])->all(),
            ],
        };
    }

    private function excel(string $filename, string $title, string $subtitle, array $headings, iterable $rows, array $summary): Response
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(substr($title, 0, 31));

        $sheet->setCellValue('A1', "Sparkle Salons – {$title}");
        $sheet->setCellValue('A2', $subtitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 4;
        foreach ($summary as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++;
        $sheet->fromArray($headings, null, "A{$row}");
        $headerRange = "A{$row}:" . $sheet->getHighestColumn() . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->freezePane('A' . ($row + 1));

        $sheet->fromArray(collect($rows)->map(fn ($r) => array_values($r))->all(), null, 'A' . ($row + 1));

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function pdf(string $filename, string $title, string $subtitle, array $headings, iterable $rows, array $summary): Response
    {
        $pdf = PDF::loadView('exports.tracking-report', compact('title', 'subtitle', 'headings', 'rows', 'summary'), [], [
            'format' => 'A4-L',
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_left' => 10,
            'margin_right' => 10,
        ]);

        // stream() echoes the file itself and returns nothing; build a real response instead.
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}.pdf\"",
        ]);
    }
}
