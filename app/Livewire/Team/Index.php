<?php

declare(strict_types=1);

namespace App\Livewire\Team;

use App\Models\User;
use App\Models\Warehouse;
use App\Support\Branches;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Super admin only (route group role:admin): who can sign in, their role, and which branch they run.
 */
#[Layout('layouts.app', ['plain' => true])]
#[Title('Team & access')]
class Index extends Component
{
    public const ROLES = ['admin', 'branch-admin', 'worker'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    public bool $panel = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $userRole = 'branch-admin';

    public ?int $branchId = null;

    public string $station = '';

    public string $password = '';

    public bool $active = true;

    public ?array $flash = null;

    /** Runs on every request, including Livewire actions that bypass route middleware. */
    public function boot(): void
    {
        abort_unless(Branches::isSuperAdmin(), 403);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->panel = true;
    }

    public function edit(string $id): void
    {
        $user = User::query()->with('roles', 'warehouses')->findOrFail($id);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->userRole = $user->hasRole('admin') ? 'admin' : ($user->hasRole('branch-admin') ? 'branch-admin' : ($user->hasRole('worker') ? 'worker' : 'branch-admin'));
        $this->branchId = $this->userRole !== 'admin' ? $user->warehouses->first()?->id : null;
        $this->station = (string) $user->station;
        $this->active = (int) ($user->getRawOriginal('status') ?? 1) === 1;
        $this->panel = true;
    }

    public function save(): void
    {
        $isSelf = $this->editingId === Auth::id();

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'userRole' => ['required', Rule::in(self::ROLES)],
            'branchId' => [Rule::requiredIf($this->userRole !== 'admin'), 'nullable', 'exists:warehouses,id'],
            'station' => [Rule::requiredIf($this->userRole === 'worker'), 'nullable', 'string', 'max:60'],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
        ], [
            'branchId.required' => __('Choose the branch this person works at.'),
            'station.required' => __('Enter the station they work at, e.g. Hair chair 2.'),
        ]);

        if ($isSelf && ($this->userRole !== 'admin' || ! $this->active)) {
            $this->addError('userRole', __('You cannot remove your own super admin access.'));

            return;
        }

        if ($this->editingId && ($this->userRole !== 'admin' || ! $this->active) && $this->isLastActiveSuperAdmin($this->editingId)) {
            $this->addError('userRole', __('There must always be at least one active super admin.'));

            return;
        }

        DB::transaction(function (): void {
            $user = $this->editingId ? User::query()->findOrFail($this->editingId) : new User;

            $user->fill([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone ?: null,
                'status' => $this->active ? 1 : 0,
                'is_all_warehouses' => $this->userRole === 'admin',
                'station' => $this->userRole === 'worker' ? trim($this->station) : null,
            ]);

            if ($this->password !== '') {
                $user->password = Hash::make($this->password);
            }

            $user->save();
            $user->syncRoles([$this->userRole]);

            // Super admins work across every location; a branch admin is tied to one branch.
            $user->warehouses()->sync($this->userRole === 'admin'
                ? Warehouse::query()->pluck('id')->all()
                : [$this->branchId]);

            if (! $this->active) {
                $this->signOutEverywhere($user->id);
            }
        });

        $this->flash = ['type' => 'success', 'message' => $this->editingId ? __(':name updated.', ['name' => $this->name]) : __(':name can now sign in.', ['name' => $this->name])];
        $this->panel = false;
        $this->resetForm();
    }

    public function toggleActive(string $id): void
    {
        $user = User::query()->findOrFail($id);
        $nowActive = (int) $user->getRawOriginal('status') !== 1;

        if (! $nowActive && ($user->id === Auth::id() || $this->isLastActiveSuperAdmin($user->id))) {
            $this->flash = ['type' => 'error', 'message' => __('You cannot deactivate yourself or the last active super admin.')];

            return;
        }

        $user->update(['status' => $nowActive ? 1 : 0]);

        if (! $nowActive) {
            $this->signOutEverywhere($user->id);
        }

        $this->flash = ['type' => 'success', 'message' => $nowActive ? __(':name can sign in again.', ['name' => $user->name]) : __(':name has been deactivated and signed out.', ['name' => $user->name])];
    }

    private function isLastActiveSuperAdmin(string $userId): bool
    {
        $admins = User::role('admin')->where('status', 1)->pluck('id');

        return $admins->count() === 1 && $admins->first() === $userId;
    }

    private function signOutEverywhere(string $userId): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $userId)->delete();
        }
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'phone', 'branchId', 'station', 'password');
        $this->userRole = 'branch-admin';
        $this->active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $users = User::query()
            ->with('roles:id,name', 'warehouses:id,name')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->role !== '', fn ($q) => $q->role($this->role))
            ->orderByDesc('status')
            ->orderBy('name')
            ->get();

        $all = User::query()->with('roles:id,name')->get(['id', 'status']);

        return view('livewire.team.index', [
            'users' => $users,
            'branches' => Warehouse::query()->orderBy('id')->get(['id', 'name']),
            'stations' => User::query()->whereNotNull('station')->distinct()->orderBy('station')->pluck('station'),
            'stats' => [
                'admins' => $all->filter(fn ($u) => $u->roles->contains('name', 'admin'))->count(),
                'branchAdmins' => $all->filter(fn ($u) => $u->roles->contains('name', 'branch-admin'))->count(),
                'workers' => $all->filter(fn ($u) => $u->roles->contains('name', 'worker'))->count(),
                'active' => $all->filter(fn ($u) => (int) $u->getRawOriginal('status') === 1)->count(),
                'inactive' => $all->filter(fn ($u) => (int) $u->getRawOriginal('status') !== 1)->count(),
            ],
        ]);
    }
}
