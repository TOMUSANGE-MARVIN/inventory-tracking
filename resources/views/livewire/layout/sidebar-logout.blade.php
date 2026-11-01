<?php

declare(strict_types=1);

use App\Livewire\Actions\Logout;
use Livewire\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
};
?>

<button type="button" wire:click="logout"
    class="w-full flex items-center gap-3 py-2 px-3 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition">
    <span class="shrink-0 w-5 text-center"><i class="fas fa-sign-out-alt"></i></span>
    <span class="whitespace-nowrap" x-show="isSidebarOpen || isSidebarHovered">{{ __('Log out') }}</span>
</button>
