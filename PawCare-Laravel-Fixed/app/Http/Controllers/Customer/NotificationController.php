<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Menampilkan notifikasi milik Customer yang sedang login.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();

        return view('customer.notifications.index', compact(
            'notifications',
            'unreadCount'
        ));
    }

    /**
     * Tandai notifikasi sebagai dibaca, lalu buka halaman internal terkait.
     */
    public function open(
        Request $request,
        string $notification
    ): RedirectResponse {
        $item = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if (is_null($item->read_at)) {
            $item->markAsRead();
        }

        $safePath = $this->resolveSafeInternalTarget(
            $request,
            data_get($item->data, 'url')
        );

        if ($safePath !== null) {
            return redirect()->to($safePath);
        }

        return redirect()->route('customer.notifications.index');
    }

    /**
     * Menandai seluruh notifikasi yang belum dibaca.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with(
            'success',
            'Semua notifikasi telah ditandai sebagai dibaca.'
        );
    }

    /**
     * Validasi URL tujuan agar hanya mengizinkan path internal aplikasi PawCare
     * dan menolak open redirect ke domain eksternal.
     */
    private function resolveSafeInternalTarget(
        Request $request,
        mixed $rawUrl
    ): ?string {
        if (! is_string($rawUrl)) {
            return null;
        }

        $rawUrl = trim($rawUrl);

        if (
            $rawUrl === ''
            || str_contains($rawUrl, "\0")
            || str_contains($rawUrl, '\\')
            || str_contains($rawUrl, "\r")
            || str_contains($rawUrl, "\n")
        ) {
            return null;
        }

        // Tolak protocol-relative URL seperti //evil.com
        if (str_starts_with($rawUrl, '//')) {
            return null;
        }

        $parsed = parse_url($rawUrl);
        if ($parsed === false) {
            return null;
        }

        // Tolak kredensial di dalam URL seperti http://localhost@evil.com
        if (! empty($parsed['user']) || ! empty($parsed['pass'])) {
            return null;
        }

        // Jika memiliki scheme/host, pastikan benar-benar host aplikasi sendiri.
        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            $targetHost = strtolower((string) ($parsed['host'] ?? ''));
            $allowedHosts = array_filter([
                strtolower((string) $request->getHost()),
                strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
                strtolower((string) parse_url(url('/'), PHP_URL_HOST)),
            ]);

            if ($targetHost === '' || ! in_array($targetHost, $allowedHosts, true)) {
                return null;
            }
        }

        $path = (string) ($parsed['path'] ?? '/');
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $query = isset($parsed['query']) && $parsed['query'] !== ''
            ? '?'.$parsed['query']
            : '';

        return url($path).$query;
    }
}