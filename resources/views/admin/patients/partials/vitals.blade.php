<div class="row">
    <div class="col-12">
        <form method="post" action="{{ route('vitals.store') }}">
            @csrf
            <div class="row">
                <div class="form-group col-md-4">
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <label for="bloodPressure">Blood Pressure (mmHg) <span
                            class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="bloodPressure" name="bloodPressure"
                        pattern="\d+/\d+">
                    <small class="form-text text-muted">Enter in the format of "systolic/diastolic", e.g.,
                        120/80.</small>
                </div>
                <div class="form-group col-md-4">
                    <label for="bodyTemperature">Body Temperature (°C) <span
                            class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="bodyTemperature" name="bodyTemperature"
                        min="34" max="39" step="0.1" required>
                    <small class="form-text text-muted">Min : 34, Max: 39</small>
                </div>
                <div class="form-group col-md-4">
                    <label for="bodyWeight">Body Weight (Kg)
                        <input type="number" class="form-control" id="bodyWeight" name="bodyWeight"
                            min="1" max="300" step="0.1" required>
                        <small class="form-text text-muted">Min : 1, Max: 300</small>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="respiratoryRate">Respiratory Rate (BPM)</label>
                    <input type="number" class="form-control" id="respiratoryRate" name="respiratoryRate"
                        min="12" max="50">
                    <small class="form-text text-muted">Breaths per Minute. Min : 12, Max: 50</small>
                </div>
                <div class="form-group col-md-6">
                    <label for="heartRate">Heart Rate (BPM)</label>
                    <input type="number" class="form-control" id="heartRate" name="heartRate"
                        min="60" max="220">
                    <small class="form-text text-muted">Beats Per Min. Min : 60, Max: 220</small>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="datetimeField">Time Taken</label>
                    <input type="datetime-local" class="form-control" id="datetimeField"
                        name="datetimeField" value="{{ date('Y-m-d\TH:i') }}" required>
                    <small class="form-text text-muted">The exact time the vitals were taken</small>
                </div>
                <div class="form-group col-md-6">
                    <label for="otherNotes">Other Notes</label>
                    <textarea name="otherNotes" id="otherNotes" class="form-control"></textarea>
                    <small class="form-text text-muted">Any other specifics about the patient</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <div class="col-12">
        <hr>
        <h4>Vital Signs Charts(up to last 30 readings)</h4>
        <br>
        <div class="row">
            <div class="col-md-6">
                <!-- Blood Pressure Chart -->
                <canvas id="bloodPressureChart"></canvas>
            </div>

            <div class="col-md-6">
                <!-- Temperature Chart -->
                <canvas id="temperatureChart"></canvas>
            </div>

            <div class="col-md-6">
                <!-- Weight Chart -->
                <canvas id="weightChart"></canvas>
            </div>

            <div class="col-md-6">
                <!-- Heart Rate Chart -->
                <canvas id="heartRateChart"></canvas>

            </div>

            <div class="col-md-6">
                <!-- Respiratory Rate Chart -->
                <canvas id="respRateChart"></canvas>
            </div>
        </div>

    </div>
</div>
<hr>
<h4>Vitals History</h4>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="vitals_history">
        <thead>
            <th>#</th>
            <th>Service</th>
            <th>Details</th>
            {{-- <th>Entry</th> --}}
        </thead>
    </table>
</div>