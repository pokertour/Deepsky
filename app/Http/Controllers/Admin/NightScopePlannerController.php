<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Planning\AddImagingPlanTargetAction;
use App\Actions\Planning\AddPlannerDraftTargetAction;
use App\Actions\Planning\DuplicateImagingPlanAction;
use App\Actions\Planning\SaveImagingPlanAction;
use App\Actions\Planning\UpdateImagingPlanAction;
use App\Actions\Planning\UpdateImagingPlanTargetAction;
use App\Enums\ImagingPlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddImagingPlanTargetRequest;
use App\Http\Requests\Admin\DuplicateImagingPlanRequest;
use App\Http\Requests\Admin\NightScopeDateRequest;
use App\Http\Requests\Admin\PlannerDraftExcludeRequest;
use App\Http\Requests\Admin\PlannerDraftTargetRequest;
use App\Http\Requests\Admin\SaveImagingPlanRequest;
use App\Http\Requests\Admin\UpdateImagingPlanRequest;
use App\Http\Requests\Admin\UpdateImagingPlanTargetRequest;
use App\Http\Requests\Admin\UpdateImagingSessionRequest;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ImagingPlan;
use App\Models\ImagingPlanTarget;
use App\Models\ImagingSession;
use App\Models\ObservingLocation;
use App\Services\Astronomy\LunarCalculator;
use App\Services\Astronomy\SolarCalculator;
use App\Services\Equipment\EquipmentCalculator;
use App\Services\Planning\AcquisitionSuggestionCalculator;
use App\Services\Planning\EquipmentScoreCalculator;
use App\Services\Planning\NightPlanner;
use App\Services\Planning\PlannerDraft;
use App\Services\Weather\WeatherManager;
use App\Services\Weather\WeatherScoreCalculator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NightScopePlannerController extends Controller
{
    public function index(NightScopeDateRequest $request, NightPlanner $planner, PlannerDraft $draft): View
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today');
        $plans = ImagingPlan::query()
            ->with(['observingLocation', 'equipmentSetup'])
            ->withCount('targets')
            ->latest('date')
            ->paginate(8);

        return view('admin.nightscope.planner.index', [
            'planner' => $draft->apply($planner->generate($date)),
            'plans' => $plans,
        ]);
    }

    public function store(
        SaveImagingPlanRequest $request,
        NightPlanner $planner,
        SaveImagingPlanAction $save,
        PlannerDraft $draft,
    ): RedirectResponse {
        $date = CarbonImmutable::parse($request->validated('date'));
        $data = $draft->apply($planner->generate($date));

        if ($data->slots === []) {
            return back()->with('error', __('Aucun créneau exploitable ne peut être sauvegardé pour cette nuit.'));
        }

        $plan = $save->execute($data, $request->validated('notes'));
        $draft->clear($date);

        return redirect()->route('admin.nightscope.plans.show', $plan)->with('success', __('Plan de nuit sauvegardé.'));
    }

    public function excludeDraftTarget(
        PlannerDraftExcludeRequest $request,
        PlannerDraft $draft,
    ): RedirectResponse {
        $date = CarbonImmutable::parse($request->validated('date'));
        $draft->exclude($date, (int) $request->validated('celestial_object_id'));

        return redirect()
            ->route('admin.nightscope.planner', ['date' => $date->format('Y-m-d')])
            ->with('success', __('Cible retirée de la proposition.'));
    }

    public function addDraftTarget(
        PlannerDraftTargetRequest $request,
        AddPlannerDraftTargetAction $add,
    ): RedirectResponse {
        $date = CarbonImmutable::parse($request->validated('date'));

        try {
            $add->execute(
                $date,
                CelestialObject::query()->findOrFail($request->validated('celestial_object_id')),
                $request->validated('planned_start'),
                $request->validated('planned_end'),
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('admin.nightscope.planner', ['date' => $date->format('Y-m-d')])
            ->with('success', __('Cible ajoutée à la proposition.'));
    }

    public function resetDraft(NightScopeDateRequest $request, PlannerDraft $draft): RedirectResponse
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today');
        $draft->clear($date);

        return redirect()
            ->route('admin.nightscope.planner', ['date' => $date->format('Y-m-d')])
            ->with('success', __('Proposition réinitialisée.'));
    }

    public function show(
        ImagingPlan $imagingPlan,
        AcquisitionSuggestionCalculator $acquisition,
        EquipmentScoreCalculator $equipmentScore,
        EquipmentCalculator $equipment,
        SolarCalculator $solar,
        LunarCalculator $lunar,
    ): View {
        $imagingPlan->load([
            'targets.celestialObject',
            'observingLocation',
            'equipmentSetup.enabledSetupItems',
            'imagingSession.astrophotos',
        ]);

        $optics = null;
        $filterTypes = [];
        $moonIllumination = 0.0;
        $at = null;

        if ($imagingPlan->equipmentSetup) {
            $filterTypes = $equipmentScore->filterTypesFor($imagingPlan->equipmentSetup);

            try {
                $optics = $equipment->calculate($imagingPlan->equipmentSetup);
            } catch (DomainException) {
                $optics = null;
            }
        }

        if ($imagingPlan->observingLocation) {
            $date = CarbonImmutable::parse($imagingPlan->date->format('Y-m-d'), $imagingPlan->observingLocation->timezone);
            $night = $solar->forNight($date, $imagingPlan->observingLocation);
            $at = $night->hasAstronomicalNight()
                ? $night->astronomicalNightStart->addSeconds((int) ($night->astronomicalNightStart->diffInSeconds($night->astronomicalNightEnd) / 2))
                : $date->setTime(23, 0);
            $moonIllumination = $lunar->forNight($date, $imagingPlan->observingLocation, $at)->illuminationPercent;
        }

        $bortleClass = $imagingPlan->observingLocation?->bortle_class;

        $acquisitions = $imagingPlan->targets->mapWithKeys(
            function (ImagingPlanTarget $planTarget) use (
                $acquisition,
                $imagingPlan,
                $optics,
                $filterTypes,
                $moonIllumination,
                $lunar,
                $at,
                $bortleClass,
            ): array {
                $moonSeparation = 180.0;

                if ($imagingPlan->observingLocation && $at) {
                    $moonSeparation = $lunar->separationFromTargetDeg(
                        $planTarget->celestialObject->ra_deg,
                        $planTarget->celestialObject->dec_deg,
                        $at,
                        $imagingPlan->observingLocation,
                    );
                }

                return [
                    $planTarget->id => $acquisition->suggest(
                        $planTarget->celestialObject,
                        $imagingPlan->equipmentSetup,
                        $optics,
                        $filterTypes,
                        $moonIllumination,
                        $moonSeparation,
                        $bortleClass,
                    ),
                ];
            },
        );

        return view('admin.nightscope.planner.show', [
            'plan' => $imagingPlan,
            'acquisitions' => $acquisitions,
            'locations' => ObservingLocation::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'equipmentSetups' => EquipmentSetup::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function update(
        UpdateImagingPlanRequest $request,
        ImagingPlan $imagingPlan,
        UpdateImagingPlanAction $update,
    ): RedirectResponse {
        try {
            $update->execute($imagingPlan, $request->validated());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', __('Plan mis à jour.'));
    }

    public function updateTarget(
        UpdateImagingPlanTargetRequest $request,
        ImagingPlan $imagingPlan,
        ImagingPlanTarget $imagingPlanTarget,
        UpdateImagingPlanTargetAction $update,
    ): RedirectResponse {
        $validated = $request->validated();
        $timezone = $imagingPlan->observingLocation?->timezone ?? config('app.timezone');
        $date = CarbonImmutable::parse($imagingPlan->date->format('Y-m-d'), $timezone);
        $startsAt = $date->setTimeFromTimeString($validated['planned_start']);
        $endsAt = $date->setTimeFromTimeString($validated['planned_end']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt = $endsAt->addDay();
        }

        try {
            $update->execute($imagingPlan, $imagingPlanTarget, $startsAt, $endsAt);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', __('Horaires de la cible mis à jour.'));
    }

    public function destroy(ImagingPlan $imagingPlan): RedirectResponse
    {
        if ($imagingPlan->status === ImagingPlanStatus::Active) {
            return back()->with('error', __('Termine ou annule la session avant de supprimer ce plan.'));
        }

        $imagingPlan->delete();

        return redirect()->route('admin.nightscope.planner')->with('success', __('Plan supprimé.'));
    }

    public function duplicate(
        DuplicateImagingPlanRequest $request,
        ImagingPlan $imagingPlan,
        DuplicateImagingPlanAction $duplicate,
    ): RedirectResponse {
        $copy = $duplicate->execute(
            $imagingPlan,
            CarbonImmutable::parse($request->validated('date')),
            $request->validated('notes'),
        );

        return redirect()
            ->route('admin.nightscope.plans.show', $copy)
            ->with('success', __('Plan dupliqué sur la date choisie.'));
    }

    public function addTarget(
        AddImagingPlanTargetRequest $request,
        ImagingPlan $imagingPlan,
        AddImagingPlanTargetAction $add,
    ): RedirectResponse {
        $validated = $request->validated();
        $target = CelestialObject::query()->findOrFail($validated['celestial_object_id']);
        $timezone = $imagingPlan->observingLocation?->timezone ?? config('app.timezone');
        $date = CarbonImmutable::parse($imagingPlan->date->format('Y-m-d'), $timezone);
        $startsAt = null;
        $endsAt = null;

        if (! empty($validated['planned_start']) && ! empty($validated['planned_end'])) {
            $startsAt = $date->setTimeFromTimeString($validated['planned_start']);
            $endsAt = $date->setTimeFromTimeString($validated['planned_end']);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $endsAt = $endsAt->addDay();
            }
        }

        try {
            $add->execute($imagingPlan, $target, $startsAt, $endsAt);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', __('Cible ajoutée au plan.'));
    }

    public function moveTargetUp(ImagingPlan $imagingPlan, ImagingPlanTarget $imagingPlanTarget): RedirectResponse
    {
        return $this->swapTarget($imagingPlan, $imagingPlanTarget, -1);
    }

    public function moveTargetDown(ImagingPlan $imagingPlan, ImagingPlanTarget $imagingPlanTarget): RedirectResponse
    {
        return $this->swapTarget($imagingPlan, $imagingPlanTarget, 1);
    }

    public function removeTarget(ImagingPlan $imagingPlan, ImagingPlanTarget $imagingPlanTarget): RedirectResponse
    {
        abort_unless($imagingPlanTarget->imaging_plan_id === $imagingPlan->id, 404);

        if ($imagingPlan->status === ImagingPlanStatus::Active) {
            return back()->with('error', __('Impossible de modifier un plan en session active.'));
        }

        DB::transaction(function () use ($imagingPlan, $imagingPlanTarget): void {
            $imagingPlanTarget->delete();
            $imagingPlan->targets()
                ->orderBy('position')
                ->get()
                ->each(fn (ImagingPlanTarget $target, int $index) => $target->update(['position' => $index + 1]));
        });

        return back()->with('success', __('Cible retirée du plan.'));
    }

    public function start(
        ImagingPlan $imagingPlan,
        WeatherManager $weather,
        WeatherScoreCalculator $weatherScores,
        SolarCalculator $solar,
    ): RedirectResponse {
        DB::transaction(function () use ($imagingPlan, $weather, $weatherScores, $solar): void {
            $imagingPlan->loadMissing(['targets', 'observingLocation']);
            $firstTarget = $imagingPlan->targets->first();
            $weatherSnapshot = null;

            if ($imagingPlan->observingLocation) {
                $date = CarbonImmutable::parse($imagingPlan->date->format('Y-m-d'), $imagingPlan->observingLocation->timezone);
                $night = $solar->forNight($date, $imagingPlan->observingLocation);
                $score = $weatherScores->calculate(
                    $weather->forecast($imagingPlan->observingLocation, $date, 8),
                    $night,
                );
                $weatherSnapshot = $score ? [
                    'score' => $score->score,
                    'label' => $score->label,
                    'clouds' => $score->averageCloudCoverPercent,
                    'humidity' => $score->averageHumidityPercent,
                    'wind' => $score->averageWindSpeedKmh,
                    'dew_point' => $score->averageDewPointCelsius,
                    'seeing' => $score->averageSeeingIndex,
                    'transparency' => $score->averageTransparencyIndex,
                    'captured_at' => now()->toIso8601String(),
                ] : null;
            }

            ImagingSession::firstOrCreate(
                ['imaging_plan_id' => $imagingPlan->id],
                [
                    'observing_location_id' => $imagingPlan->observing_location_id,
                    'equipment_setup_id' => $imagingPlan->equipment_setup_id,
                    'started_at' => $firstTarget?->planned_start_at ?? now(),
                    'notes' => $imagingPlan->notes,
                    'weather_data' => $weatherSnapshot,
                ],
            );
            $imagingPlan->update(['status' => ImagingPlanStatus::Active]);
        });

        return back()->with('success', __('Session d’imagerie démarrée.'));
    }

    public function finish(ImagingSession $imagingSession): RedirectResponse
    {
        DB::transaction(function () use ($imagingSession): void {
            $imagingSession->update(['ended_at' => now()]);
            $imagingSession->imagingPlan?->update(['status' => ImagingPlanStatus::Completed]);
        });

        return back()->with('success', __('Session d’imagerie terminée.'));
    }

    public function updateSession(
        UpdateImagingSessionRequest $request,
        ImagingSession $imagingSession,
    ): RedirectResponse {
        $imagingSession->update(['notes' => $request->validated('notes')]);

        return back()->with('success', __('Notes de session mises à jour.'));
    }

    private function swapTarget(ImagingPlan $plan, ImagingPlanTarget $target, int $direction): RedirectResponse
    {
        abort_unless($target->imaging_plan_id === $plan->id, 404);

        if ($plan->status === ImagingPlanStatus::Active) {
            return back()->with('error', __('Impossible de réordonner un plan en session active.'));
        }

        $neighbor = $plan->targets()
            ->where('position', $target->position + $direction)
            ->first();

        if (! $neighbor) {
            return back();
        }

        DB::transaction(function () use ($target, $neighbor): void {
            $current = $target->position;
            $target->update(['position' => $neighbor->position]);
            $neighbor->update(['position' => $current]);
        });

        return back()->with('success', __('Ordre des cibles mis à jour.'));
    }
}
