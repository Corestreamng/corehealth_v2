<div class="patient-search-widget-container" id="{{ $id ?? 'ps-widget' }}-container">
    @if(($showRecent ?? true))
    <div class="ps-recent-bar" id="{{ $id ?? 'ps-widget' }}-recent-bar" style="display: none; margin-bottom: 10px;">
        <span class="ps-recent-label" style="font-size: 0.85rem; color: #6c757d; margin-right: 10px;">Recent:</span>
        <div class="ps-recent-chips" id="{{ $id ?? 'ps-widget' }}-recent-chips" style="display: inline-flex; gap: 8px; flex-wrap: wrap;"></div>
    </div>
    @endif
    
    <div class="ps-search-wrapper" style="position: relative;">
        <i class="mdi mdi-magnify ps-search-icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 1.2rem;"></i>
        <input type="text" 
            class="form-control ps-search-input" 
            id="{{ $id ?? 'ps-widget' }}-input" 
            placeholder="{{ $placeholder ?? 'Search patient by file no or name...' }}" 
            autocomplete="off"
            data-route="{{ $searchRoute ?? '' }}"
            data-callback="{{ $onSelectCallback ?? '' }}"
            data-context="{{ $context ?? '' }}"
            style="padding-left: 40px; border-radius: 20px;"
        >
        <button type="button" class="ps-search-clear" id="{{ $id ?? 'ps-widget' }}-clear" style="display:none; position:absolute; right:12px; top:50%; transform:translateY(-50%); background:rgba(0,0,0,.1); border:none; border-radius:50%; width:22px; height:22px; line-height:1; padding:0; cursor:pointer; color:#333; font-size:14px;" aria-label="Clear search">&times;</button>
        <div class="ps-search-dropdown shadow-sm" id="{{ $id ?? 'ps-widget' }}-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #dee2e6; border-radius: 0.5rem; max-height: 400px; overflow-y: auto; z-index: 1050; margin-top: 5px;"></div>
    </div>
</div>
