<?php

declare(strict_types=1);

use App\Livewire\Analytics\AnalyticsDashboard;
use App\Models\User;
use Livewire\Livewire;

it('loads the analytics dashboard without errors and fills the overview', function () {
    Illuminate\Support\Facades\Cache::flush(); // the revenue report is cached by date range

    $this->actingAs(User::factory()->create()->assignRole('admin'));

    Livewire::test(AnalyticsDashboard::class)
        ->assertOk()
        ->assertNotDispatched('alert')
        ->assertSet('overview.total_sales', App\Models\Sale::query()
            ->whereBetween('created_at', [now()->subDays(30)->startOfDay(), now()->endOfDay()])
            ->where('status', '!=', App\Enums\SaleStatus::CANCELED->value)->count())
        ->assertSee('Revenue Trend');
});
