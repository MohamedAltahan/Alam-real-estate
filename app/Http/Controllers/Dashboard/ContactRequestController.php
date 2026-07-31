<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\RequestType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = ContactRequest::query()
            ->with(['requestType', 'property'])
            ->when($request->type_id, fn ($q, $v) => $q->where('request_type_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.requests.index', [
            'requests' => $requests,
            'types' => RequestType::where('is_active', true)->get(),
            'filters' => $request->only('type_id', 'status'),
            'unreadCount' => ContactRequest::where('is_read', false)->count(),
        ]);
    }

    public function markContacted(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('contact_requests.edit'), 403);
        $contactRequest->update(['status' => 'contacted', 'is_read' => true, 'handled_by' => $request->user()->id]);

        return back()->with('success', 'تم تعليم الطلب كمتواصل معه.');
    }

    public function markRead(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('contact_requests.view'), 403);
        $contactRequest->update(['is_read' => true]);

        return back();
    }

    public function destroy(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->can('contact_requests.delete'), 403);
        $contactRequest->delete();

        return back()->with('success', 'تم حذف الطلب.');
    }
}
