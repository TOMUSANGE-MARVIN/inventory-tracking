@php
    $i = fn (string $icon) => "<i class='fas fa-{$icon}'></i>";
@endphp

<x-perfect-scrollbar as="nav" aria-label="main" wire:navigate:scroll class="flex flex-col flex-1 gap-0.5 px-3 pb-4">

    <x-sidebar.link title="{{ __('Dashboard') }}" href="{{ route('dashboard') }}" :isActive="request()->routeIs('dashboard', 'tracking.overview')" :icon="$i('th-large')" />

    @can('transfer_access')
        <x-sidebar.section :title="__('Stock tracking')" />
        <x-sidebar.link title="{{ __('Receive new stock') }}" href="{{ route('tracking.arrivals') }}" :isActive="request()->routeIs('tracking.arrivals')" :icon="$i('box-open')" />
        <x-sidebar.link title="{{ __('Transfers') }}" href="{{ route('tracking.transfers') }}" :isActive="request()->routeIs('tracking.transfers', 'tracking.dispatch', 'tracking.receive')" :icon="$i('truck')" />
        <x-sidebar.link title="{{ __('Scan station') }}" href="{{ route('tracking.station') }}" :isActive="request()->routeIs('tracking.station')" :icon="$i('qrcode')" />
        <x-sidebar.link title="{{ __('Where is this item?') }}" href="{{ route('tracking.lookup') }}" :isActive="request()->routeIs('tracking.lookup')" :icon="$i('map-marker-alt')" />
        <x-sidebar.link title="{{ __('Refills') }}" href="{{ route('tracking.refills') }}" :isActive="request()->routeIs('tracking.refills')" :icon="$i('redo-alt')" />
        <x-sidebar.link title="{{ __('Movement history') }}" href="{{ route('tracking.history') }}" :isActive="request()->routeIs('tracking.history')" :icon="$i('history')" />
        <x-sidebar.link title="{{ __('Reports') }}" href="{{ route('tracking.reports') }}" :isActive="request()->routeIs('tracking.reports')" :icon="$i('chart-line')" />
        <x-sidebar.link title="{{ __('Print labels') }}" href="{{ route('tracking.labels') }}" :isActive="request()->routeIs('tracking.labels')" :icon="$i('tags')" />
    @endcan

    @can('product_access')
        <x-sidebar.section :title="__('Inventory')" />
        <x-sidebar.dropdown title="{{ __('Products') }}" :active="request()->routeIs(['products.*', 'product-categories.index', 'brands.index', 'adjustments.index'])" :icon="$i('boxes')">
            <x-sidebar.sublink title="{{ __('All products') }}" href="{{ route('products.index') }}" :active="request()->routeIs('products.index')" />
            @can('category_access')
                <x-sidebar.sublink title="{{ __('Categories') }}" href="{{ route('product-categories.index') }}" :active="request()->routeIs('product-categories.index')" />
            @endcan
            @can('brand_access')
                <x-sidebar.sublink title="{{ __('Brands') }}" href="{{ route('brands.index') }}" :active="request()->routeIs('brands.index')" />
            @endcan
            @can('print_barcodes')
                <x-sidebar.sublink title="{{ __('Product barcodes') }}" href="{{ route('products.barcode-print') }}" :active="request()->routeIs('products.barcode-print')" />
            @endcan
            @can('adjustment_access')
                <x-sidebar.sublink title="{{ __('Stock adjustments') }}" href="{{ route('adjustments.index') }}" :active="request()->routeIs('adjustments.index')" />
            @endcan
        </x-sidebar.dropdown>
        @can('warehouse_access')
            <x-sidebar.link title="{{ __('Locations') }}" href="{{ route('warehouses.index') }}" :isActive="request()->routeIs('warehouses.index')" :icon="$i('warehouse')" />
        @endcan
    @endcan

    @canany(['purchase_access', 'sale_access', 'quotation_access', 'expense_access', 'report_access'])
        <x-sidebar.section :title="__('Business')" />
    @endcanany

    @can('purchase_access')
        <x-sidebar.dropdown title="{{ __('Purchases') }}" :active="request()->routeIs(['purchases.*', 'purchase-returns.*'])" :icon="$i('shopping-cart')">
            <x-sidebar.sublink title="{{ __('All purchases') }}" href="{{ route('purchases.index') }}" :active="request()->routeIs('purchases.index')" />
            @can('purchase_return_access')
                <x-sidebar.sublink title="{{ __('Purchase returns') }}" href="{{ route('purchase-returns.index') }}" :active="request()->routeIs('purchase-returns.index')" />
            @endcan
        </x-sidebar.dropdown>
    @endcan

    @can('sale_access')
        <x-sidebar.dropdown title="{{ __('Sales') }}" :active="request()->routeIs(['sales.*', 'sale-returns.*', 'business.dashboard'])" :icon="$i('cash-register')">
            <x-sidebar.sublink title="{{ __('Sales dashboard') }}" href="{{ route('business.dashboard') }}" :active="request()->routeIs('business.dashboard')" />
            <x-sidebar.sublink title="{{ __('All sales') }}" href="{{ route('sales.index') }}" :active="request()->routeIs('sales.index')" />
            @can('sale_return_access')
                <x-sidebar.sublink title="{{ __('Sale returns') }}" href="{{ route('sale-returns.index') }}" :active="request()->routeIs('sale-returns.index')" />
            @endcan
            <x-sidebar.sublink title="{{ __('Point of sale') }}" href="{{ route('pos.index') }}" :active="request()->routeIs('pos.index')" />
        </x-sidebar.dropdown>
    @endcan

    @can('quotation_access')
        <x-sidebar.link title="{{ __('Quotations') }}" href="{{ route('quotations.index') }}" :isActive="request()->routeIs('quotations.index')" :icon="$i('file-invoice')" />
    @endcan

    @can('expense_access')
        <x-sidebar.dropdown title="{{ __('Expenses') }}" :active="request()->routeIs(['expenses.index', 'expense-categories.index'])" :icon="$i('wallet')">
            <x-sidebar.sublink title="{{ __('All expenses') }}" href="{{ route('expenses.index') }}" :active="request()->routeIs('expenses.index')" />
            @can('expense_categories_access')
                <x-sidebar.sublink title="{{ __('Categories') }}" href="{{ route('expense-categories.index') }}" :active="request()->routeIs('expense-categories.index')" />
            @endcan
        </x-sidebar.dropdown>
    @endcan

    @can('report_access')
        <x-sidebar.dropdown title="{{ __('Reports') }}" :active="request()->routeIs([
                'purchases-report.index', 'sales-report.index', 'sales-return-report.index', 'payments-report.index',
                'purchases-return-report.index', 'profit-loss-report.index', 'stock-alert-report.index',
                'customers-report.index', 'suppliers-report.index', 'warehouses-report.index',
            ])" :icon="$i('chart-bar')">
            <x-sidebar.sublink title="{{ __('Stock alerts') }}" href="{{ route('stock-alert-report.index') }}" :active="request()->routeIs('stock-alert-report.index')" />
            <x-sidebar.sublink title="{{ __('Locations') }}" href="{{ route('warehouses-report.index') }}" :active="request()->routeIs('warehouses-report.index')" />
            <x-sidebar.sublink title="{{ __('Purchases') }}" href="{{ route('purchases-report.index') }}" :active="request()->routeIs('purchases-report.index')" />
            <x-sidebar.sublink title="{{ __('Purchase returns') }}" href="{{ route('purchases-return-report.index') }}" :active="request()->routeIs('purchases-return-report.index')" />
            <x-sidebar.sublink title="{{ __('Suppliers') }}" href="{{ route('suppliers-report.index') }}" :active="request()->routeIs('suppliers-report.index')" />
            <x-sidebar.sublink title="{{ __('Sales') }}" href="{{ route('sales-report.index') }}" :active="request()->routeIs('sales-report.index')" />
            <x-sidebar.sublink title="{{ __('Sale returns') }}" href="{{ route('sales-return-report.index') }}" :active="request()->routeIs('sales-return-report.index')" />
            <x-sidebar.sublink title="{{ __('Customers') }}" href="{{ route('customers-report.index') }}" :active="request()->routeIs('customers-report.index')" />
            <x-sidebar.sublink title="{{ __('Payments') }}" href="{{ route('payments-report.index') }}" :active="request()->routeIs('payments-report.index')" />
            <x-sidebar.sublink title="{{ __('Profit & loss') }}" href="{{ route('profit-loss-report.index') }}" :active="request()->routeIs('profit-loss-report.index')" />
        </x-sidebar.dropdown>

        <x-sidebar.dropdown title="{{ __('Analytics') }}" :active="request()->routeIs(['analytics.*', 'finance.*'])" :icon="$i('chart-pie')">
            <x-sidebar.sublink title="{{ __('Analytics dashboard') }}" href="{{ route('analytics.dashboard') }}" :active="request()->routeIs('analytics.dashboard')" />
            <x-sidebar.sublink title="{{ __('Product analytics') }}" href="{{ route('analytics.product') }}" :active="request()->routeIs('analytics.product')" />
            <x-sidebar.sublink title="{{ __('Item analytics') }}" href="{{ route('tracking.lookup') }}" :active="false" />
            <x-sidebar.sublink title="{{ __('Revenue') }}" href="{{ route('analytics.revenue') }}" :active="request()->routeIs('analytics.revenue')" />
            <x-sidebar.sublink title="{{ __('Financial dashboard') }}" href="{{ route('finance.dashboard') }}" :active="request()->routeIs('finance.dashboard')" />
            <x-sidebar.sublink title="{{ __('KPI tracking') }}" href="{{ route('finance.kpi') }}" :active="request()->routeIs('finance.kpi')" />
            <x-sidebar.sublink title="{{ __('Break-even') }}" href="{{ route('finance.breakeven') }}" :active="request()->routeIs('finance.breakeven')" />
        </x-sidebar.dropdown>
    @endcan

    @canany(['user_access', 'setting_access'])
        <x-sidebar.section :title="__('Administration')" />
    @endcanany

    @can('user_access')
        <x-sidebar.dropdown title="{{ __('People') }}" :active="request()->routeIs(['team.index', 'customers.*', 'customer-group.*', 'suppliers.*', 'users.*', 'roles.*', 'permissions.*'])" :icon="$i('users')">
            <x-sidebar.sublink title="{{ __('Team & access') }}" href="{{ route('team.index') }}" :active="request()->routeIs('team.index')" />
            @can('suppliers_access')
                <x-sidebar.sublink title="{{ __('Suppliers') }}" href="{{ route('suppliers.index') }}" :active="request()->routeIs('suppliers.index')" />
            @endcan
            @can('customer_access')
                <x-sidebar.sublink title="{{ __('Customers') }}" href="{{ route('customers.index') }}" :active="request()->routeIs('customers.index')" />
            @endcan
            @can('customer_group_access')
                <x-sidebar.sublink title="{{ __('Customer groups') }}" href="{{ route('customer-group.index') }}" :active="request()->routeIs('customer-group.index')" />
            @endcan
            @can('access_roles')
                <x-sidebar.sublink title="{{ __('Roles') }}" href="{{ route('roles.index') }}" :active="request()->routeIs('roles.index')" />
            @endcan
            @can('access_permissions')
                <x-sidebar.sublink title="{{ __('Permissions') }}" href="{{ route('permissions.index') }}" :active="request()->routeIs('permissions.index')" />
            @endcan
        </x-sidebar.dropdown>
    @endcan

    @can('setting_access')
        <x-sidebar.dropdown title="{{ __('Settings') }}" :active="request()->routeIs(['settings.*', 'currencies.*', 'notifications.manager'])" :icon="$i('cog')">
            <x-sidebar.sublink title="{{ __('General') }}" href="{{ route('settings.index') }}" :active="request()->routeIs('settings.index')" />
            <x-sidebar.sublink title="{{ __('Currencies') }}" href="{{ route('currencies.index') }}" :active="request()->routeIs('currencies.index')" />
            <x-sidebar.sublink title="{{ __('Notifications') }}" href="{{ route('notifications.manager') }}" :active="request()->routeIs('notifications.manager')" />
        </x-sidebar.dropdown>
    @endcan
</x-perfect-scrollbar>
