<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bukti pembayaran bersifat privat dan hanya bisa dilihat oleh:
 * - Customer pemilik booking
 * - Owner bisnis terkait
 * - Admin
 */
class PaymentProofController extends Controller
{
    private const PROOF_DIRECTORY = 'payment-proofs';

    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'pdf',
    ];

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public function show(
        Request $request,
        Payment $payment
    ): Response {
        $user = $request->user();

        // Pastikan pengguna yang login adalah User PawCare.
        if (! $user instanceof User) {
            abort(401);
        }

        $payment->loadMissing('booking.business');

        $booking = $payment->booking;

        if (! $booking || ! $booking->business) {
            abort(404);
        }

        // Validasi hak akses sebelum file diberikan.
        $allowed = $user->isAdmin()
            || (
                $user->isCustomer()
                && (int) $booking->customer_id === (int) $user->id
            )
            || (
                $user->isOwner()
                && (int) $booking->business->owner_id === (int) $user->id
            );

        if (! $allowed || ! filled($payment->proof_path)) {
            abort(404);
        }

        // Normalisasi path dan tolak directory traversal / file di luar folder payment-proofs/.
        $proofPath = ltrim(str_replace('\\', '/', trim((string) $payment->proof_path)), '/');

        if (
            $proofPath === ''
            || str_contains($proofPath, '..')
            || str_contains($proofPath, "\0")
            || ! str_starts_with($proofPath, self::PROOF_DIRECTORY . '/')
            || substr_count($proofPath, '/') !== 1
        ) {
            abort(404);
        }

        $extension = strtolower(
            pathinfo($proofPath, PATHINFO_EXTENSION)
        );

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            abort(404);
        }

        // Bukti pembayaran harus ada di disk privat.
        $disk = Storage::disk('local');

        if (! $disk->exists($proofPath)) {
            abort(404);
        }

        $filePath = $disk->path($proofPath);
        $realFilePath = realpath($filePath);
        $realProofDir = realpath($disk->path(self::PROOF_DIRECTORY));

        if (
            $realFilePath === false
            || $realProofDir === false
            || ! str_starts_with(
                $realFilePath,
                rtrim($realProofDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            )
            || ! is_file($realFilePath)
            || ! is_readable($realFilePath)
        ) {
            abort(404);
        }

        // Deteksi MIME langsung dari file yang sebenarnya.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->file($realFilePath)
            ?: 'application/octet-stream';

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            abort(404);
        }

        $filename = 'bukti-pembayaran-' . $payment->id . '.' . $extension;

        // Bersihkan byte liar pada output buffer aktif sebelum mengirim binary response.
        if (ob_get_level() > 0 && ob_get_length() > 0) {
            ob_clean();
        }

        // Baca isi file dari penyimpanan privat.
        $contents = $disk->get($proofPath);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}