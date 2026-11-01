<div class="pointer-events-none absolute inset-0 text-[10px] text-text-main/65 sm:hidden" aria-hidden="true">
    @foreach ([0, 30, 60, 90] as $height)
        <span class="absolute left-1 -translate-y-1/2" style="top: {{ (204 - $height * 2) / 250 * 100 }}%">{{ $height }}°</span>
    @endforeach
    @foreach ([0 => 'N', 90 => 'E', 180 => 'S', 270 => 'O', 360 => 'N'] as $azimuth => $direction)
        <span class="absolute bottom-[5%] -translate-x-1/2" style="left: {{ (40 + $azimuth * 2) / 800 * 100 }}%">{{ $direction }}</span>
    @endforeach
</div>
