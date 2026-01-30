@extends('admin.layouts.app')

@section('title', 'Search Results')

@section('style')
<style>
    .search-page-wrapper {
        max-width: 900px;
        margin: 0 auto;
        padding: 1.5rem;
    }

    .search-page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2.5rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }

    .search-page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        pointer-events: none;
    }

    .search-page-header h4 {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .search-page-header .subtitle {
        opacity: 0.85;
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
    }

    .search-form-wrapper {
        position: relative;
    }

    .search-input-large {
        background: rgba(255,255,255,0.95);
        border: none;
        color: #1f2937;
        font-size: 1.1rem;
        padding: 1.1rem 1.5rem;
        padding-right: 130px;
        border-radius: 14px;
        width: 100%;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }

    .search-input-large::placeholder {
        color: #9ca3af;
    }

    .search-input-large:focus {
        outline: none;
        box-shadow: 0 4px 25px rgba(0,0,0,0.2), 0 0 0 4px rgba(255,255,255,0.3);
        transform: translateY(-1px);
    }

    .search-btn {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }

    .search-btn:hover {
        transform: translateY(-50%) scale(1.02);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .search-tips {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .search-tip {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        background: rgba(255,255,255,0.15);
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
    }

    .results-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--border-color, #e5e7eb);
    }

    .results-count {
        font-size: 1rem;
        color: var(--text-secondary, #6b7280);
    }

    .results-count strong {
        color: var(--text-primary, #1f2937);
        font-weight: 700;
    }

    .results-count .query-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 600;
    }

    .section-group {
        margin-bottom: 2rem;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-secondary, #6b7280);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 1rem;
        padding: 0.5rem 0;
    }

    .section-header::before {
        content: '';
        width: 4px;
        height: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 2px;
    }

    .section-header .section-count {
        background: var(--border-color, #e5e7eb);
        padding: 0.2rem 0.6rem;
        border-radius: 10px;
        font-size: 0.7rem;
        margin-left: auto;
    }

    .search-result-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 0.75rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        display: block;
        color: inherit;
        position: relative;
        overflow: hidden;
    }

    .search-result-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: transparent;
        transition: background 0.25s ease;
    }

    .search-result-card:hover {
        border-color: #667eea;
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.15);
        transform: translateY(-3px) translateX(2px);
        text-decoration: none;
        color: inherit;
    }

    .search-result-card:hover::before {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .result-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        transition: all 0.25s ease;
    }

    .search-result-card:hover .result-icon {
        transform: scale(1.08) rotate(-3deg);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .result-content {
        flex-grow: 1;
        min-width: 0;
    }

    .result-title {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--text-primary, #1f2937);
        margin-bottom: 0.35rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .result-badge {
        font-size: 0.65rem;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        font-weight: 600;
        text-transform: uppercase;
    }

    .result-hierarchy {
        font-size: 0.8rem;
        color: #667eea;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex-wrap: wrap;
    }

    .hierarchy-separator {
        color: #d1d5db;
        margin: 0 0.25rem;
    }

    .result-description {
        font-size: 0.9rem;
        color: var(--text-secondary, #6b7280);
        line-height: 1.55;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .result-arrow {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--border-color, #f3f4f6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 1.25rem;
        flex-shrink: 0;
        transition: all 0.25s ease;
    }

    .search-result-card:hover .result-arrow {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateX(3px);
    }

    .no-results {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary, #6b7280);
    }

    .no-results-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
    }

    .no-results-icon i {
        font-size: 2.5rem;
        background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .no-results h5 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary, #1f2937);
        margin-bottom: 0.5rem;
    }

    .no-results p {
        max-width: 300px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .quick-links {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--border-color, #e5e7eb);
    }

    .quick-links-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-secondary, #6b7280);
        margin-bottom: 1rem;
        text-align: center;
    }

    .quick-links-list {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
    }

    .quick-link {
        padding: 0.5rem 1rem;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 20px;
        font-size: 0.85rem;
        color: var(--text-secondary, #6b7280);
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .quick-link:hover {
        border-color: #667eea;
        color: #667eea;
        text-decoration: none;
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .search-result-card {
        animation: fadeInUp 0.4s ease forwards;
    }

    .search-result-card:nth-child(1) { animation-delay: 0.05s; }
    .search-result-card:nth-child(2) { animation-delay: 0.1s; }
    .search-result-card:nth-child(3) { animation-delay: 0.15s; }
    .search-result-card:nth-child(4) { animation-delay: 0.2s; }
    .search-result-card:nth-child(5) { animation-delay: 0.25s; }

    /* Pagination improvements */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color, #e5e7eb);
    }

    .pagination-wrapper .pagination {
        gap: 0.25rem;
    }

    .pagination-wrapper .page-link {
        border-radius: 10px;
        border: none;
        padding: 0.6rem 1rem;
        font-weight: 500;
    }

    .pagination-wrapper .page-item.active .page-link {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
</style>
@endsection

@section('content')
<div class="search-page-wrapper">
    <!-- Search Header -->
    <div class="search-page-header">
        <h4>
            <i class="mdi mdi-magnify"></i>
            Search Navigation
        </h4>
        <p class="subtitle">Find any page, feature, or module in the system</p>

        <form action="{{ route('search.index') }}" method="GET" class="search-form-wrapper">
            <input type="text"
                   name="q"
                   class="search-input-large"
                   placeholder="Type to search..."
                   value="{{ $query }}"
                   autofocus>
            <button type="submit" class="search-btn">
                <i class="mdi mdi-magnify"></i>
                Search
            </button>
        </form>

        <div class="search-tips">
            <span class="search-tip">
                <i class="mdi mdi-keyboard"></i>
                Press <kbd style="background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:4px;margin:0 2px;">Ctrl+K</kbd> anywhere
            </span>
            <span class="search-tip">
                <i class="mdi mdi-lightbulb-outline"></i>
                Try: patient, billing, pharmacy
            </span>
        </div>
    </div>

    <!-- Results -->
    @if(strlen($query) >= 2)
        @if($routes->count() > 0)
            <div class="results-header">
                <div class="results-count">
                    Found <strong>{{ $routes->total() }}</strong> result{{ $routes->total() > 1 ? 's' : '' }}
                    for "<span class="query-text">{{ $query }}</span>"
                </div>
            </div>

            @php
                $groupedRoutes = $routes->groupBy('section');
            @endphp

            @foreach($groupedRoutes as $section => $sectionRoutes)
                <div class="section-group">
                    <div class="section-header">
                        {{ $section }}
                        <span class="section-count">{{ $sectionRoutes->count() }}</span>
                    </div>

                    @foreach($sectionRoutes as $route)
                        <a href="{{ $route->url }}" class="search-result-card">
                            <div class="d-flex gap-3 align-items-center">
                                <div class="result-icon">
                                    <i class="mdi {{ $route->icon ?? 'mdi-link' }}"></i>
                                </div>
                                <div class="result-content">
                                    <div class="result-title">
                                        {{ $route->title }}
                                        @if($route->parent_section)
                                            <span class="result-badge">{{ $route->parent_section }}</span>
                                        @endif
                                    </div>
                                    <div class="result-hierarchy">
                                        @foreach(explode(' > ', $route->hierarchy_path) as $index => $part)
                                            @if($index > 0)
                                                <span class="hierarchy-separator"><i class="mdi mdi-chevron-right" style="font-size:0.7rem;"></i></span>
                                            @endif
                                            <span>{{ $part }}</span>
                                        @endforeach
                                    </div>
                                    <div class="result-description">{{ $route->description }}</div>
                                </div>
                                <div class="result-arrow">
                                    <i class="mdi mdi-arrow-right"></i>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endforeach

            <!-- Pagination -->
            @if($routes->hasPages())
                <div class="pagination-wrapper">
                    {{ $routes->appends(['q' => $query])->links() }}
                </div>
            @endif
        @else
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="mdi mdi-file-search-outline"></i>
                </div>
                <h5>No results found</h5>
                <p>We couldn't find any pages matching "<strong>{{ $query }}</strong>". Try different keywords.</p>

                <div class="quick-links">
                    <div class="quick-links-title">Popular searches</div>
                    <div class="quick-links-list">
                        <a href="{{ route('search.index', ['q' => 'patient']) }}" class="quick-link">Patient</a>
                        <a href="{{ route('search.index', ['q' => 'billing']) }}" class="quick-link">Billing</a>
                        <a href="{{ route('search.index', ['q' => 'pharmacy']) }}" class="quick-link">Pharmacy</a>
                        <a href="{{ route('search.index', ['q' => 'lab']) }}" class="quick-link">Laboratory</a>
                        <a href="{{ route('search.index', ['q' => 'inventory']) }}" class="quick-link">Inventory</a>
                        <a href="{{ route('search.index', ['q' => 'reports']) }}" class="quick-link">Reports</a>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="no-results">
            <div class="no-results-icon">
                <i class="mdi mdi-text-box-search-outline"></i>
            </div>
            <h5>Start Searching</h5>
            <p>Enter at least 2 characters to search for pages, features, and modules.</p>

            <div class="quick-links">
                <div class="quick-links-title">Popular searches</div>
                <div class="quick-links-list">
                    <a href="{{ route('search.index', ['q' => 'patient']) }}" class="quick-link">Patient</a>
                    <a href="{{ route('search.index', ['q' => 'billing']) }}" class="quick-link">Billing</a>
                    <a href="{{ route('search.index', ['q' => 'pharmacy']) }}" class="quick-link">Pharmacy</a>
                    <a href="{{ route('search.index', ['q' => 'lab']) }}" class="quick-link">Laboratory</a>
                    <a href="{{ route('search.index', ['q' => 'inventory']) }}" class="quick-link">Inventory</a>
                    <a href="{{ route('search.index', ['q' => 'reports']) }}" class="quick-link">Reports</a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
