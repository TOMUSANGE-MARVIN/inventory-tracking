<?php

declare(strict_types=1);

use App\Livewire\Actions\Logout;
use Livewire\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }
};
?>

<button type="button" wire:click="logout" class="btn-secondary py-1.5 text-sm"><i class="fas fa-sign-out-alt"></i>{{ __('Sign out') }}</button>
