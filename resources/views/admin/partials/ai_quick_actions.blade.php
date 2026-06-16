{{-- AI Quick Actions Floating Action Button (FAB) --}}
@php
    $llmConfig = is_string(appsettings('llm_config')) ? json_decode(appsettings('llm_config'), true) : (is_array(appsettings('llm_config')) ? appsettings('llm_config') : []);
    $aiEnabled = $llmConfig['enabled'] ?? true;
    $hosColor = appsettings('hos_color') ?? '#0066cc';
@endphp

@if($aiEnabled)
<div id="ai-quick-actions-fab" class="ai-fab-container d-none">
    {{-- Patient Summary Button (Always visible) --}}
    <button type="button" class="btn btn-ai-fab btn-ai-summary btn-patient-summary" id="fab-summary-btn" title="Generate AI Patient Summary">
        <div class="ai-fab-icon-wrapper"><i class="fa fa-magic ai-fab-icon"></i></div>
        <span class="ai-fab-label">Patient Summary</span>
    </button>
</div>

<style>
.ai-fab-container {
    position: fixed;
    right: 30px;
    bottom: 140px; /* Elevated to avoid overlap with messages/shift control */
    display: flex;
    flex-direction: column;
    align-items: flex-end; /* Aligns buttons to the right so they expand leftwards */
    gap: 15px;
    z-index: 1050;
}

.ai-fab-container.visible {
    display: flex !important;
    animation: fabSlideIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

.btn-ai-fab {
    width: 55px; /* Starting circular width */
    height: 55px;
    border-radius: 50px; /* Pill shape */
    border: none;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 0;
    color: #fff;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(0,0,0,0.25);
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    position: relative;
    overflow: hidden;
    white-space: nowrap;
    animation: fabOccasionalBounce 6s infinite;
}

.btn-ai-fab::after {
    content: '';
    position: absolute;
    top: -5px; right: -5px; bottom: -5px; left: -5px;
    border-radius: 50px;
    background: inherit;
    z-index: -1;
    filter: blur(14px);
    opacity: 0.7;
    animation: fabGlow 2s infinite alternate ease-in-out;
}

.btn-ai-fab::before {
    content: '';
    position: absolute;
    top: 0; left: -100%; width: 50%; height: 100%;
    background: linear-gradient(to right, transparent, rgba(255,255,255,0.3), transparent);
    transform: skewX(-20deg);
    transition: none;
    animation: fabShine 4s infinite;
}

.ai-fab-icon-wrapper {
    width: 55px;
    height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ai-fab-icon {
    font-size: 1.5rem;
    line-height: 1;
}

.ai-fab-label {
    font-size: 1.05rem;
    font-weight: 600;
    margin-left: 14px;
    opacity: 0;
    transform: translateX(15px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    letter-spacing: 0.3px;
}

.btn-ai-summary {
    background: linear-gradient(135deg, {{ $hosColor }}, color-mix(in srgb, {{ $hosColor }} 70%, #3b82f6));
}

.btn-ai-fab:hover {
    width: 220px; /* Expanded width for text */
    transform: scale(1.03) translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.35);
    animation: none;
}

.btn-ai-fab:hover .ai-fab-label {
    opacity: 1;
    transform: translateX(0);
}

@keyframes fabSlideIn {
    from { opacity: 0; transform: translateX(50px) scale(0.8); }
    to { opacity: 1; transform: translateX(0) scale(1); }
}

@keyframes fabGlow {
    from { opacity: 0.3; filter: blur(8px); }
    to { opacity: 0.7; filter: blur(15px); }
}

@keyframes fabShine {
    0% { left: -100%; }
    20% { left: 200%; }
    100% { left: 200%; }
}

@keyframes fabOccasionalBounce {
    0%, 80%, 100% { transform: translateY(0); }
    85% { transform: translateY(-12px); }
    90% { transform: translateY(0); }
    95% { transform: translateY(-5px); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fabContainer = document.getElementById('ai-quick-actions-fab');
    const summaryBtn = document.getElementById('fab-summary-btn');
    
    // Make container visible after slight delay
    setTimeout(() => {
        fabContainer.classList.remove('d-none');
        fabContainer.classList.add('visible');
    }, 1000);

    // Handle Summary Click
    if (summaryBtn) {
        summaryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.patientSummary !== 'undefined') {
                window.patientSummary.openAndLoad();
            } else {
                console.warn("PatientSummaryManager is not initialized on this page.");
            }
        });
    }
});
</script>
@endif
