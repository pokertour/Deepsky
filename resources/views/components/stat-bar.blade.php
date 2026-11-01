@props(['name', 'row'])

<div>
    <div class="flex justify-between items-baseline mb-1.5 font-mono text-[0.75rem]">
        <span class="text-text-bold uppercase truncate pr-4">{{ $name }}</span>
        <span class="text-accent whitespace-nowrap">{{ __(':valueh · :value2 photo:value3', ['value' => $row['hours'], 'value2' => $row['count'], 'value3' => $row['count'] > 1 ? 's' : '']) }}</span>
    </div>
    <div class="h-1.5 bg-border/40 rounded-full overflow-hidden">
        <div class="h-full bg-accent rounded-full" style="width: {{ $row['percent'] }}%"></div>
    </div>
</div>
