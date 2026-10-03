<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateUserDataExport;
use App\Models\User;
use App\Services\Privacy\DataExportStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Nustatymai → Privatumas (BDAR, Etapas 8): duomenų archyvo užsakymas ir atsisiuntimas, paskyros ištrynimas.
 * Pats ištrynimas – ProfileController::destroy (starter kit maršrutas) → AnonymizeUser.
 */
class PrivacyController extends Controller
{
    public function __construct(private readonly DataExportStorage $exports) {}

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Privacy', [
            'exports' => array_map(fn (array $export): array => [
                'file' => $export['file'],
                'size' => $export['size'],
                'created_at' => $export['created_at']->toIso8601String(),
                'expires_at' => $export['expires_at']->toIso8601String(),
                'download_url' => route('privacy.download', ['file' => $export['file']]),
            ], $this->exports->list($user)),
            'retentionDays' => DataExportStorage::RETENTION_DAYS,
            'canDelete' => ! $user->isAdmin(),
        ]);
    }

    /**
     * Archyvas ruošiamas eilėje (GenerateUserDataExport); kai bus paruoštas – laiškas ir pranešimas varpelyje.
     */
    public function export(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        GenerateUserDataExport::dispatch($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('privacy.export.queued')]);

        return to_route('privacy.edit');
    }

    /**
     * Kelias sudaromas iš prisijungusio vartotojo ID – kito žmogaus archyvo pasiekti neįmanoma.
     */
    public function download(Request $request, string $file): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $path = $this->exports->path($user, $file);

        abort_unless($this->exports->disk()->exists($path), 404);

        return $this->exports->disk()->download($path, 'duomenys-'.$file);
    }
}
