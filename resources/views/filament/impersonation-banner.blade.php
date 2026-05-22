@php $imp = app(\App\Services\ImpersonationService::class); @endphp

@if($imp->isImpersonating())
    @php $admin = $imp->originalAdmin(); @endphp
    <div class="bg-red-600 text-white py-2 px-4 text-center text-sm font-semibold flex items-center justify-center gap-3 sticky top-0 z-50">
        <span>⚠️ {{ __('admin.impersonation_banner', ['admin' => $admin?->name ?? '—']) }}</span>
        <form method="POST" action="{{ route('impersonate.stop') }}" class="inline">
            @csrf
            <button type="submit" class="bg-white text-red-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-red-50 transition-colors">
                {{ __('admin.impersonation_stop') }}
            </button>
        </form>
    </div>
@endif
