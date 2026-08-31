<div class="modal fade" id="addHistoryModal" tabindex="-1" aria-labelledby="addHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addHistoryModalLabel"><i class="mdi mdi-clipboard-text-clock"></i> Add Medical History</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i> Record relevant past medical, surgical, obstetric, family, or social history that may affect pregnancy management.</div>
                <form id="add-history-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-clipboard-list"></i> History Details</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category <span class="mat-tooltip-icon" title="Medical: chronic illnesses. Surgical: previous operations. Obstetric: prior pregnancy complications. Family: inherited conditions. Social: smoking, alcohol, occupation"><i class="mdi mdi-help-circle"></i></span></label>
                                <select name="category" class="form-select" required>
                                    <option value="medical">Medical</option>
                                    <option value="surgical">Surgical</option>
                                    <option value="obstetric">Obstetric</option>
                                    <option value="family">Family</option>
                                    <option value="social">Social</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year <span class="mat-tooltip-icon" title="Year of diagnosis or occurrence"><i class="mdi mdi-help-circle"></i></span></label>
                                <input type="number" name="year" class="form-control" min="1950" placeholder="e.g. 2022">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description <span class="text-danger">*</span></label>
                                <input type="text" name="description" class="form-control" placeholder="e.g. Gestational diabetes in 2020" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" placeholder="Additional details">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-history"><i class="mdi mdi-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-alert"></i> Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this record? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-delete">Delete</button>
            </div>
        </div>
    </div>
</div>

{{-- 2. Add Previous Pregnancy Modal --}}
<div class="modal fade" id="addPregnancyModal" tabindex="-1" aria-labelledby="addPregnancyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addPregnancyModalLabel"><i class="mdi mdi-baby-carriage"></i> Add Previous Pregnancy</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i> Document each prior pregnancy to build a complete obstetric profile. This helps assess current risk factors.</div>
                <form id="add-pregnancy-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-calendar-clock"></i> Pregnancy Details</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Year</label><input type="number" name="year" class="form-control" min="1950" placeholder="e.g. 2021"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Duration (wks) <span class="mat-tooltip-icon" title="Gestational age at delivery. Term: 37–42 weeks. Preterm: <37 weeks"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="duration_weeks" class="form-control" min="1" max="45" placeholder="e.g. 39"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Place of Delivery</label><input type="text" name="place_of_delivery" class="form-control" placeholder="e.g. General Hospital"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Baby Sex</label><select name="baby_sex" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Birth Weight (kg) <span class="mat-tooltip-icon" title="Normal: 2.5–4.0 kg. Low birth weight may recur in subsequent pregnancies"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="birth_weight_kg" class="form-control" step="0.1" placeholder="e.g. 3.2"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-clipboard-check"></i> Outcome</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Outcome <span class="mat-tooltip-icon" title="Alive: live birth. Dead: neonatal death. Stillbirth: fetal death ≥20 weeks or ≥500g"><i class="mdi mdi-help-circle"></i></span></label><select name="outcome" class="form-select">
                                    <option value="alive">Alive</option>
                                    <option value="dead">Dead</option>
                                    <option value="stillbirth">Stillbirth</option>
                                </select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Complications</label><input type="text" name="complications" class="form-control" placeholder="e.g. Pre-eclampsia, PPH"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" placeholder="Additional observations"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-pregnancy"><i class="mdi mdi-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- 3. Add Growth Record Modal --}}
<div class="modal fade" id="addGrowthModal" tabindex="-1" aria-labelledby="addGrowthModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="addGrowthModalLabel"><i class="mdi mdi-chart-line"></i> Add Growth Record</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-form-help mb-3"><i class="mdi mdi-information"></i> Track baby's growth over time. Compare with WHO growth standards for age-appropriate percentiles.</div>
                <form id="growth-record-form">
                    <input type="hidden" name="baby_id" id="growth-baby-id">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="record_date" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Weight (kg) <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Expected: birth weight regained by day 10–14. Gain ~150–200g/week in first 3 months"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="weight_kg" class="form-control" step="0.01" placeholder="e.g. 3.50" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Length (cm) <span class="mat-tooltip-icon" title="Expected growth: ~3–4 cm/month in first 3 months"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="length_height_cm" class="form-control" step="0.1" placeholder="e.g. 52.0"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Head Circ (cm) <span class="mat-tooltip-icon" title="Expected: ~1 cm/month growth in first year"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="head_circumference_cm" class="form-control" step="0.1" placeholder="e.g. 36.0"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">MUAC (cm) <span class="mat-tooltip-icon" title="Mid-Upper Arm Circumference. ≥11.5 cm = normal, <11.5 cm = at risk"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="muac_cm" class="form-control" step="0.1" placeholder="e.g. 12.0"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-info text-white" id="btn-save-growth"><i class="mdi mdi-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- 3c. Maternity Partograph Tab Modal (enrollment-level, pre & post delivery) --}}
<div class="modal fade" id="matPartographModal" tabindex="-1" aria-labelledby="matPartographModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="matPartographModalLabel"><i class="mdi mdi-chart-timeline-variant"></i> Add Partograph Entry</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i>
                    <div>Record labour progress and maternal/fetal observations. Entries recorded before delivery are labelled <strong>Labour Monitoring</strong>; those after delivery are labelled <strong>Post-Delivery</strong>.</div>
                </div>
                <form id="mat-partograph-form">
                    <input type="hidden" id="mat-partograph-enrollment-id">
                    <input type="hidden" id="mat-partograph-entry-id">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-clock-outline"></i> Timing &amp; Phase</div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Phase <span class="text-danger">*</span></label>
                                <select name="phase" class="form-select" id="mat-partograph-phase">
                                    <option value="pre_delivery">Labour Monitoring (Pre-Delivery)</option>
                                    <option value="post_delivery">Post-Delivery</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Recorded At <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="recorded_at" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Cervical Dilation (cm)</label>
                                <input type="number" name="cervical_dilation_cm" class="form-control" min="0" max="10" step="0.1">
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> 0 = closed, 10 = fully dilated</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Descent of Head</label>
                                <input type="text" name="descent_of_head" class="form-control" placeholder="e.g. 5/5, 3/5, 0/5">
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Fifths palpable above brim</div>
                            </div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-heart-pulse"></i> Fetal &amp; Contractions</div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Contractions /10 min</label>
                                <input type="number" name="contractions_per_10_min" class="form-control" min="0" max="20">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Duration (sec)</label>
                                <input type="number" name="contraction_duration_sec" class="form-control" min="0" max="180">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fetal Heart Rate (bpm)</label>
                                <input type="text" name="foetal_heart_rate" class="form-control" placeholder="e.g. 140, Nil">
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Normal: 110–160 bpm</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Amniotic Fluid</label>
                                <select name="amniotic_fluid" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="intact">I — Intact</option>
                                    <option value="clear">C — Clear</option>
                                    <option value="meconium_stained">M — Meconium stained</option>
                                    <option value="bloody">B — Bloody</option>
                                    <option value="absent">A — Absent</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Moulding</label>
                                <select name="moulding" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="none">None (0)</option>
                                    <option value="+">+ (touching)</option>
                                    <option value="++">++ (overlapping, reducible)</option>
                                    <option value="+++">+++ (overlapping, irreducible)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-account-heart"></i> Maternal Monitoring</div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Pulse (bpm)</label>
                                <input type="number" name="maternal_pulse" class="form-control" min="20" max="220">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">BP Systolic</label>
                                <input type="number" name="maternal_bp_systolic" class="form-control" min="40" max="300">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">BP Diastolic</label>
                                <input type="number" name="maternal_bp_diastolic" class="form-control" min="20" max="220">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Temp (°C)</label>
                                <input type="number" name="maternal_temp" class="form-control" step="0.1" min="30" max="45">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Urine Output (ml)</label>
                                <input type="number" name="urine_output_ml" class="form-control" min="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Urine Protein</label>
                                <select name="urine_protein" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="nil">Nil</option>
                                    <option value="trace">Trace</option>
                                    <option value="+">+</option>
                                    <option value="++">++</option>
                                    <option value="+++">+++</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Oxytocin Dose</label>
                                <input type="text" name="oxytocin_dose" class="form-control" placeholder="e.g. 10 IU in 500ml">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">IV Fluids</label>
                                <input type="text" name="iv_fluids" class="form-control" placeholder="e.g. Ringer's Lactate 1L">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Medications / Notes</label>
                                <textarea name="medications" class="form-control" rows="2" placeholder="Additional medications, observations, or notes"></textarea>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                    <button type="button" class="btn btn-success" id="btn-save-mat-partograph"><i class="mdi mdi-check"></i> Save Entry</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- 4. Add Clinical Note Modal --}}
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addNoteModalLabel"><i class="mdi mdi-note-plus"></i> Add Clinical Note</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i>
                    <div>Use the rich editor to write structured clinical notes. You can use <strong>headings, bold, lists</strong> and <strong>tables</strong> for clear documentation.</div>
                </div>
                <form id="add-note-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-tag"></i> Note Classification</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Note Type <span class="text-danger">*</span></label>
                                <select name="note_type_id" class="form-select" id="modal-note-type-select" required></select>
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Select the category (e.g. Progress, Discharge, Counselling)</div>
                            </div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-text-box"></i> Note Content</div>
                        <div id="mat-note-editor-modal"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div id="mat-note-autosave-status" class="mr-auto small" style="min-height: 1.2em;"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-note"><i class="mdi mdi-check"></i> Save Note</button>
            </div>
        </div>
    </div>
</div>

{{-- 5. Postnatal Visit Modal --}}
<div class="modal fade" id="postnatalModal" tabindex="-1" aria-labelledby="postnatalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="postnatalModalLabel"><i class="mdi mdi-account-heart"></i> Record Postnatal Visit</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i>
                    <div>Postnatal visits assess <strong>mother's recovery</strong> and <strong>baby's wellbeing</strong>. WHO recommends visits within 24h, Day 3, Week 1–2, and Week 6 post-delivery.</div>
                </div>
                <form id="postnatal-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-calendar-clock"></i> Visit Information</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Visit Type <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="WHO recommended schedule: Within 24h, Day 3, Week 1–2, Week 6"><i class="mdi mdi-help-circle"></i></span></label><select name="visit_type" class="form-select" required>
                                    <option value="within_24h">Within 24 hours</option>
                                    <option value="day_3">Day 3</option>
                                    <option value="week_1_2">Week 1–2</option>
                                    <option value="week_6">Week 6</option>
                                    <option value="other">Other</option>
                                </select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Visit Date <span class="text-danger">*</span></label><input type="date" name="visit_date" class="form-control" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Next Appointment</label><input type="date" name="next_appointment" class="form-control"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-mother-heart"></i> Mother Assessment</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">General Condition</label><select name="general_condition" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option>Good</option>
                                    <option>Fair</option>
                                    <option>Poor</option>
                                </select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Blood Pressure</label><input type="text" name="blood_pressure" class="form-control" placeholder="e.g. 120/80">
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">Temperature (°C)</label><input type="number" name="temperature_c" class="form-control" step="0.1" placeholder="e.g. 37.2">
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">Lochia</label><select name="lochia" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="normal">Normal</option>
                                    <option value="offensive">Offensive</option>
                                    <option value="heavy">Heavy</option>
                                    <option value="absent">Absent</option>
                                </select></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Uterus Assessment</label><input type="text" name="uterus_assessment" class="form-control" placeholder="e.g. well contracted"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Wound Assessment</label><input type="text" name="wound_assessment" class="form-control" placeholder="e.g. healing well, clean"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Breast Assessment</label><input type="text" name="breast_assessment" class="form-control" placeholder="e.g. soft, engorged"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-emoticon-outline"></i> Emotional Wellbeing</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Wellbeing Status</label><select name="emotional_wellbeing" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="good">Good</option>
                                    <option value="mild_concern">Mild Concern</option>
                                    <option value="moderate_concern">Moderate Concern</option>
                                    <option value="severe_concern">Severe Concern</option>
                                </select></div>
                            <div class="col-md-8 mb-3"><label class="form-label">Emotional Notes</label><input type="text" name="emotional_notes" class="form-control" placeholder="e.g. signs of postpartum blues"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-baby-face-outline"></i> Baby Assessment</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Baby Gen. Condition</label><input type="text" name="baby_general_condition" class="form-control" placeholder="e.g. active"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Baby Weight (kg)</label><input type="number" name="baby_weight_kg" class="form-control" step="0.01" placeholder="e.g. 3.20"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Baby Feeding</label><select name="baby_feeding" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="exclusive_breastfeeding">Exclusive breastfeeding</option>
                                    <option value="formula">Formula</option>
                                    <option value="mixed">Mixed</option>
                                </select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">BF Support Needed?</label><select name="breastfeeding_support" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Cord Status</label><select name="cord_status" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="clean">Clean</option>
                                    <option value="infected">Infected</option>
                                    <option value="separated">Separated</option>
                                </select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Jaundice</label><select name="jaundice" class="form-select">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Baby Notes</label><input type="text" name="baby_notes" class="form-control" placeholder="e.g. cord healing well"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-human-male-female"></i> Family Planning</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">FP Counselled</label><select name="family_planning_counselled" class="form-select">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select></div>
                            <div class="col-md-8 mb-3"><label class="form-label">FP Method Chosen</label><input type="text" name="family_planning_method" class="form-control" placeholder="e.g. Implants, Depo-Provera, None"></div>
                        </div>
                    </div>
                    <div class="mat-form-section p-3 bg-light rounded border-start border-4 border-info mt-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="mdi mdi-clipboard-text fs-4 text-info me-2"></i>
                            <h5 class="mb-0 text-dark fw-bold">Encounter Notes (Global Sync)</h5>
                        </div>
                        <div class="alert alert-warning py-2 px-3 small mb-3">
                            <i class="mdi mdi-information-outline me-1"></i> <b>Critical Documentation:</b> The notes entered below will be permanently synced to the patient's global encounter timeline.
                        </div>
                        <div id="mat-postnatal-notes-editor-modal"></div>
                        <div class="mt-2 text-muted small"><i class="mdi mdi-lightbulb-on-outline text-warning"></i> <b>Hint:</b> Document findings, concerns, counselling given, and plan of care.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-info text-white" id="btn-save-postnatal"><i class="mdi mdi-check"></i> Save Visit</button>
            </div>
        </div>
    </div>
</div>

{{-- 6. ANC Visit Modal --}}
<div class="modal fade" id="ancVisitModal" tabindex="-1" aria-labelledby="ancVisitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: var(--maternity-pink);">
                <h5 class="modal-title" id="ancVisitModalLabel"><i class="mdi mdi-stethoscope"></i> Record ANC Visit</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i>
                    <div>Record the antenatal care visit details. <strong>Vital signs</strong> and <strong>obstetric examination findings</strong> are grouped separately. Fields marked <span class="text-danger">*</span> are required.</div>
                </div>
                <form id="anc-visit-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-calendar-clock"></i> Visit Information</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Visit Date <span class="text-danger">*</span></label><input type="date" name="visit_date" class="form-control" required></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Gestational Age (weeks) <span class="text-danger">*</span></label><input type="number" name="gestational_age_weeks" class="form-control" min="1" max="45" placeholder="e.g. 28" required>
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Current gestational age calculated from LMP</div>
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">Visit Type</label><select name="visit_type" class="form-select">
                                    <option value="">Auto-detect</option>
                                    <option value="booking">Booking</option>
                                    <option value="routine">Routine</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="specialist_referral">Specialist Referral</option>
                                </select>
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Leave blank for auto-detection (booking/routine)</div>
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">Next Appointment</label><input type="date" name="next_appointment" class="form-control">
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Schedule the next ANC visit date</div>
                            </div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-heart-pulse"></i> Maternal Vitals</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Weight (kg)</label><input type="number" name="weight_kg" class="form-control" step="0.1" placeholder="e.g. 68.5">
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Monitor weight gain trend each visit</div>
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">BP Systolic <span class="mat-tooltip-icon" title="Top number of blood pressure. Normal: 90–139 mmHg"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="blood_pressure_systolic" class="form-control" min="50" max="250" placeholder="e.g. 120"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">BP Diastolic <span class="mat-tooltip-icon" title="Bottom number. Normal: 60–89 mmHg. ≥90 may indicate pre-eclampsia"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="blood_pressure_diastolic" class="form-control" min="30" max="150" placeholder="e.g. 80"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Haemoglobin (g/dL) <span class="mat-tooltip-icon" title="Normal in pregnancy: 10–14 g/dL. <10 = anaemia"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="haemoglobin" class="form-control" step="0.1" placeholder="e.g. 11.5"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-human-pregnant"></i> Obstetric Examination</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Fundal Height (cm) <span class="mat-tooltip-icon" title="Symphysis-fundal height — roughly equals gestational age in weeks (±2cm)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="fundal_height_cm" class="form-control" step="0.1" placeholder="e.g. 28"></div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fetal Heart Rate (bpm) <span class="mat-tooltip-icon" title="Normal FHR: 110–160 bpm. Can also record Nil, +, ++"><i class="mdi mdi-help-circle"></i></span></label>
                                <input type="text" name="fetal_heart_rate" class="form-control" placeholder="e.g. 140, Nil, +, ++" list="fhr-options">
                                <datalist id="fhr-options">
                                    <option value="Nil">Nil</option>
                                    <option value="+">+</option>
                                    <option value="++">++</option>
                                </datalist>
                            </div>
                            <div class="col-md-3 mb-3"><label class="form-label">Presentation <span class="mat-tooltip-icon" title="Cephalic: head-first (normal). Breech: buttocks/feet first. Transverse: sideways"><i class="mdi mdi-help-circle"></i></span></label><select name="presentation" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option>Cephalic</option>
                                    <option>Breech</option>
                                    <option>Transverse</option>
                                    <option>Oblique</option>
                                    <option>Palpable</option>
                                </select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Oedema <span class="mat-tooltip-icon" title="+: pedal only. ++: lower legs. +++: generalized/facial (pre-eclampsia warning)"><i class="mdi mdi-help-circle"></i></span></label><select name="oedema" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option>None</option>
                                    <option>+</option>
                                    <option>++</option>
                                    <option>+++</option>
                                </select></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Foetal Movement <span class="mat-tooltip-icon" title="Absent/reduced movement may indicate fetal distress"><i class="mdi mdi-help-circle"></i></span></label><select name="foetal_movement" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="reduced">Reduced</option>
                                </select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Urine Protein <span class="mat-tooltip-icon" title="≥++ with raised BP may indicate pre-eclampsia"><i class="mdi mdi-help-circle"></i></span></label><select name="urine_protein" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="nil">Nil</option>
                                    <option value="trace">Trace</option>
                                    <option value="+">+</option>
                                    <option value="++">++</option>
                                    <option value="+++">+++</option>
                                </select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Urine Glucose <span class="mat-tooltip-icon" title="Persistent glycosuria warrants screening for gestational diabetes"><i class="mdi mdi-help-circle"></i></span></label><select name="urine_glucose" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="nil">Nil</option>
                                    <option value="trace">Trace</option>
                                    <option value="+">+</option>
                                    <option value="++">++</option>
                                    <option value="+++">+++</option>
                                </select></div>
                        </div>
                    </div>
                    <div class="mat-form-section p-3 bg-light rounded border-start border-4 border-info mt-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="mdi mdi-clipboard-text fs-4 text-info me-2"></i>
                            <h5 class="mb-0 text-dark fw-bold">Encounter Notes (Global Sync)</h5>
                        </div>
                        <div class="alert alert-warning py-2 px-3 small mb-3">
                            <i class="mdi mdi-information-outline me-1"></i> <b>Critical Documentation:</b> The notes entered below will be permanently synced to the patient's global encounter timeline.
                        </div>
                        <div id="mat-anc-notes-editor-modal"></div>
                        <div class="mt-2 text-muted small"><i class="mdi mdi-lightbulb-on-outline text-warning"></i> <b>Hint:</b> Document clinical findings, counselling given, concerns, and plan of care.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn text-white" style="background: var(--maternity-pink);" id="btn-save-anc-visit"><i class="mdi mdi-check"></i> Save Visit</button>
            </div>
        </div>
    </div>
</div>

{{-- 7. Register Baby Modal --}}
<div class="modal fade" id="registerBabyModal" tabindex="-1" aria-labelledby="registerBabyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="registerBabyModalLabel"><i class="mdi mdi-baby-face-outline"></i> Register Baby</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i> Record the newborn's identity, measurements, and immediate care provided at birth.</div>
                <form id="register-baby-form">
                    @csrf
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-account"></i> Identity</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Surname <span class="text-danger">*</span></label><input type="text" name="baby_surname" class="form-control" placeholder="Baby's surname" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" name="baby_firstname" class="form-control" placeholder="Baby's first name" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Sex <span class="text-danger">*</span></label><select name="sex" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="ambiguous">Ambiguous</option>
                                </select></div>
                            <div class="col-md-12 mb-0">
                                <div class="form-check form-switch border rounded p-2 px-4 bg-light">
                                    <input class="form-check-input" type="checkbox" name="is_still_birth" id="is_still_birth" value="1">
                                    <label class="form-check-label fw-bold text-danger" for="is_still_birth">
                                        <i class="mdi mdi-emoticon-dead"></i> Still Birth (Deceased at birth)
                                    </label>
                                    <div class="text-muted small ms-4">Checking this will automatically create a death record for clinical statistics.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-human-child"></i> Anthropometrics</div>
                        <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Normal birth weight: 2.5–4.0 kg. Normal length: 48–53 cm. Normal head circumference: 33–37 cm</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Birth Weight (kg) <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Normal: 2.5–4.0 kg. Low birth weight: <2.5 kg. Macrosomia:>4.0 kg"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="birth_weight_kg" class="form-control" step="0.01" min="0.3" max="8" placeholder="e.g. 3.20" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Length (cm) <span class="mat-tooltip-icon" title="Crown-to-heel length. Normal: 48–53 cm at term"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="length_cm" class="form-control" step="0.1" placeholder="e.g. 50.0"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Head Circumference (cm) <span class="mat-tooltip-icon" title="Occipitofrontal circumference. Normal: 33–37 cm"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="head_circumference_cm" class="form-control" step="0.1" placeholder="e.g. 35.0"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-heart-pulse"></i> APGAR Scores</div>
                        <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Score 0–10: <strong>A</strong>ppearance, <strong>P</strong>ulse, <strong>G</strong>rimace, <strong>A</strong>ctivity, <strong>R</strong>espiration. 7–10: Normal. 4–6: Needs assistance. 0–3: Critical</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">APGAR at 1 min <span class="mat-tooltip-icon" title="First assessment at 1 minute after birth"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="apgar_1_min" class="form-control" min="0" max="10" placeholder="0–10"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">APGAR at 5 min <span class="mat-tooltip-icon" title="Second assessment at 5 minutes — most predictive of outcome"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="apgar_5_min" class="form-control" min="0" max="10" placeholder="0–10"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">APGAR at 10 min <span class="mat-tooltip-icon" title="Third assessment at 10 minutes (if earlier scores low)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="apgar_10_min" class="form-control" min="0" max="10" placeholder="0–10"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-medical-bag"></i> Immediate Care</div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Feeding Method <span class="mat-tooltip-icon" title="WHO recommends exclusive breastfeeding for the first 6 months"><i class="mdi mdi-help-circle"></i></span></label><select name="feeding_method" class="form-select">
                                    <option value="exclusive_breastfeeding">Exclusive Breastfeeding</option>
                                    <option value="formula">Formula</option>
                                    <option value="mixed">Mixed</option>
                                </select></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Immediate Immunizations & Prophylaxis <span class="mat-tooltip-icon" title="Standard birth-dose immunizations per national schedule"><i class="mdi mdi-help-circle"></i></span></label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="bcg_given" value="1"><label class="form-check-label">BCG</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="opv0_given" value="1"><label class="form-check-label">OPV-0</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="hbv0_given" value="1"><label class="form-check-label">HBV-0</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="vitamin_k_given" value="1"><label class="form-check-label">Vitamin K</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="eye_prophylaxis" value="1"><label class="form-check-label">Eye Prophylaxis</label></div>
                                </div>
                                <div class="mat-form-help mt-1"><i class="mdi mdi-information"></i> BCG: tuberculosis. OPV-0: polio. HBV-0: hepatitis B. Vitamin K: prevents haemorrhagic disease. Eye prophylaxis: prevents ophthalmia neonatorum</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-success" id="btn-save-baby"><i class="mdi mdi-check"></i> Register Baby</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.admit_discharge_modal')
@include('admin.partials.patient-form-modal')

@include('admin.partials.re-prescribe-encounter-modal')
@include('admin.partials.clinical_context_modal')
@include('admin.partials.treatment-plan-viewer-modal')
@include('admin.partials.invest_res_modal', ['save_route' => 'lab.saveResult'])
@include('admin.partials.invest_res_view_imaging_modal')
@include('admin.partials.invest_res_view_imaging_js')
@include('admin.partials.invest_res_view_modal')
@include('admin.partials.invest_res_view_js')
@include('admin.partials.ward_dashboard')

<!-- Discharge Maternity Enrollment Modal -->
<div class="modal fade" id="dischargeModal" tabindex="-1" aria-labelledby="dischargeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="dischargeModalLabel"><i class="mdi mdi-exit-run"></i> Discharge Maternity Enrollment</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="discharge-warnings-container" style="display:none;" class="mb-3"></div>
                <div id="discharge-status-info" class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="mdi mdi-account me-2"></i>
                        <strong id="discharge-patient-name"></strong>
                        <span id="discharge-current-status" class="ms-2"></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Outcome Summary <span class="text-danger">*</span></label>
                    <textarea id="discharge-outcome-summary" class="form-control" rows="3" placeholder="Brief summary of maternity outcome (min 5 characters)..." required></textarea>
                    <div class="form-text">Describe the overall outcome of this maternity episode (e.g., "Normal vaginal delivery, mother and baby well")</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-discharge" disabled>
                    <i class="mdi mdi-exit-run"></i> Discharge Patient
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mark Baby Deceased Modal -->
<div class="modal fade" id="markBabyDeceasedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-account-remove"></i> Mark Baby as Deceased</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="mark-baby-deceased-form">
                    @csrf
                    <input type="hidden" name="baby_id" id="deceased-baby-id">
                    <div class="mb-3">
                        <label class="form-label">Date of Death <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="deceased_at" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cause of Death <span class="text-danger">*</span></label>
                        <textarea name="cause_of_death" class="form-control" rows="3" placeholder="Enter cause of death..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-baby-death"><i class="mdi mdi-check"></i> Confirm</button>
            </div>
        </div>
    </div>
</div>

@endsection

