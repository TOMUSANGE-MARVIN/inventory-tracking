<x-sidebar.overlay />

<aside
    class="fixed inset-y-0 z-30 flex flex-col bg-white dark:bg-gray-900 border-r border-gray-200/70 dark:border-gray-800"
    :class="{
        'translate-x-0 w-64': isSidebarOpen || isSidebarHovered,
        'w-16 -translate-x-full lg:translate-x-0': !isSidebarOpen && !isSidebarHovered,
    }"
    style="transition-property: width, transform; transition-duration: 200ms; transition-timing-function: ease-out;"
    @mouseenter="handleSidebarHover(true)"
    @mouseleave="handleSidebarHover(false)"
    x-cloak
>
    <x-sidebar.header />

    <x-sidebar.content />

    <x-sidebar.footer />
</aside>
