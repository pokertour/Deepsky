<?php

namespace App\Support;

class AcquisitionData
{
    public const FRAME_TYPES = ['light' => 'Light', 'dark' => 'Dark', 'flat' => 'Flat', 'bias' => 'Bias'];

    public const CAMERA_MODES = ['color' => 'Couleur (OSC)', 'mono' => 'Monochrome'];

    public static function integrationSeconds(array $sessions): float
    {
        return round(collect($sessions)->flatMap(fn (array $session) => $session['frames'] ?? [])
            ->where('type', 'light')
            ->sum(fn (array $frame) => (int) $frame['count'] * (float) $frame['seconds']), 3);
    }

    public static function filterSummary(array $sessions): array
    {
        return collect($sessions)->flatMap(fn (array $session) => $session['frames'] ?? [])
            ->where('type', 'light')
            ->groupBy(fn (array $frame) => ($frame['filter'] ?? null) ?: __('Sans filtre'))
            ->map(fn ($frames, $filter) => [
                'filter' => $filter,
                'count' => $frames->sum('count'),
                'duration' => self::formatDuration($frames->sum(fn (array $frame) => (int) $frame['count'] * (float) $frame['seconds'])),
            ])->values()->all();
    }

    public static function formatDuration(float $seconds): string
    {
        $milliseconds = (int) round(max(0, $seconds) * 1000);
        $hours = intdiv($milliseconds, 3600000);
        $minutes = intdiv($milliseconds % 3600000, 60000);
        $remaining = ($milliseconds % 60000) / 1000;

        return implode(' ', array_filter([
            $hours > 0 ? $hours.'h' : null,
            $minutes > 0 ? $minutes.'m' : null,
            $remaining > 0 || $milliseconds === 0 ? $remaining.'s' : null,
        ], fn ($part) => $part !== null));
    }
}
