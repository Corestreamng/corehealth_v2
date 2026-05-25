{{--
    Hierarchical Breadcrumb for Internal Audit Module
    Usage: @include('admin.audit.partials.breadcrumb', ['items' => [['label' => 'Reports', 'url' => route('...')], ...]])
--}}

@php
    $defaultItems = [
        ['label' => 'Internal Audit', 'url' => route('audit.workbench'), 'icon' => 'mdi-shield-check'],
    ];

    $breadcrumbItems = array_merge($defaultItems, $items ?? []);
@endphp

<style>
    .audit-breadcrumb {
        background: linear-gradient(135deg, #4f46e5 0%, #312e81 100%);
        padding: 1.25rem 0;
        margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
    }
    .breadcrumb-custom-audit {
        background: transparent;
        margin-bottom: 0;
        padding: 0;
    }
    .breadcrumb-custom-audit .breadcrumb-item {
        font-size: 0.95rem;
    }
    .breadcrumb-custom-audit .breadcrumb-item a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 500;
    }
    .breadcrumb-custom-audit .breadcrumb-item a:hover {
        color: white;
        transform: translateX(2px);
    }
    .breadcrumb-custom-audit .breadcrumb-item.active {
        color: white;
        font-weight: 600;
    }
    .breadcrumb-custom-audit .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: rgba(255, 255, 255, 0.6);
        font-size: 1.3rem;
        padding-right: 0.5rem;
        vertical-align: middle;
    }
    .breadcrumb-custom-audit i {
        font-size: 1.1rem;
    }
</style>

<div class="audit-breadcrumb">
    <div class="container-fluid">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-custom-audit">
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
