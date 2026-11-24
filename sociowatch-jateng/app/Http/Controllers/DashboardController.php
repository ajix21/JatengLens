<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Services\NarrativeBriefingService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalAccounts    = SocialAccount::count();
        $totalFollowers   = SocialAccount::sum('followers_count');
        $regionsCovered   = SocialAccount::distinct('region_id')->count('region_id');
        $activeCategories = Category::whereHas('socialAccounts')->count();

        $accountsPerCategory = Category::withCount('socialAccounts')
            ->orderByDesc('social_accounts_count')
            ->get();

        $topRegions = Region::withCount('socialAccounts')
            ->orderByDesc('social_accounts_count')
            ->take(10)
            ->get();

        $mapAccounts = SocialAccount::with([
            'region:id,name,latitude,longitude',
            'category:id,name,color',
        ])->where('is_active', true)->get([
            'id', 'username', 'display_name', 'platform',
            'followers_count', 'category_id', 'region_id',
        ]);

        $latestAccounts = SocialAccount::with([
            'category:id,name,color',
            'region:id,name',
        ])->latest()->take(5)->get();

        $latestBriefing = NarrativeBriefing::latest('id')->first();
        if (!$latestBriefing && Post::exists()) {
            $latestBriefing = app(NarrativeBriefingService::class)->generate(24, true);
        }

        $igTokenAlert = $this->buildInstagramTokenAlert();

        return view('dashboard.index', compact(
            'totalAccounts', 'totalFollowers', 'regionsCovered', 'activeCategories',
            'accountsPerCategory', 'topRegions', 'mapAccounts', 'latestAccounts',
            'latestBriefing', 'igTokenAlert'
        ));
    }

    private function buildInstagramTokenAlert(): ?array
    {
        if (! filled(config('services.instagram.token'))) {
            return null;
        }

        $expiresRaw = trim((string) config('services.instagram.token_expires_at', ''));
        if ($expiresRaw === '') {
            return null;
        }

        try {
            $expiresAt = \Illuminate\Support\Carbon::parse($expiresRaw)->endOfDay();
        } catch (\Throwable) {
            return null;
        }

        $daysLeft = (int) floor(now()->diffInDays($expiresAt, false));

        if ($daysLeft < 0) {
            $daysAgo = abs($daysLeft);
            return [
                'level' => 'danger',
                'title' => 'Token Meta Graph API Instagram Sudah Kedaluwarsa',
                'message' => "Token Instagram telah habis masa berlakunya {$daysAgo} hari lalu ({$expiresAt->format('d M Y')}). Segera perbarui long-lived token di .env agar auto-fetch Instagram kembali aktif.",
            ];
        }

        if ($daysLeft <= 7) {
            return [
                'level' => 'warning',
                'title' => "Token Meta Graph API Instagram Akan Habis dalam {$daysLeft} Hari",
                'message' => "Masa berlaku token Instagram berakhir pada {$expiresAt->format('d M Y')}. Segera lakukan perpanjangan token 60 hari di Meta Developer Portal sebelum auto-fetch terhenti.",
            ];
        }

        return null;
    }

    public function generateBriefing(Request $request, NarrativeBriefingService $service)
    {
        $briefing = $service->generate(24, false, auth()->id());

        if ($briefing->status === 'fallback') {
            return redirect()->route('dashboard')
                ->with('warning', 'Ringkasan diperbarui menggunakan mode analitik otomatis (fallback API: ' . $briefing->error_message . ')');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Ringkasan Strategis & Klaster Isu berhasil diperbarui.');
    }
}
