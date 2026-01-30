{{--
    Hierarchical Breadcrumb for Accounting Module
    Usage: @include('accounting.partials.breadcrumb', ['items' => [['label' => 'Reports', 'url' => route('...')], ...]])
--}}

@php
    $defaultItems = [
        ['label' => 'Accounting', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-calculator'],
    ];

    $breadcrumbItems = array_merge($defaultItems, $items ?? []);
@endphp

<style>
    .accounting-breadcrumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 1rem 0;
        margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.15);
    }
    .breadcrumb-custom {
        background: transparent;
        margin-bottom: 0;
        padding: 0;
    }
    .breadcrumb-custom .breadcrumb-item {
        font-size: 0.9rem;
    }
    .breadcrumb-custom .breadcrumb-item a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .breadcrumb-custom .breadcrumb-item a:hover {
        color: white;
        transform: translateX(2px);
    }
    .breadcrumb-custom .breadcrumb-item.active {
        color: white;
        font-weight: 600;
    }
    .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: rgba(255, 255, 255, 0.6);
        font-size: 1.2rem;
        padding-right: 0.5rem;
    }
    .breadcrumb-custom i {
        font-size: 1rem;
    }
</style>

<div class="accounting-breadcrumb">
    <div class="container-fluid">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-custom">
                @foreach($breadcrumbItems as $index => $item)
                    @if($loop->last)
                        <li class="breadcrumb-item active" aria-current="page">
                            @if(isset($item['icon']))
                                <i class="mdi {{ $item['icon'] }} mr-1"></i>
                            @endif
                            {{ $item['label'] }}
                        </li>
                    @else
                        <li class="breadcrumb-item">
                            <a href="{{ $item['url'] }}">
                                @if(isset($item['icon']))
                                    <i class="mdi {{ $item['icon'] }}"></i>
                                @endif
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ol>
        </nav>
    </div>
</div>
