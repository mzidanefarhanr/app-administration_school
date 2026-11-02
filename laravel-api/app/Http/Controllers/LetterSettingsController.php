<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLetterHeaderRequest;
use App\Http\Requests\UpdateLetterSignatoryRequest;
use App\Http\Resources\StatusResource;
use App\Models\LetterSettings;
use App\Services\Letters\LetterSettingsAssetService;
use App\Services\Letters\LetterSettingsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LetterSettingsController extends Controller
{
    public function __construct(
        private readonly LetterSettingsService $settings,
        private readonly LetterSettingsAssetService $assets,
    ) {}

    public function show(Request $request): StatusResource
    {
        $this->authorize('letters.view');

        return new StatusResource(
            true,
            'Letter settings retrieved successfully.',
            $this->settings->show($request->user()),
        );
    }

    public function updateHeader(UpdateLetterHeaderRequest $request): StatusResource
    {
        $this->authorize('letters.manage');

        $validated = $request->validated();

        $data = $this->settings->updateHeader(
            $validated,
            (int) ($validated['expected_version'] ?? 0),
            $request->user(),
        );

        return new StatusResource(true, 'Letter header saved successfully.', $data);
    }

    public function updateSignatory(UpdateLetterSignatoryRequest $request): StatusResource
    {
        $this->authorize('letters.sign');

        $validated = $request->validated();

        $data = $this->settings->updateSignatory(
            $validated,
            (int) ($validated['expected_version'] ?? 0),
            $request->user(),
        );

        return new StatusResource(true, 'Letter signatory saved successfully.', $data);
    }

    public function asset(Request $request, string $asset): StreamedResponse
    {
        if (! in_array($asset, LetterSettings::ASSETS, true)) {
            abort(response()->json(['message' => 'Letter settings asset not found.'], 404));
        }

        $this->authorize($asset === 'logo' ? 'letters.view' : 'letters.sign');

        $settings = $this->settings->context()['settings'];

        if ($settings === null) {
            abort(response()->json(['message' => 'Letter settings asset not found.'], 404));
        }

        return $this->assets->stream($settings, $asset);
    }
}
