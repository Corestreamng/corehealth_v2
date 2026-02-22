{{--
    Dose Mode Toggle — Segmented Button Group
    Ref: CLINICAL_ORDERS_PLAN.md §2.2

    @param string $prefix  '' for doctor, 'cr_' for nurse
    Default: Structured mode checked

    Usage:
      Doctor:  @include('admin.partials.dose-mode-toggle', ['prefix' => ''])
      Nurse:   @include('admin.partials.dose-mode-toggle', ['prefix' => 'cr_'])
--}}
@php $prefix = $prefix ?? ''; @endphp

<div class="dose-mode-toggle-group mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Dose entry mode">
        <input type="radio" class="btn-check" name="{{ $prefix }}dose_mode"
               id="{{ $prefix }}dose_mode_simple" value="simple" autocomplete="off">
        <label class="btn btn-outline-secondary" for="{{ $prefix }}dose_mode_simple">
            <i class="fa fa-pen"></i> Simple Note
        </label>

        <input type="radio" class="btn-check" name="{{ $prefix }}dose_mode"
               id="{{ $prefix }}dose_mode_structured" value="structured" autocomplete="off" checked>
        <label class="btn btn-outline-primary" for="{{ $prefix }}dose_mode_structured">
            <i class="fa fa-th-list"></i> Structured Dose
        </label>
    </div>
    <div class="form-text text-muted mt-1" style="font-size: 0.78em; max-width: 520px;">
        <strong>Structured</strong>: amount, unit, route, frequency, duration &amp; qty in
        separate fields for precision.
        <strong>Simple</strong>: free text (e.g. "500mg BD × 5 days").
    </div>
</div>
