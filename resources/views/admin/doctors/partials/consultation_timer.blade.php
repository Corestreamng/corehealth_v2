{{-- Consultation Timer Component --}}
{{-- Included in new_encounter.blade.php action bar --}}
{{-- Requires: $queueId to be available in the parent view --}}

<style>
    .consultation-timer-widget {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 8px 16px;
        min-width: 200px;
    }
    .consultation-timer-widget.timer-paused {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-color: #ffc107;
    }
    .timer-display {
        font-family: 'Courier New', Courier, monospace;
        font-size: 1.4rem;
        font-weight: bold;
        letter-spacing: 2px;
    }
    .timer-display.timer-paused-text {
        animation: timerPulse 1.5s ease-in-out infinite;
    }
    @keyframes timerPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }
    .timer-meta {
        font-size: 0.7rem;
        color: #6c757d;
    }
    .timer-status-label {
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
</style>

<div id="consultation-timer-container" class="consultation-timer-widget d-none">
    <div class="d-flex align-items-center gap-2">
        <div>
            <div class="d-flex align-items-center gap-1 mb-1">
                <i class="mdi mdi-timer-outline text-primary" style="font-size: 1.1rem;"></i>
                <span class="timer-status-label text-primary" id="timer-status-label">RUNNING</span>
            </div>
            <div class="timer-display text-dark" id="timer-display">00:00:00</div>
            <div class="timer-meta">
                <span id="timer-started-at"></span>
                <span id="timer-pause-info" class="d-none"> | Paused: <span id="timer-total-paused">0m</span></span>
            </div>
        </div>
        <div class="d-flex flex-column gap-1 ms-2">
            <button type="button" class="btn btn-outline-warning btn-sm" id="timer-pause-btn" onclick="toggleTimerPause()" title="Pause consultation">
                <i class="mdi mdi-pause"></i>
            </button>
        </div>
    </div>
</div>

<script>
    // ─── Consultation Timer Class ──────────────────────────────────────
    var ConsultationTimer = (function() {
        var _startedAt = null;
        var _serverElapsed = 0;
        var _syncTime = null;
        var _isPaused = false;
        var _tickInterval = null;
        var _queueId = null;
        var _initialized = false;

        function formatTime(seconds) {
            var h = Math.floor(seconds / 3600);
            var m = Math.floor((seconds % 3600) / 60);
            var s = seconds % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function formatMinutes(seconds) {
            if (seconds < 60) return seconds + 's';
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return m + 'm' + (s > 0 ? ' ' + s + 's' : '');
        }

        function getElapsedSeconds() {
            if (!_syncTime) return 0;
            var elapsed = _serverElapsed;
            if (!_isPaused) {
                elapsed += Math.floor((new Date() - _syncTime) / 1000);
            }
            return Math.max(0, elapsed);
        }

        function updateUI() {
            var elapsed = getElapsedSeconds();
            $('#timer-display').text(formatTime(elapsed));

            if (_isPaused) {
                $('#consultation-timer-container').addClass('timer-paused');
                $('#timer-display').addClass('timer-paused-text');
                $('#timer-status-label').text('PAUSED').removeClass('text-primary').addClass('text-warning');
                $('#timer-pause-btn').html('<i class="mdi mdi-play"></i>').removeClass('btn-outline-warning').addClass('btn-outline-success');
                $('#timer-pause-btn').attr('title', 'Resume consultation');
            } else {
                $('#consultation-timer-container').removeClass('timer-paused');
                $('#timer-display').removeClass('timer-paused-text');
                $('#timer-status-label').text('RUNNING').removeClass('text-warning').addClass('text-primary');
                $('#timer-pause-btn').html('<i class="mdi mdi-pause"></i>').removeClass('btn-outline-success').addClass('btn-outline-warning');
                $('#timer-pause-btn').attr('title', 'Pause consultation');
            }

            // Show pause info if any pause time accumulated
            var totalPaused = 0;
            if (_startedAt) {
                var wallTime = Math.floor((new Date() - _startedAt) / 1000);
                totalPaused = Math.max(0, wallTime - getElapsedSeconds());
            }
            if (totalPaused > 0) {
                $('#timer-pause-info').removeClass('d-none');
                $('#timer-total-paused').text(formatMinutes(totalPaused));
            }
        }

        function startTicking() {
            if (_tickInterval) clearInterval(_tickInterval);
            _tickInterval = setInterval(function() {
                updateUI();
            }, 1000);
        }

        function syncFromServer(timer) {
            _serverElapsed = parseInt(timer.elapsed_seconds) || 0;
            _syncTime = new Date();
            _isPaused = timer.is_paused === true || timer.is_paused == 1;
            if (timer.started_at) {
                _startedAt = new Date(timer.started_at);
            }
        }

        return {
            init: function(queueId) {
                _queueId = queueId;
                if (!_queueId || _queueId === 'ward_round') {
                    // No queue context (ward round) — don't show timer
                    return;
                }

                // Fetch current timer status from server
                $.ajax({
                    url: "{{ route('queue.timer.status', ['queue' => '__QID__']) }}".replace('__QID__', _queueId),
                    type: 'GET',
                    success: function(data) {
                        if (data.timer && data.timer.started_at) {
                            syncFromServer(data.timer);
                            _initialized = true;

                            $('#timer-started-at').text('Started: ' + _startedAt.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}));
                            $('#consultation-timer-container').removeClass('d-none');

                            updateUI();
                            startTicking();
                        } else {
                            // Timer not started yet — start it now (encounter was just opened)
                            ConsultationTimer.startOnServer();
                        }
                    },
                    error: function() {
                        // Silently fail — timer just won't show
                    }
                });
            },

            startOnServer: function() {
                if (!_queueId || _queueId === 'ward_round') return;

                $.ajax({
                    url: "{{ route('queue.timer.start', ['queue' => '__QID__']) }}".replace('__QID__', _queueId),
                    type: 'POST',
                    data: { _token: $('meta[name="csrf-token"]').attr('content') },
                    success: function(data) {
                        if (data.timer && data.timer.started_at) {
                            syncFromServer(data.timer);
                            _initialized = true;

                            $('#timer-started-at').text('Started: ' + _startedAt.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}));
                            $('#consultation-timer-container').removeClass('d-none');

                            updateUI();
                            startTicking();
                        }
                    }
                });
            },

            togglePause: function() {
                if (!_initialized || !_queueId) return;

                // If currently paused → resume (start endpoint), otherwise → pause
                var endpoint = _isPaused
                    ? "{{ route('queue.timer.start', ['queue' => '__QID__']) }}".replace('__QID__', _queueId)
                    : "{{ route('queue.timer.pause', ['queue' => '__QID__']) }}".replace('__QID__', _queueId);

                $.ajax({
                    url: endpoint,
                    type: 'POST',
                    data: { _token: $('meta[name="csrf-token"]').attr('content') },
                    success: function(data) {
                        if (data.timer) {
                            syncFromServer(data.timer);
                        }
                        updateUI();
                    },
                    error: function(xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Timer action failed.';
                        toastr.error(msg);
                    }
                });
            },

            getElapsed: function() {
                return getElapsedSeconds();
            },

            isRunning: function() {
                return _initialized && !_isPaused;
            },

            sync: function(timerData) {
                syncFromServer(timerData);
                updateUI();
            }
        };
    })();

    // Global toggle function called from button
    function toggleTimerPause() {
        ConsultationTimer.togglePause();
    }

    // Timer sync every 60s to prevent drift
    setInterval(function() {
        if (typeof queueId !== 'undefined' && queueId && queueId !== 'ward_round') {
            $.ajax({
                url: "{{ route('queue.timer.status', ['queue' => '__QID__']) }}".replace('__QID__', queueId),
                type: 'GET',
                success: function(data) {
                    if (data.timer) {
                        ConsultationTimer.sync(data.timer);
                    }
                }
            });
        }
    }, 60000);
</script>
