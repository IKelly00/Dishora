@extends('layouts/contentNavbarLayout')

@section('title', 'Notifications')

@section('content')

    {{-- Remix Icon (same as Orders blade, if not already in layout) --}}
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.2.0/fonts/remixicon.css" rel="stylesheet">

    <div class="container py-5">
        <div class="main-content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">Notifications</h4>

                @if (!$notifications->isEmpty())
                    <span class="badge bg-light text-muted fw-normal">
                        {{ method_exists($notifications, 'total') ? $notifications->total() : $notifications->count() }}
                        total
                    </span>
                @endif
            </div>

            @if ($notifications->isEmpty())
                <div class="alert alert-light text-center p-5 rounded-4 shadow-sm border">
                    <i class="ri-notification-off-line display-3 d-block mb-3 text-muted"></i>
                    <h4 class="fw-bold mb-2">You're all caught up</h4>
                    <p class="text-muted mb-0">You don't have any notifications right now.</p>
                </div>
            @else
                <div class="notification-list">
                    @foreach ($notifications as $notification)
                        @php
                            $isRead = $notification->is_read;
                            $title = $notification->payload['title'] ?? 'New Notification';
                            $excerpt = $notification->payload['excerpt'] ?? null;
                            $url = $notification->payload['url'] ?? '#';
                        @endphp

                        <a href="{{ $url }}"
                            class="notification-item d-flex justify-content-between align-items-start mb-3
                                  {{ $isRead ? 'notification-read' : 'notification-unread' }}">

                            <div class="me-3 flex-grow-1" style="min-width: 0;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="notification-dot {{ $isRead ? 'opacity-0' : '' }}"></span>
                                    <span class="fw-semibold text-dark text-truncate">
                                        {{ $title }}
                                    </span>
                                </div>

                                @if (!empty($excerpt))
                                    <div class="small text-muted text-wrap ms-4">
                                        {{ $excerpt }}
                                    </div>
                                @endif

                                <div class="small text-muted mt-2">
                                    <i class="ri-time-line me-1"></i>
                                    {{ $notification->created_at->diffForHumans() }}
                                </div>
                            </div>

                            @if (!$isRead)
                                <span class="badge bg-primary-subtle text-primary fw-semibold rounded-pill ms-2">
                                    New
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>

                @if ($notifications->hasPages())
                    <div class="pagination-wrapper">
                        {{ $notifications->links('pagination::bootstrap-5') }}
                    </div>
                @endif

            @endif
        </div>
    </div>

    <style>
        /* main wrapper – same feel as Orders page */
        .main-content-area {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .notification-list {
            margin-top: 0.5rem;
        }

        .notification-item {
            text-decoration: none;
            background: #ffffff;
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            border: 1px solid rgba(0, 0, 0, 0.04);
            box-shadow: 0 4px 12px rgba(15, 31, 42, 0.04);
            transition: all 0.15s ease-in-out;
        }

        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(15, 31, 42, 0.08);
            border-color: rgba(0, 0, 0, 0.06);
        }

        .notification-unread {
            background: linear-gradient(180deg, #fff8e6 0%, #fff3d6 100%);
            border-left: 4px solid #f0a93a;
        }

        .notification-read {
            background: #ffffff;
            border-left: 4px solid transparent;
        }

        .notification-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: #f0a93a;
            flex-shrink: 0;
        }

        .badge.bg-primary-subtle {
            background: #e9f3ff;
            color: #1a66d0 !important;
        }

        .pagination-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        @media (max-width: 576px) {
            .main-content-area {
                padding: 1.1rem;
            }

            .notification-item {
                padding: 0.85rem 0.95rem;
            }

            .badge.bg-primary-subtle {
                font-size: 0.7rem;
            }
        }
    </style>
@endsection
