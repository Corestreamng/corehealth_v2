@php
    $req = $req ?? null;
    // If passed through json_decode(..., true), convert the nested arrays back to objects
    if (is_array($req)) {
        $req = json_decode(json_encode($req));
    }
@endphp
@if($req)
    <div style="font-size: 0.75rem; line-height: 1.4;" class="mt-2">
        @if($req->staff ?? null)
            @php $billedBy = trim($req->staff->firstname . ' ' . ($req->staff->surname ?? '')); @endphp
            <div class="text-muted mb-1"><i class="mdi mdi-account-cash me-1"></i> Billed By: {{ $billedBy }}</div>
        @endif
        @if($req->labRequest ?? null)
            @php
                $lab = $req->labRequest;
                $labStatus = ($lab->status ?? 0) == 1 ? 'Completed' : 'Pending';
                $sampler = ($lab->sampler ?? null) ? trim($lab->sampler->firstname . ' ' . ($lab->sampler->surname ?? '')) : null;
                $resultBy = ($lab->resultBy ?? null) ? trim($lab->resultBy->firstname . ' ' . ($lab->resultBy->surname ?? '')) : null;
            @endphp
            <div class="text-muted"><i class="mdi mdi-flask me-1 text-primary"></i> <strong>Lab Request:</strong> #{{ $lab->lab_number ?? $lab->id ?? '-' }}</div>
            <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                <div>Status: <span class="badge bg-{{ ($lab->status ?? 0) == 1 ? 'success' : 'warning' }} px-1 py-0">{{ $labStatus }}</span></div>
                @if($sampler) <div class="text-muted"><i class="mdi mdi-test-tube me-1"></i> Sampled By: {{ $sampler }}</div> @endif
                @if($resultBy) <div class="text-muted"><i class="mdi mdi-check-decagram me-1"></i> Result By: {{ $resultBy }}</div> @endif
            </div>
        @endif
        @if($req->imagingRequest ?? null)
            @php
                $img = $req->imagingRequest;
                $imgStatus = ($img->status ?? 0) == 1 ? 'Completed' : 'Pending';
                $resultBy = ($img->resultBy ?? null) ? trim($img->resultBy->firstname . ' ' . ($img->resultBy->surname ?? '')) : null;
            @endphp
            <div class="text-muted"><i class="mdi mdi-radiology me-1 text-primary"></i> <strong>Imaging Request:</strong> #{{ $img->radiology_number ?? $img->id ?? '-' }}</div>
            <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                <div>Status: <span class="badge bg-{{ ($img->status ?? 0) == 1 ? 'success' : 'warning' }} px-1 py-0">{{ $imgStatus }}</span></div>
                @if($resultBy) <div class="text-muted"><i class="mdi mdi-check-decagram me-1"></i> Result By: {{ $resultBy }}</div> @endif
            </div>
        @endif
        @if($req->productRequest ?? null)
            @php
                $rx = $req->productRequest;
            @endphp
            <div class="text-muted"><i class="mdi mdi-pill me-1 text-primary"></i> <strong>Prescription:</strong> #{{ $rx->prescription_number ?? $rx->id ?? '-' }}</div>
            <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                <div>Status: <span class="badge bg-{{ ($rx->status ?? 0) == 1 ? 'success' : 'warning' }} px-1 py-0">{{ ($rx->status ?? 0) == 1 ? 'Dispensed' : 'Pending' }}</span></div>
                @if($rx->dose ?? null) <div class="text-muted"><i class="mdi mdi-information-outline me-1"></i> Dose: {{ $rx->dose }}</div> @endif
            </div>
        @endif
        @if($req->procedure ?? null)
            @php
                $proc = $req->procedure;
                $requestedBy = ($proc->requestedByUser ?? null) ? trim($proc->requestedByUser->firstname . ' ' . ($proc->requestedByUser->surname ?? '')) : null;
            @endphp
            <div class="text-muted"><i class="mdi mdi-needle me-1 text-primary"></i> <strong>Procedure:</strong> #{{ $proc->id ?? '-' }}</div>
            <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                <div>Status: <span class="badge bg-{{ ($proc->status ?? '') == 'completed' ? 'success' : (($proc->status ?? '') == 'cancelled' ? 'danger' : 'warning') }} px-1 py-0">{{ ucfirst($proc->status ?? 'pending') }}</span></div>
                @if($proc->outcome ?? null) <div class="text-muted">Outcome: {{ ucfirst($proc->outcome) }}</div> @endif
                @if($requestedBy) <div class="text-muted"><i class="mdi mdi-account me-1"></i> Requested By: {{ $requestedBy }}</div> @endif
            </div>
        @endif
        @if($req->encounter ?? null)
            @php
                $enc = $req->encounter;
                $doctor = ($enc->doctor ?? null) ? trim($enc->doctor->firstname . ' ' . ($enc->doctor->surname ?? '')) : null;
                $clinicName = ($enc->queue->clinic ?? null) ? $enc->queue->clinic->name : null;
            @endphp
            <div class="text-muted"><i class="mdi mdi-stethoscope me-1 text-primary"></i> <strong>Encounter:</strong> #{{ $enc->id ?? '-' }}</div>
            <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                @if($doctor) <div class="text-muted"><i class="mdi mdi-doctor me-1"></i> Doctor: Dr. {{ $doctor }}</div> @endif
                @if($clinicName) <div class="text-muted"><i class="mdi mdi-hospital-building me-1"></i> Clinic: {{ $clinicName }}</div> @endif
                <div class="text-muted"><i class="mdi mdi-calendar me-1"></i> Date: {{ ($enc->created_at ?? null) ? \Carbon\Carbon::parse($enc->created_at)->format('d M y H:i') : '-' }}</div>
            </div>
        @elseif($req->doctor_queue_entry ?? null)
            @php
                $queue = $req->doctor_queue_entry;
                $clinic = ($queue->clinic ?? null) ? $queue->clinic->name : null;
                $queueDoc = ($queue->doctor ?? null) ? trim($queue->doctor->firstname . ' ' . ($queue->doctor->surname ?? '')) : null;
            @endphp
            <div class="text-muted"><i class="mdi mdi-stethoscope me-1 text-primary"></i> <strong>Consultation Queue:</strong> #{{ $queue->id ?? '-' }}</div>
            <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                @if($clinic) <div class="text-muted"><i class="mdi mdi-hospital-building me-1"></i> Clinic: {{ $clinic }}</div> @endif
                @if($queueDoc) <div class="text-muted"><i class="mdi mdi-doctor me-1"></i> Doctor: Dr. {{ $queueDoc }}</div> @endif
                <div class="text-muted"><i class="mdi mdi-calendar me-1"></i> Queued At: {{ ($queue->created_at ?? null) ? \Carbon\Carbon::parse($queue->created_at)->format('d M y H:i') : '-' }}</div>
            </div>
        @endif
    </div>
@endif
