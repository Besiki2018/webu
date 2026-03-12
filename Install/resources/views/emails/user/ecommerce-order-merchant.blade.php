@extends('emails.layouts.base')

@section('content')
    <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 600; color: #18181b;">
        {{ $title }}
    </h2>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #3f3f46;">
        {{ $intro }}
    </p>

    @include('emails.partials.details-box', [
        'title' => __('Order Details'),
        'details' => $details,
        'primaryColor' => $primaryColor
    ])

    @if(!empty($actionUrl))
        @include('emails.partials.button', [
            'url' => $actionUrl,
            'text' => $actionText ?? __('Open Project CMS'),
            'primaryColor' => $primaryColor,
            'primaryForeground' => $primaryForeground
        ])
    @endif
@endsection

