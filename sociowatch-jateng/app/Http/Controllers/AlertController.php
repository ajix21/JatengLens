<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    // GET /alerts
    public function index(Request $request)
    {
        $accounts = SocialAccount::orderBy('username')->get(['id', 'username', 'display_name']);

        $alerts = Alert::with('socialAccount:id,username,display_name,platform')
            ->when($request->account_id, fn ($q) => $q->where('social_account_id', $request->account_id))
            ->when($request->type,       fn ($q) => $q->where('alert_type', $request->type))
            ->when($request->date_from,  fn ($q) => $q->whereDate('triggered_at', '>=', $request->date_from))
            ->when($request->date_to,    fn ($q) => $q->whereDate('triggered_at', '<=', $request->date_to))
            ->when($request->unread,     fn ($q) => $q->where('is_read', false))
            ->orderByDesc('triggered_at')
            ->paginate(30)
            ->withQueryString();

        $unreadCount = Alert::where('is_read', false)->count();

        return view('alerts.index', compact('alerts', 'accounts', 'unreadCount'));
    }

    // POST /alerts/read-all
    public function readAll()
    {
        Alert::where('is_read', false)->update(['is_read' => true]);
        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    // POST /alerts/{alert}/read
    public function markRead(Alert $alert)
    {
        $alert->update(['is_read' => true]);
        return back();
    }

    // GET /alerts/settings
    public function settings()
    {
        $global   = AlertSetting::global();
        $accounts = SocialAccount::orderBy('username')
            ->with('alertSetting')
            ->get(['id', 'username', 'display_name', 'platform']);
        return view('alerts.settings', compact('global', 'accounts'));
    }

    // POST /alerts/settings
    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'spike_up_threshold'   => 'required|numeric|min:0.1|max:999',
            'spike_down_threshold' => 'required|numeric|min:0.1|max:999',
            'milestone_values'     => 'nullable|string',
            'is_active'            => 'boolean',
        ]);

        $milestones = null;
        if (!empty($data['milestone_values'])) {
            $milestones = array_values(array_filter(
                array_map('intval', explode(',', $data['milestone_values']))
            ));
            sort($milestones);
        }

        AlertSetting::updateOrCreate(
            ['social_account_id' => null],
            [
                'spike_up_threshold'   => $data['spike_up_threshold'],
                'spike_down_threshold' => $data['spike_down_threshold'],
                'milestone_values'     => $milestones,
                'is_active'            => $request->boolean('is_active', true),
            ]
        );

        return back()->with('success', 'Setting alert global berhasil disimpan.');
    }

    // POST /alerts/settings/account — save per-account override
    public function saveAccountSetting(Request $request, SocialAccount $account)
    {
        $this->authorize('can-edit');

        $data = $request->validate([
            'spike_up_threshold'   => 'required|numeric|min:0.1|max:999',
            'spike_down_threshold' => 'required|numeric|min:0.1|max:999',
            'is_active'            => 'boolean',
        ]);

        AlertSetting::updateOrCreate(
            ['social_account_id' => $account->id],
            array_merge($data, ['is_active' => $request->boolean('is_active', true)])
        );

        return back()->with('success', 'Setting alert untuk akun ini berhasil disimpan.');
    }

    // GET /api/alerts/unread — AJAX polling endpoint
    public function unread()
    {
        $unread = Alert::with('socialAccount:id,username,display_name,platform')
            ->where('is_read', false)
            ->orderByDesc('triggered_at')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'alert_type'   => $a->alert_type,
                'message'      => $a->message,
                'change_pct'   => (float) $a->change_percent,
                'triggered_at' => $a->triggered_at?->diffForHumans(),
                'account' => [
                    'id'           => $a->socialAccount?->id,
                    'username'     => $a->socialAccount?->username,
                    'platform'     => $a->socialAccount?->platform,
                    'display_name' => $a->socialAccount?->display_name,
                ],
            ]);

        return response()->json([
            'count'  => Alert::where('is_read', false)->count(),
            'alerts' => $unread,
        ]);
    }
}
