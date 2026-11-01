<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PetHealthRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PetHealthAttachmentController extends Controller
{
    public function show(Request $request, PetHealthRecord $record): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $record->loadMissing('pet');
        $pet = $record->pet;
        if (! $pet) {
            abort(404);
        }

        $allowed = $user->isAdmin()
            || ($user->isCustomer() && (int) $record->customer_id === (int) $user->id)
            || (
                $user->isOwner()
                && $pet->share_health_with_business
                && Booking::forOwner($user)->where('pet_id', $pet->id)->exists()
            );

        if (! $allowed || ! filled($record->attachment_path)) {
            abort(404);
        }

        $path = ltrim(str_replace('\\', '/', trim((string) $record->attachment_path)), '/');
        if (
            $path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || ! str_starts_with($path, PetHealthRecord::ATTACHMENT_DIRECTORY.'/')
        ) {
            abort(404);
        }

        $disk = Storage::disk(PetHealthRecord::ATTACHMENT_DISK);
        if (! $disk->exists($path)) {
            abort(404);
        }

        $filePath = $disk->path($path);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = ($filePath && is_file($filePath)) ? ($finfo->file($filePath) ?: 'application/octet-stream') : 'application/octet-stream';

        if (! in_array($mimeType, PetHealthRecord::ATTACHMENT_MIMES, true)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = 'dokumen-kesehatan-'.$record->id.'.'.$ext;
        $contents = $disk->get($path);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
