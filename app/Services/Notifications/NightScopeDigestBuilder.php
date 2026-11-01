<?php

namespace App\Services\Notifications;

use App\Data\Notifications\NightScopeDigest;
use App\Data\Planning\TonightData;
use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use App\Services\Planning\TonightService;
use App\Services\Weather\ForecastService;
use App\Settings\NotificationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Traits\Localizable;

class NightScopeDigestBuilder
{
    use Localizable;

    public function __construct(
        private TonightService $tonight,
        private ForecastService $forecast,
        private NotificationSettings $settings,
        private NightScopeDigestFormatter $formatter,
    ) {}

    /** @return list<NightScopeDigest> */
    public function daily(bool $preview = false): array
    {
        return $this->withLocale($this->settings->locale, fn (): array => $this->buildDaily($preview));
    }

    public function weekly(bool $preview = false): ?NightScopeDigest
    {
        return $this->withLocale($this->settings->locale, fn (): ?NightScopeDigest => $this->buildWeekly($preview));
    }

    /** @return list<NightScopeDigest> */
    private function buildDaily(bool $preview): array
    {
        if (! $preview && ! $this->settings->wantsDailyDigest()) {
            return [];
        }

        $data = $this->tonight->build(CarbonImmutable::today(), recommendationLimit: 5);
        $digests = [];
        $favorable = $this->isFavorableNight($data);

        if ($preview || $this->settings->alert_daily_conditions) {
            $conditions = $this->conditionsDigest($data, $preview, $favorable);
            if ($conditions !== null) {
                $digests[] = $conditions;
            }
        }

        // Top cibles uniquement si la nuit est favorable (même seuil que l’alerte conditions).
        if (($preview || $this->settings->alert_best_targets) && ($preview || $favorable)) {
            $targets = $this->targetsDigest($data);
            if ($targets !== null) {
                $digests[] = $targets;
            }
        }

        return $digests;
    }

    private function buildWeekly(bool $preview): ?NightScopeDigest
    {
        if (! $preview && ! $this->settings->alert_weekly_report) {
            return null;
        }

        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $photos = Astrophoto::query()
            ->whereBetween('captured_at', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get(['exposure']);

        $forecast = $this->forecast->build(CarbonImmutable::today());
        $bestNight = collect($forecast->nights)
            ->filter(fn ($night) => $night->weather !== null)
            ->sortByDesc(fn ($night) => $night->weather->score)
            ->first();

        $lines = [
            $this->formatter->metaLine('📆', __('Semaine'), $weekStart->translatedFormat('d F').' → '.$weekEnd->translatedFormat('d F')),
            $this->formatter->metaLine('📷', __('Photos publiées'), (string) Astrophoto::query()->whereBetween('created_at', [$weekStart, $weekEnd])->count()),
            $this->formatter->metaLine('⏱️', __('Temps de pose'), $this->formatExposure((int) $photos->sum(fn (Astrophoto $photo): int => (int) $photo->exposure))),
            $this->formatter->metaLine('👥', __('Abonnés confirmés'), (string) NewsletterSubscriber::confirmed()->count()),
            $this->formatter->metaLine('🔗', __('Photos sans objet'), (string) Astrophoto::query()->whereNull('celestial_object_id')->count()),
        ];

        if ($bestNight?->weather) {
            $lines[] = '';
            $lines[] = $this->formatter->weatherEmoji($bestNight->weather).' '.__('<b>Meilleure nuit</b>').' '.$bestNight->date->translatedFormat('l d/m').'  '.$this->formatter->scoreHtml($bestNight->weather->score);
        }

        $tonight = $this->tonight->build(CarbonImmutable::today(), recommendationLimit: 3);
        if ($tonight->targets !== []) {
            $lines[] = '';
            $lines[] = __('🔭 <b>Cibles à surveiller</b>');
            foreach (array_values($tonight->targets) as $index => $result) {
                $lines[] = $this->formatter->targetLine($index + 1, $result->target->displayName(), $result->score);
            }
        }

        return new NightScopeDigest(
            title: __('📊 Deepsky — rapport hebdo'),
            lines: $lines,
            dedupeKey: 'weekly:'.$weekStart->format('Y-m-d'),
            ntfyTags: 'bar_chart,camera,telescope',
        );
    }

    private function isFavorableNight(TonightData $data): bool
    {
        return $data->location !== null
            && $data->nightScore !== null
            && $data->nightScore->score >= $this->settings->daily_min_score;
    }

    private function conditionsDigest(TonightData $data, bool $preview, bool $favorable): ?NightScopeDigest
    {
        if (! $data->location || ! $data->nightScore) {
            return null;
        }

        if (! $preview && ! $favorable) {
            return null;
        }

        $score = $data->nightScore->score;
        $lines = [
            $this->formatter->metaLine('📅', __('Date'), $data->date->translatedFormat('l d F Y')),
            $this->formatter->metaLine('📍', __('Lieu'), $data->location->name),
            '⭐ '.__('<b>Night score</b>').'  '.$this->formatter->scoreHtml($score),
        ];

        if ($data->weather) {
            $lines[] = $this->formatter->weatherEmoji($data->weather).' '.__('<b>Météo</b>').'  '.$this->formatter->scoreHtml($data->weather->score).' · '.e($data->weather->label);
        }

        if ($data->moon) {
            $lines[] = $this->formatter->moonEmoji($data->moon).' <b>Lune</b> '.round($data->moon->illuminationPercent).'%';
        }

        $lines[] = '';
        $lines[] = $favorable
            ? '<blockquote>'.__('✅ <b>Conditions favorables</b> ce soir (seuil :score/100)', ['score' => $this->settings->daily_min_score]).'</blockquote>'
            : '<blockquote>'.__('⚠️ Score <b>sous le seuil</b> (:score/100)', ['score' => $this->settings->daily_min_score]).'</blockquote>';

        return new NightScopeDigest(
            title: __('🌌 Deepsky — nuit du :date', ['date' => $data->date->format('d/m')]),
            lines: $lines,
            dedupeKey: 'daily:conditions:'.$data->date->format('Y-m-d'),
            ntfyTags: 'milky_way,star,sparkles',
        );
    }

    private function targetsDigest(TonightData $data): ?NightScopeDigest
    {
        if (! $data->location || $data->targets === []) {
            return null;
        }

        $lines = [
            $this->formatter->metaLine('📅', __('Date'), $data->date->translatedFormat('l d F Y')),
            $this->formatter->metaLine('📍', __('Lieu'), $data->location->name),
            '',
            __('🔭 <b>Top cibles</b>'),
        ];

        foreach (array_slice($data->targets, 0, 3) as $index => $result) {
            $lines[] = $this->formatter->targetLine($index + 1, $result->target->displayName(), $result->score);
        }

        return new NightScopeDigest(
            title: __('🎯 Deepsky — top cibles'),
            lines: $lines,
            dedupeKey: 'daily:targets:'.$data->date->format('Y-m-d'),
            ntfyTags: 'dart,telescope,star',
        );
    }

    private function formatExposure(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 h';
        }

        return floor($seconds / 3600).' h '.str_pad((string) floor(($seconds % 3600) / 60), 2, '0', STR_PAD_LEFT);
    }
}
