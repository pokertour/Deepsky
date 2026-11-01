<?php

namespace App\Http\Requests\Admin;

use App\Enums\ConversionStatus;
use App\Models\EquipmentSetup;
use App\Models\ImagingSession;
use App\Models\ObservingLocation;
use App\Support\AcquisitionData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAstrophotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return $this->rulesWithImage(['required']);
    }

    /**
     * L'heure programmée est saisie dans le fuseau de l'admin et stockée en UTC ;
     * une publication immédiate annule toute programmation.
     */
    public function scheduledPublication(): ?Carbon
    {
        if ($this->boolean('is_published') || blank($this->validated('publish_at'))) {
            return null;
        }

        return Carbon::parse((string) $this->validated('publish_at'), config('admin.timezone'))->utc();
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty()) {
                foreach ($this->input('acquisition_sessions', []) ?? [] as $sessionIndex => $session) {
                    foreach ($session['frames'] as $frameIndex => $frame) {
                        if ($frame['type'] === 'light' && (float) ($frame['seconds'] ?? 0) <= 0) {
                            $validator->errors()->add("acquisition_sessions.$sessionIndex.frames.$frameIndex.seconds", __('La durée de chaque light doit être supérieure à zéro.'));
                        }
                    }
                }
            }

            if (! $this->boolean('is_published')) {
                return;
            }

            $photo = $this->route('astrophoto');
            if (! $photo || $this->hasFile('image') || $this->hasFile('starless_image') || (! $photo->is_published && $photo->conversion_status !== ConversionStatus::Ready)) {
                $validator->errors()->add('is_published', __('Enregistre en brouillon, puis publie lorsque les images sont prêtes.'));
            }
        }];
    }

    /** @param array<int, string> $imagePresence */
    protected function rulesWithImage(array $imagePresence): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
            'publish_at' => ['nullable', 'date', function (string $attribute, mixed $value, Closure $fail): void {
                if (! rescue(fn (): bool => Carbon::parse((string) $value, config('admin.timezone'))->isFuture(), false, report: false)) {
                    $fail(__('L’heure de publication doit être dans le futur.'));
                }
            }],
            'target' => ['nullable', 'string', 'max:255'],
            'celestial_object_id' => ['nullable', 'exists:celestial_objects,id'],
            'captured_at' => ['required', 'date'],
            'image' => [...$imagePresence, 'file', 'mimes:jpg,jpeg,png,webp,tif,tiff', 'max:307200'],
            'starless_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,tif,tiff', 'max:307200'],
            'remove_starless' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'in:galaxy,nebula,cluster,supernova,planets,widefield'],
            'exposure' => ['nullable', 'string', 'max:255'],
            'acquisition_sessions' => ['nullable', 'array', 'max:100'],
            'acquisition_sessions.*' => ['required', 'array:date,camera_mode,frames'],
            'acquisition_sessions.*.date' => ['nullable', 'date_format:Y-m-d'],
            'acquisition_sessions.*.camera_mode' => ['required', Rule::in(array_keys(AcquisitionData::CAMERA_MODES))],
            'acquisition_sessions.*.frames' => ['required', 'array', 'min:1', 'max:100'],
            'acquisition_sessions.*.frames.*' => ['required', 'array:type,filter,count,seconds'],
            'acquisition_sessions.*.frames.*.type' => ['required', Rule::in(array_keys(AcquisitionData::FRAME_TYPES))],
            'acquisition_sessions.*.frames.*.filter' => ['nullable', 'string', 'max:100'],
            'acquisition_sessions.*.frames.*.count' => ['required', 'integer', 'min:1', 'max:100000'],
            'acquisition_sessions.*.frames.*.seconds' => ['nullable', 'numeric', 'min:0', 'max:86400', 'decimal:0,3'],
            'gain' => ['nullable', 'integer', 'min:0'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sensor_temp' => ['nullable', 'string', 'max:50'],
            'focal_length' => ['nullable', 'integer', 'min:0'],
            'filters' => ['nullable', 'string', 'max:255'],
            'equipment_setup_id' => ['nullable', Rule::exists((new EquipmentSetup)->getTable(), 'id')],
            'imaging_session_id' => ['nullable', Rule::exists((new ImagingSession)->getTable(), 'id')],
            'observing_location_id' => ['nullable', Rule::exists((new ObservingLocation)->getTable(), 'id')],
            'description' => ['nullable', 'string'],
            'object_description' => ['nullable', 'string', 'max:10000'],
            'description_en' => ['nullable', 'string'],
            'object_description_en' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
