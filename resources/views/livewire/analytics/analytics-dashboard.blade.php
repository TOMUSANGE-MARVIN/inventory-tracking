<div>
    <div class="print:hidden">
        <div class="w-full">
            <div class="flex items-center justify-between py-4">
                <div>
                    <div class="text-sm text-gray-500 mb-1">
                        {{ __('Analytics') }}
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">
                        {{ __('Analytics Dashboard') }}
                    </h2>
                </div>
                <div class="print:hidden">
                    <div class="flex space-x-2">
                        <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium flex items-center" wire:click="exportReport">
                            <i class="ti ti-download mr-1"></i>
                            {{ __('Export Report') }}
                        </button>
                        <button type="button" class="border border-blue-600 text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-md font-medium flex items-center" wire:click="loadAnalytics">
                            <i class="ti ti-refresh mr-1"></i>
                            {{ __('Refresh') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="py-6">
        <div class="w-full">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Date From') }}</label>
                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" wire:model.live="dateFrom">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Date To') }}</label>
                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" wire:model.live="dateTo">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Product') }}</label>
                    <x-select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" wire:model.live="selectedProduct">
                        <option value="">{{ __('All Products') }}</option>
                        @foreach($this->products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex items-end">
                    <button type="button" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md font-medium flex items-center" wire:click="resetFilters">
                        <i class="ti ti-x mr-1"></i>
                        {{ __('Reset') }}
                    </button>
                </div>
            </div>

          
                <!-- Analytics Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">{{ __('Total Revenue') }}</p>
                                <p class="text-2xl font-bold text-gray-900">{{ format_currency($overview['total_revenue'] ?? 0) }}</p>
                            </div>
                            <div class="p-3 bg-blue-100 rounded-full">
                                <i class="ti ti-chart-line text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: 75%"></div>
                                </div>
                                <span class="ml-2 text-sm text-gray-500">75%</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">{{ __('Total Sales') }}</p>
                                <p class="text-2xl font-bold text-gray-900">{{ number_format($overview['total_sales'] ?? 0) }}</p>
                            </div>
                            <div class="p-3 bg-green-100 rounded-full">
                                <i class="ti ti-shopping-cart text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: 60%"></div>
                                </div>
                                <span class="ml-2 text-sm text-gray-500">60%</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">{{ __('Avg Order Value') }}</p>
                                <p class="text-2xl font-bold text-gray-900">{{ format_currency($overview['average_order_value'] ?? 0) }}</p>
                            </div>
                            <div class="p-3 bg-yellow-100 rounded-full">
                                <i class="ti ti-currency-dollar text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 45%"></div>
                                </div>
                                <span class="ml-2 text-sm text-gray-500">45%</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">{{ __('Growth Rate') }}</p>
                                <p class="text-2xl font-bold text-gray-900">{{ isset($overview['growth_rate']) ? number_format($overview['growth_rate'], 1) . '%' : '—' }}</p>
                            </div>
                            <div class="p-3 bg-purple-100 rounded-full">
                                <i class="ti ti-trending-up text-purple-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-600 h-2 rounded-full" style="width: 80%"></div>
                                </div>
                                <span class="ml-2 text-sm text-gray-500">80%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Revenue Chart -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('Revenue Trend') }}</h3>
                            </div>
                            <div class="p-6">
                                @if(!empty($overview['daily_revenue']))
                                    <div wire:key="revenue-{{ md5(json_encode($overview['daily_revenue'])) }}" wire:ignore class="h-[300px]"
                                        x-data="vizChart('area', @js(['series' => [['name' => __('Revenue'), 'data' => array_values($overview['daily_revenue'])]], 'labels' => collect(array_keys($overview['daily_revenue']))->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('j M'))->all()]))"></div>
                                @else
                                    <div class="text-center py-12">
                                        <div class="mx-auto h-24 w-24 text-gray-400 mb-4">
                                            <i class="ti ti-chart-line text-6xl"></i>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No revenue data available') }}</h3>
                                        <p class="text-gray-500">
                                            {{ __('Revenue data will appear here once you have sales in the selected date range.') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('Top Products') }}</h3>
                            </div>
                            <div class="p-6">
                                @if(!empty($overview['top_products']))
                                    <div class="space-y-4">
                                        @foreach($overview['top_products'] as $product)
                                            <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
                                                <div class="flex-1">
                                                    <div class="text-sm font-medium text-gray-900">{{ $product['name'] }}</div>
                                                    <div class="text-xs text-gray-500">{{ number_format($product['quantity']) }} {{ __('sold') }}</div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-medium text-gray-900">{{ format_currency($product['revenue']) }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <div class="mx-auto h-16 w-16 text-gray-400 mb-4">
                                            <i class="ti ti-package text-4xl"></i>
                                        </div>
                                        <p class="text-sm text-gray-500">{{ __('No product data available') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Price Trends -->
                @if(!empty($priceTrends))
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Price Trends Analysis') }}</h3>
                        </div>
                        <div class="overflow-hidden">
                            @if(!empty($priceTrends['trends']))
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Product') }}</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Current Price') }}</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Previous Price') }}</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Change') }}</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Trend') }}</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Last Updated') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($priceTrends['trends'] as $trend)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">{{ $trend['product_name'] }}</div>
                                                        <div class="text-sm text-gray-500">{{ $trend['product_code'] }}</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ format_currency($trend['current_price']) }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ format_currency($trend['previous_price'] ?? 0) }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        @php
                                                            $change = $trend['price_change_percentage'] ?? 0;
                                                            $changeClass = $change > 0 ? 'text-green-600' : ($change < 0 ? 'text-red-600' : 'text-gray-500');
                                                            $changeIcon = $change > 0 ? 'ti-trending-up' : ($change < 0 ? 'ti-trending-down' : 'ti-minus');
                                                        @endphp
                                                        <span class="inline-flex items-center text-sm {{ $changeClass }}">
                                                            <i class="ti {{ $changeIcon }} mr-1"></i>
                                                            {{ number_format(abs($change), 2) }}%
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $trend['trend'] === 'increasing' ? 'bg-green-100 text-green-800' : ($trend['trend'] === 'decreasing' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                                            {{ ucfirst($trend['trend'] ?? 'stable') }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $trend['last_updated'] ? \Carbon\Carbon::parse($trend['last_updated'])->format('M d, Y') : '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-12">
                                    <div class="mx-auto h-24 w-24 text-gray-400 mb-4">
                                        <i class="ti ti-trending-up text-6xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No price trend data available') }}</h3>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
           
        </div>
    </div>

</div>
