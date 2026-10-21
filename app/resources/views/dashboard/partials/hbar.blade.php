<div class="hbar" data-tip="{{ $tip }}" tabindex="0">
    <span class="l">{{ $label }}</span>
    <span class="track"><b style="width: {{ $max ? max(1.5, $value / $max * 100) : 0 }}%"></b></span>
    <span class="v">{{ $text }}</span>
</div>
