{{-- Tracking Module Stat Cards - Pass $stats as array of ['label'=>..., 'value'=>..., 'icon'=>..., 'color'=>..., 'subtitle'=>...] --}}
<div class="row mb-3">
    @foreach($trackingStats as $stat)
    <div class="col-sm-6 col-md-3 mb-2">
        <div class="card border-0 shadow-sm" style="border-radius: 10px; border-left: 4px solid {{ $stat['color'] }} !important;">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">{{ $stat['label'] }}</div>
                        <div class="font-weight-bold" style="font-size: 1.4rem; color: {{ $stat['color'] }};">{{ $stat['value'] }}</div>
                        @if(!empty($stat['subtitle']))
                            <div class="text-muted" style="font-size: 0.7rem;">{{ $stat['subtitle'] }}</div>
                        @endif
                    </div>
                    <div style="font-size: 1.8rem; color: {{ $stat['color'] }}; opacity: 0.3;">
                        <i class="mdi {{ $stat['icon'] }}"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
