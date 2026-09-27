/**
 * Medication Schedule Presets Module
 * CoreHealth v2
 * 
 * Provides clinical frequency presets (STAT, OD, BD, TID, QID, Q4H, Q6H, Q8H, PRN)
 * and smart anchor-based time slot calculation for medication charting.
 */
(function(window, $) {
    'use strict';

    const MedicationSchedulePresets = {
        currentPreset: 'OD',
        slots: ['08:00'],

        init: function() {
            this.bindEvents();
            this.renderSlots(['08:00']);
        },

        bindEvents: function() {
            const self = this;

            // Frequency preset pill clicked
            $(document).on('click', '.preset-pill-btn', function(e) {
                e.preventDefault();
                const preset = $(this).data('preset');
                self.setPreset(preset);
            });

            // Anchor time changed
            $(document).on('change', '#schedule_anchor_time', function() {
                const anchorTime = $(this).val();
                if (anchorTime) {
                    $('#schedule_time').val(anchorTime);
                    if (self.currentPreset !== 'CUSTOM') {
                        const calculated = self.calculateSlots(self.currentPreset, anchorTime);
                        self.renderSlots(calculated);
                    }
                }
            });

            // Add time slot button
            $(document).on('click', '#add-time-slot-btn', function(e) {
                e.preventDefault();
                self.addCustomSlot();
            });

            // Remove time slot button
            $(document).on('click', '.remove-time-slot', function(e) {
                e.preventDefault();
                $(this).closest('.time-slot-chip').remove();
                self.syncPrimaryTime();
                self.markCustomPreset();
            });

            // Changing an individual slot time marks preset as custom
            $(document).on('change', '.schedule-time-slot-input', function() {
                self.syncPrimaryTime();
                self.markCustomPreset();
            });

            // Duration preset pills
            $(document).on('click', '.duration-pill', function(e) {
                e.preventDefault();
                const days = $(this).data('days');
                $('.duration-pill').removeClass('active');
                $(this).addClass('active');
                $('#schedule_duration').val(days);
            });

            // Manual duration input syncs pill state
            $(document).on('input change', '#schedule_duration', function() {
                const val = parseInt($(this).val(), 10);
                $('.duration-pill').removeClass('active');
                $(`.duration-pill[data-days="${val}"]`).addClass('active');
            });

            // Modal reset on show
            $('#setScheduleModal').on('show.bs.modal', function() {
                // Default anchor time to current hour or 08:00
                let defaultAnchor = '08:00';
                try {
                    const now = new Date();
                    now.setMinutes(0, 0, 0);
                    now.setHours(now.getHours() + 1);
                    const hh = String(now.getHours() % 24).padStart(2, '0');
                    defaultAnchor = `${hh}:00`;
                } catch (err) {
                    defaultAnchor = '08:00';
                }

                $('#schedule_anchor_time').val(defaultAnchor);
                $('#schedule_time').val(defaultAnchor);
                self.setPreset('OD', defaultAnchor);
            });
        },

        setPreset: function(preset, customAnchor) {
            this.currentPreset = preset;
            $('#schedule_frequency').val(preset);

            $('.preset-pill-btn').removeClass('active');
            $(`.preset-pill-btn[data-preset="${preset}"]`).addClass('active');

            const anchor = customAnchor || $('#schedule_anchor_time').val() || '08:00';

            if (preset === 'STAT') {
                // STAT = 1 dose immediately, once
                $('#schedule_duration').val(1);
                $('.duration-pill').removeClass('active');
                $('.duration-pill[data-days="1"]').addClass('active');
                $('#repeat_selected_days').prop('checked', false);
                $('#repeat_daily').prop('checked', true);
                const statTimes = [this.getCurrentTimeString()];
                $('#schedule_anchor_time').val(statTimes[0]);
                this.renderSlots(statTimes);
            } else if (preset === 'CUSTOM') {
                // Keep existing slots as is
            } else {
                const times = this.calculateSlots(preset, anchor);
                this.renderSlots(times);
            }
        },

        calculateSlots: function(preset, anchorTime) {
            const [hStr, mStr] = (anchorTime || '08:00').split(':');
            const h = parseInt(hStr, 10) || 0;
            const m = parseInt(mStr, 10) || 0;

            const format = (hh, mm) => {
                const normalizedH = ((hh % 24) + 24) % 24;
                return `${String(normalizedH).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
            };

            switch (preset) {
                case 'STAT':
                    return [this.getCurrentTimeString()];

                case 'OD':
                    // Once daily
                    return [format(h, m)];

                case 'BD':
                    // Twice daily (every 12 hours)
                    return [
                        format(h, m),
                        format(h + 12, m)
                    ].sort();

                case 'TID':
                    // Three times daily (typically morning, afternoon, night: +6h, +12h or +8h, +16h)
                    return [
                        format(h, m),
                        format(h + 6, m),
                        format(h + 12, m)
                    ].sort();

                case 'QID':
                    // Four times daily (every 6 hours)
                    return [
                        format(h, m),
                        format(h + 6, m),
                        format(h + 12, m),
                        format(h + 18, m)
                    ].sort();

                case 'Q4H':
                    // Every 4 hours (6 doses)
                    return [
                        format(h, m),
                        format(h + 4, m),
                        format(h + 8, m),
                        format(h + 12, m),
                        format(h + 16, m),
                        format(h + 20, m)
                    ].sort();

                case 'Q6H':
                    // Every 6 hours (4 doses)
                    return [
                        format(h, m),
                        format(h + 6, m),
                        format(h + 12, m),
                        format(h + 18, m)
                    ].sort();

                case 'Q8H':
                    // Every 8 hours (3 doses)
                    return [
                        format(h, m),
                        format(h + 8, m),
                        format(h + 16, m)
                    ].sort();

                case 'PRN':
                    // As needed - default single anchor
                    return [format(h, m)];

                default:
                    return [format(h, m)];
            }
        },

        renderSlots: function(times) {
            this.slots = times && times.length > 0 ? times : ['08:00'];
            const container = $('#scheduled-time-slots-list');
            container.empty();

            this.slots.forEach(function(time, index) {
                const chip = $(`
                    <div class="time-slot-chip d-inline-flex align-items-center p-1 border rounded bg-light me-1 mb-1">
                        <input type="time" class="form-control form-control-sm schedule-time-slot-input border-0 bg-transparent px-1 py-0" 
                               name="times[]" value="${time}" style="width: 95px; font-weight: 600;">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1 remove-time-slot" title="Remove slot" style="line-height: 1;">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                `);
                container.append(chip);
            });

            this.syncPrimaryTime();
        },

        addCustomSlot: function() {
            const lastTime = $('.schedule-time-slot-input').last().val() || '08:00';
            const [hStr, mStr] = lastTime.split(':');
            const h = (parseInt(hStr, 10) + 4) % 24;
            const newTime = `${String(h).padStart(2, '0')}:${mStr || '00'}`;

            const chip = $(`
                <div class="time-slot-chip d-inline-flex align-items-center p-1 border rounded bg-light me-1 mb-1">
                    <input type="time" class="form-control form-control-sm schedule-time-slot-input border-0 bg-transparent px-1 py-0" 
                           name="times[]" value="${newTime}" style="width: 95px; font-weight: 600;">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1 remove-time-slot" title="Remove slot" style="line-height: 1;">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            `);
            $('#scheduled-time-slots-list').append(chip);
            this.markCustomPreset();
            this.syncPrimaryTime();
        },

        markCustomPreset: function() {
            this.currentPreset = 'CUSTOM';
            $('#schedule_frequency').val('CUSTOM');
            $('.preset-pill-btn').removeClass('active');
            $('.preset-pill-btn[data-preset="CUSTOM"]').addClass('active');
        },

        syncPrimaryTime: function() {
            const firstTime = $('.schedule-time-slot-input').first().val();
            if (firstTime) {
                $('#schedule_time').val(firstTime);
            }
        },

        getCurrentTimeString: function() {
            const d = new Date();
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            return `${hh}:${mm}`;
        },

        getTimes: function() {
            const times = [];
            $('.schedule-time-slot-input').each(function() {
                const val = $(this).val();
                if (val && !times.includes(val)) {
                    times.push(val);
                }
            });
            return times.length > 0 ? times : [$('#schedule_time').val() || '08:00'];
        }
    };

    window.MedicationSchedulePresets = MedicationSchedulePresets;

    $(document).ready(function() {
        if ($('#setScheduleModal').length > 0) {
            MedicationSchedulePresets.init();
        }
    });

})(window, jQuery);
