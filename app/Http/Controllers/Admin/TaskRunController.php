<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskRunStatus;
use App\Enums\TaskRunType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTaskRunRequest;
use App\Http\Requests\Admin\StoreTaskRunRequest;
use App\Jobs\RunScheduledCommandJob;
use App\Models\TaskRun;
use App\Support\RunnableCommands;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TaskRunController extends Controller
{
    public function index(IndexTaskRunRequest $request): View
    {
        $runs = TaskRun::query()
            ->when($request->validated('type'), fn ($query, string $type) => $query->where('type', $type))
            ->when($request->validated('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->validated('name'), fn ($query, string $name) => $query->where('name', $name))
            ->latest('started_at')->latest('id')
            ->paginate(25)->withQueryString();

        $latestCommands = TaskRun::query()
            ->whereIn('id', TaskRun::query()->selectRaw('max(id)')->where('type', TaskRunType::Command)->groupBy('name'))
            ->get()
            ->keyBy('name');

        $lastDay = TaskRun::query()
            ->where('started_at', '>=', now()->subDay())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.tasks.index', [
            'runs' => $runs,
            'scheduledCommands' => collect(RunnableCommands::names())
                ->merge($latestCommands->keys())->unique()
                ->mapWithKeys(fn (string $name): array => [$name => $latestCommands->get($name)]),
            'lastDayCounts' => collect(TaskRunStatus::cases())->mapWithKeys(fn (TaskRunStatus $status): array => [$status->value => (int) ($lastDay[$status->value] ?? 0)]),
            'names' => TaskRun::query()->distinct()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreTaskRunRequest $request): RedirectResponse
    {
        $command = $request->validated('command');
        $label = TaskRun::labelFor($command);
        $isRunning = TaskRun::where('name', $command)
            ->where('status', TaskRunStatus::Running)
            ->where('started_at', '>=', now()->subMinutes(30))
            ->exists();

        // Le verrou couvre le délai entre la mise en file et le démarrage par le worker.
        if ($isRunning || ! Cache::add('task-launch:'.$command, true, now()->addMinute())) {
            return back()->with('error', __('« :task » est déjà en cours ou vient d’être lancée.', ['task' => $label]));
        }

        RunScheduledCommandJob::dispatch($command);

        return back()->with('success', __('« :task » a été ajoutée à la file. Elle apparaîtra dans l’historique dès son démarrage.', ['task' => $label]));
    }
}
