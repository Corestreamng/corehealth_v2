@props(['user' => null, 'width' => '40px', 'height' => '40px', 'class' => ''])

@php
    $user = $user ?? Auth::user();
    $initials = '';
    if ($user) {
        $initials = strtoupper(substr($user->firstname ?? '', 0, 1) . substr($user->surname ?? '', 0, 1));
    }

    // Generate a consistent background color based on the user's ID or name
    $colors = ['#007bff', '#6610f2', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#17a2b8'];
    $colorIndex = ($user->id ?? 0) % count($colors);
    $bgColor = $colors[$colorIndex];
@endphp

@if($user && !empty($user->filename) && file_exists(public_path('storage/image/user/'.$user->filename)))
    <img src="{{ url('storage/image/user/'.$user->filename) }}"
         alt="{{ $user->firstname }} {{ $user->surname }}"
         class="rounded-circle {{ $class }}"
         style="width: {{ $width }}; height: {{ $height }}; object-fit: cover;">
@else
    <div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold {{ $class }}"
         style="width: {{ $width }}; height: {{ $height }}; background-color: {{ $bgColor }}; font-size: calc({{ $width }} * 0.4);">
        {{ $initials }}
    </div>
@endif
