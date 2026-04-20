{{-- Scoped Staff Context Bar - shown when viewing records for a specific staff member --}}
@if(isset($scopedStaff) && $scopedStaff)
<div class="d-flex align-items-center justify-content-between mb-3 px-3 py-2" style="background: linear-gradient(135deg, {{ appsettings()->hos_color ?? '#011b33' }}, {{ appsettings()->hos_color ?? '#011b33' }}cc); border-radius: 10px; color: #fff;">
    <div class="d-flex align-items-center">
        <a href="{{ route('hr.tracking.profile', $scopedStaff->id) }}" class="btn btn-sm mr-3" style="border-radius: 8px; background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.3);">
            <i class="mdi mdi-arrow-left mr-1"></i> Back to Profile
        </a>
        <div style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;margin-right:0.75rem;">
            {{ strtoupper(substr($scopedStaff->user?->surname ?? '?', 0, 1)) }}{{ strtoupper(substr($scopedStaff->user?->firstname ?? '', 0, 1)) }}
        </div>
        <div>
            <div style="font-size:1rem;font-weight:700;">{{ $scopedStaff->user?->surname }} {{ $scopedStaff->user?->firstname }} {{ $scopedStaff->user?->othername }}</div>
            <div style="opacity:0.8;font-size:0.8rem;">
                @if($scopedStaff->employee_id)<span class="mr-2"><i class="mdi mdi-identifier mr-1"></i>{{ $scopedStaff->employee_id }}</span>@endif
                <span class="mr-2"><i class="mdi mdi-domain mr-1"></i>{{ $scopedStaff->department?->name ?? '—' }}</span>
                <span class="mr-2"><i class="mdi mdi-stairs mr-1"></i>{{ $scopedStaff->gradeLevel?->name ?? '—' }}</span>
                @if($scopedStaff->cadre)<span><i class="mdi mdi-briefcase mr-1"></i>{{ $scopedStaff->cadre->name }}</span>@endif
            </div>
        </div>
    </div>
    <div class="d-flex align-items-center" style="gap:0.5rem;">
        @if(isset($scopedStaffLinks))
            @foreach($scopedStaffLinks as $link)
                <a href="{{ $link['url'] }}" class="btn btn-sm" style="border-radius:6px;background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);font-size:0.78rem;">
                    <i class="mdi {{ $link['icon'] }} mr-1"></i>{{ $link['label'] }}
                </a>
            @endforeach
        @endif
    </div>
</div>
@endif
