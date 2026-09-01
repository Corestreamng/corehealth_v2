<div class="queue-view" id="nursing-reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box-outline"></i> Nursing Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-nursing-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 0; overflow: hidden;">
                <!-- Global Filters Panel -->
                <div class="nursing-reports-filters p-3 bg-light border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-calendar-range"></i> Date Range
                            </label>
                            <select class="form-control form-control-modern" id="nr-date-range">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="7days" selected>Last 7 Days</option>
                                <option value="30days">Last 30 Days</option>
                                <option value="thismonth">This Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="nr-custom-dates" style="display: none;">
                            <label class="form-label-modern">From - To</label>
                            <div class="d-flex gap-2">
                                <input type="date" class="form-control form-control-modern" id="nr-date-from" style="height: 40px !important;">
                                <input type="date" class="form-control form-control-modern" id="nr-date-to" style="height: 40px !important;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-hospital-building"></i> Ward
                            </label>
                            <select class="form-control form-control-modern" id="nr-ward-filter">
                                <option value="">All Wards</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-account-nurse"></i> Nurse
                            </label>
                            <select class="form-control form-control-modern" id="nr-nurse-filter">
                                <option value="">All Nurses</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-clock-outline"></i> Shift
                            </label>
                            <select class="form-control form-control-modern" id="nr-shift-filter">
                                <option value="">All Shifts</option>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="night">Night</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary btn-modern flex-grow-1" id="nr-apply-filters">
                                <i class="mdi mdi-filter"></i> Apply
                            </button>
                            <button class="btn btn-outline-secondary btn-modern" id="nr-reset-filters" title="Reset Filters">
                                <i class="mdi mdi-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reports Tabs -->
                <div class="nursing-reports-tabs-wrapper">
                    <ul class="nav nav-tabs nursing-reports-tabs" id="nursingReportsTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="nr-activity-tab" data-toggle="tab" href="#nr-activity" role="tab">
                                <i class="mdi mdi-chart-timeline-variant"></i> Activity Summary
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-vitals-tab" data-toggle="tab" href="#nr-vitals" role="tab">
                                <i class="mdi mdi-heart-pulse"></i> Vitals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-medications-tab" data-toggle="tab" href="#nr-medications" role="tab">
                                <i class="mdi mdi-pill"></i> Medications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-injections-tab" data-toggle="tab" href="#nr-injections" role="tab">
                                <i class="mdi mdi-needle"></i> Injections
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-io-tab" data-toggle="tab" href="#nr-io" role="tab">
                                <i class="mdi mdi-water"></i> I/O Balance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-notes-tab" data-toggle="tab" href="#nr-notes" role="tab">
                                <i class="mdi mdi-note-text"></i> Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-shifts-tab" data-toggle="tab" href="#nr-shifts" role="tab">
                                <i class="mdi mdi-account-clock"></i> Shift Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-occupancy-tab" data-toggle="tab" href="#nr-occupancy" role="tab">
                                <i class="mdi mdi-bed"></i> Ward Occupancy
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tab Content -->
                <div class="tab-content nursing-reports-content" id="nursingReportsContent">
                    <!-- Activity Summary Tab -->
                    @include('admin.nursing.partials.tabs._activity')

                    <!-- Vitals Tab -->
                    @include('admin.nursing.partials.tabs._vitals')

                    <!-- Medications Tab -->
                    @include('admin.nursing.partials.tabs._medications')

                    <!-- Injections Tab -->
                    @include('admin.nursing.partials.tabs._injections')

                    <!-- I/O Balance Tab -->
                    @include('admin.nursing.partials.tabs._io')

                    <!-- Notes Tab -->
                    @include('admin.nursing.partials.tabs._notes')

                    <!-- Shift Performance Tab -->
                    @include('admin.nursing.partials.tabs._shifts')

                    <!-- Ward Occupancy Tab -->
                    @include('admin.nursing.partials.tabs._occupancy')
                </div>
            </div>
        </div>