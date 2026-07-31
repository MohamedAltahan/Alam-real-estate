<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class SupervisorController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.supervisors.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('supervisors.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:40'],
            'civil_id' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'exists:roles,name'],
            'status' => ['required', 'in:active,suspended'],
        ]);

        $user = User::create([
            ...collect($data)->except('role')->all(),
            'is_agent' => $request->boolean('is_agent'),
        ]);
        $user->assignRole($data['role']);

        return back()->with('success', 'تم إضافة المشرف بنجاح.');
    }

    public function update(Request $request, User $supervisor): RedirectResponse
    {
        abort_unless($request->user()->can('supervisors.edit'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($supervisor->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:40'],
            'civil_id' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'exists:roles,name'],
            'status' => ['required', 'in:active,suspended'],
        ]);

        $supervisor->update([
            ...collect($data)->except('role', 'password')->all(),
            'is_agent' => $request->boolean('is_agent'),
            ...($data['password'] ? ['password' => $data['password']] : []),
        ]);
        $supervisor->syncRoles([$data['role']]);

        return back()->with('success', 'تم تحديث بيانات المشرف.');
    }

    public function destroy(Request $request, User $supervisor): RedirectResponse
    {
        abort_unless($request->user()->can('supervisors.delete'), 403);
        abort_if($supervisor->id === $request->user()->id, 403, 'لا يمكنك حذف حسابك.');

        $supervisor->delete();

        return back()->with('success', 'تم حذف المشرف.');
    }
}
