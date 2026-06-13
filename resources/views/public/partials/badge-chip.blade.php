{{-- One Badges-Center chip. Expects $badge = ['label','icon','color'].
     Palette keys are shared with the React admin preview. --}}
@php
    $palette = [
        'gold'    => 'bg-gold-whisper text-gold-deep',
        'sage'    => 'bg-sage-mist text-sage-deep',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'red'     => 'bg-red-50 text-red-600',
        'blue'    => 'bg-blue-50 text-blue-700',
        'amber'   => 'bg-amber-50 text-amber-700',
        'purple'  => 'bg-purple-50 text-purple-700',
        'sky'     => 'bg-sky-50 text-sky-700',
        'gray'    => 'bg-gray-100 text-gray-600',
    ];
    $cls = $palette[$badge['color'] ?? 'gold'] ?? $palette['gold'];
@endphp
<span class="inline-flex items-center gap-1 {{ $cls }} px-2 py-0.5 rounded-full text-xs font-semibold">
    <x-icon :name="$badge['icon'] ?? 'star-solid'" class="w-3.5 h-3.5" /> {{ $badge['label'] }}
</span>
