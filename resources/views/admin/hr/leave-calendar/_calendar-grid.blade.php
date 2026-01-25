{{-- Shared Leave Calendar Grid Component --}}
{{-- Can be included in HR Leave Calendar, ESS My Calendar, and ESS Team Calendar --}}

@section('style')
@parent
<style>
    /* Leave Calendar Grid Styles */
    .leave-calendar-container {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .calendar-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0;
    }
    
    .calendar-nav-btn {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .calendar-nav-btn:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .calendar-weekday-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background-color: #4a5568;
    }
    
    .weekday-name {
        padding: 12px 8px;
        text-align: center;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: white;
    }
    
    .leave-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background-color: #dee2e6;
        border: 1px solid #dee2e6;
    }
    
    .calendar-day-cell {
        background-color: white;
        min-height: 120px;
        padding: 8px;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    
    .calendar-day-cell.empty-day {
        background-color: #f8f9fa;
        min-height: 60px;
    }
    
    .calendar-day-cell.today {
        background-color: #e3f2fd;
        border: 2px solid #2196F3;
    }
    
    .calendar-day-cell.weekend {
        background-color: #fffde7;
    }
    
    .calendar-day-cell.past-date {
        opacity: 0.7;
        background-color: #fafafa;
    }
    
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eee;
    }
    
    .day-name {
        font-size: 0.65rem;
        color: #888;
        text-transform: uppercase;
        font-weight: 500;
    }
    
    .day-number {
        font-weight: 700;
        font-size: 1rem;
        color: #333;
    }
    
    .today .day-number {
        background: #2196F3;
        color: white;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .leave-items-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 3px;
        overflow-y: auto;
        max-height: 150px;
    }
    
    .leave-item {
        font-size: 0.7rem;
        padding: 4px 6px;
        border-radius: 4px;
        cursor: pointer;
        border-left: 3px solid;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
    }
    
    .leave-item:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    
    .leave-item .staff-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 500;
    }
    
    .leave-item .leave-type-badge {
        font-size: 0.6rem;
        padding: 1px 4px;
        border-radius: 3px;
        background: rgba(255,255,255,0.8);
    }
    
    /* Status Modifiers */
    .leave-item.status-pending { opacity: 0.85; border-style: dashed; }
    .leave-item.status-supervisor_approved { border-left-width: 4px; }
    .leave-item.status-approved { }
    
    /* Stats Cards */
    .stat-card {
        border-radius: 12px;
        border: none;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    /* Legend */
    .legend-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        font-size: 0.75rem;
    }
    
    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 3px;
        margin-right: 6px;
        border-left: 3px solid;
    }
    
    /* On Leave List */
    .on-leave-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    /* More indicator */
    .more-leaves {
        font-size: 0.65rem;
        color: #666;
        text-align: center;
        padding: 2px;
        background: #f5f5f5;
        border-radius: 3px;
        cursor: pointer;
    }
    
    .more-leaves:hover {
        background: #e0e0e0;
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .calendar-day-cell {
            min-height: 90px;
            padding: 4px;
        }
        .leave-item {
            font-size: 0.6rem;
            padding: 2px 4px;
        }
        .day-number {
            font-size: 0.85rem;
        }
    }
    
    @media (max-width: 576px) {
        .calendar-day-cell {
            min-height: 70px;
        }
        .leave-item .leave-type-badge {
            display: none;
        }
    }
</style>
@endsection
