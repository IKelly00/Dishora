@extends('layouts/contentNavbarLayout')

@section('title', 'Feedback')

@section('content')
    <div class="container-fluid py-4">
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Feedback for
                    <span class="text-primary">
                        {{ optional(Auth::user()->vendor->businessDetails->firstWhere('business_id', session('active_business_id')))->business_name ?? 'Unknown Business' }}
                    </span>
                </h5>
            </div>

            <div class="card-body">
                @if ($reviews->count())
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Comment</th>
                                    <th>Rating</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reviews as $index => $review)
                                    <tr>
                                        <td>{{ $reviews->firstItem() + $index }}</td>
                                        <td>
                                            {{ $review->user->fullname ?? ($review->customer->user->fullname ?? 'Anonymous') }}
                                        </td>
                                        <td>{{ $review->comment ?: '-' }}</td>
                                        <td>
                                            @if ($review->rating)
                                                <span class="badge bg-warning text-dark">
                                                    {{ str_repeat('★', (int) $review->rating) }}
                                                </span>
                                            @else
                                                <span class="text-muted">No rating</span>
                                            @endif
                                        </td>
                                        <td>{{ $review->created_at->format('M d, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $reviews->links('pagination::bootstrap-5') }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="ri-chat-off-line display-4 text-muted mb-3"></i>
                        <h6 class="text-secondary">No feedback yet for this business.</h6>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
