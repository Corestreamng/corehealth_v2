{{--
    Shared Patient Search HTML Partial
    ====================================
    Usage:  @include('admin.partials.patient_search_html')

    Renders the search input + dropdown container.
    Must be placed inside a positioned parent (typically the left‑panel).
--}}

<div class="search-container" style="position: relative;">
    <input type="text"
           id="patient-search-input"
           placeholder="Search by file no, name or phone..."
           autocomplete="off">
    <div class="search-results" id="patient-search-results"></div>
</div>
