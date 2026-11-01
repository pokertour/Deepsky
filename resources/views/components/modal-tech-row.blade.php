@props(['label', 'id' => null, 'value' => null])

<div class="flex justify-between py-3 border-b border-border/30 text-[0.9rem]">
    <span class="text-[#8b949e]">{{ $label }}</span>
    <span @if ($id) id="{{ $id }}" @endif class="text-text-bold font-mono">
        {{ $value ?? '---' }}
    </span>
</div>
