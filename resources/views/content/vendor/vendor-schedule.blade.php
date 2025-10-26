@extends('layouts.contentNavbarLayout')

@section('title', 'Pre-order Schedule')

@section('content')
    <!-- FullCalendar CSS & JS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

    {{-- Server-Side Toastr Script --}}
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "5000"
        };
        @if (Session::has('success'))
            toastr.success("{{ Session::get('success') }}");
        @endif
        @if (Session::has('error'))
            toastr.error("{{ Session::get('error') }}");
        @endif
    </script>

    <div class="container py-4 py-lg-5">
        <div class="main-content-area">
            <h4 class="fw-bold mb-4"><span class="text-muted fw-light">Manage /</span> Pre-order Schedule</h4>
            <div class="alert alert-info bg-light-info border-0" role="alert">
                <h5 class="alert-heading fw-bold text-info-emphasis"><i class="ri-information-line me-1"></i> How to Use</h5>
                <p class="mb-0 text-info-emphasis">Click a future date to set capacity. Click an existing event to view,
                    edit, or remove it. Past dates can be viewed but not modified.</p>
            </div>
            <div id="calendar" class="mt-4"></div>
        </div>
    </div>

    <!-- SCHEDULE SETTINGS MODAL -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="scheduleModalForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">
                            <i class="ri-calendar-todo-line me-2"></i>
                            <span>Set Pre-Order Capacity</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="scheduleDate" name="date">

                        <!-- Styled Date Display -->
                        <div class="date-display-box mb-3">
                            <i class="ri-calendar-2-line"></i>
                            <span id="modalDateDisplay"></span>
                        </div>

                        <!-- Informational Box for warnings -->
                        <div id="infoBox" class="alert d-none mt-3" role="alert"></div>

                        <!-- Editable Content (for new/future dates) -->
                        <div id="editableContent">
                            <label for="maxOrders" class="form-label fw-bold">Maximum Pre-Orders</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="ri-shopping-bag-3-line"></i></span>
                                <input type="number" class="form-control" id="maxOrders" name="max_orders" min="1"
                                    placeholder="e.g., 10" required>
                            </div>
                            <div class="form-text mt-2">Set the total number of orders you can accept for this day.</div>
                        </div>

                        <!-- Read-only Content (for past dates) -->
                        <div id="readOnlyContent" class="d-none">
                            <div class="metric-display">
                                <label class="metric-label">Max Pre-Orders Set</label>
                                <p class="metric-value" id="maxOrdersValue"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div id="actionButtonsContainer" class="w-100 d-flex">
                            <button type="button" id="removeScheduleBtn"
                                class="btn btn-outline-danger me-auto">Remove</button>
                            <button type="button" class="btn btn-secondary me-1" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="saveScheduleBtn" class="btn btn-primary">Save Settings</button>
                        </div>
                        <div id="closeButtonContainer" class="w-100 text-end d-none">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CONFIRM REMOVE MODAL -->
    <div class="modal fade" id="confirmRemoveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow">
                <div class="modal-body p-4 text-center">
                    <i class="ri-error-warning-line display-3 text-danger mb-3"></i>
                    <h5 class="fw-bold">Are you sure?</h5>
                    <p class="text-muted">Do you want to close this date for pre-orders? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmRemoveBtn" class="btn btn-danger">Yes, Remove it</button>
                </div>
            </div>
        </div>
    </div>

    {{-- STYLES (Calendar & Modal) --}}
    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .fc .fc-toolbar-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .fc-day-past .fc-daygrid-day-number {
            color: #d1d5db !important;
        }

        .fc-event {
            padding: 8px;
            cursor: pointer;
            border-radius: 8px !important;
            border: 1px solid transparent !important;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .fc-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .event-wrapper {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .event-count {
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .event-count i {
            margin-right: 6px;
            font-size: 1.2em;
        }

        .event-progress-bar {
            background-color: rgba(0, 0, 0, 0.07);
            border-radius: 99px;
            height: 6px;
            overflow: hidden;
            width: 100%;
        }

        .event-progress-bar span {
            display: block;
            height: 100%;
            border-radius: 99px;
            transition: width 0.3s ease-in-out;
        }

        .fc-daygrid-event.event-available {
            background-color: #f0fdf4 !important;
            border-color: #bbf7d0 !important;
        }

        .event-available .event-count {
            color: #166534 !important;
        }

        .event-available .event-progress-bar span {
            background-color: #22c55e !important;
        }

        .fc-direction-ltr .fc-daygrid-event.fc-event-end,
        .fc-direction-rtl .fc-daygrid-event.fc-event-start {
            margin-right: 10px;
        }

        .fc-direction-ltr .fc-daygrid-event.fc-event-start,
        .fc-direction-rtl .fc-daygrid-event.fc-event-end {
            margin-left: 10px;
        }

        .fc-daygrid-event.event-full {
            background-color: #fef2f2 !important;
            border-color: #fecaca !important;
        }

        .fc .fc-daygrid-day-number {
            font-size: 15px !important;
        }

        .event-full .event-count {
            color: #991b1b !important;
        }

        .event-full .event-progress-bar span {
            background-color: #ef4444 !important;
        }

        .fc-daygrid-event.event-past {
            cursor: default;
            opacity: 0.7;
        }

        .event-past .event-count {
            color: #475569 !important;
        }

        .event-past .event-progress-bar span {
            background-color: #64748b !important;
        }

        .event-past:hover {
            transform: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .fc-daygrid-event.event-past.event-full {
            background-color: #fef2f2 !important;
            border-color: #fecaca !important;
        }

        .event-past.event-full .event-count {
            color: #991b1b !important;
        }

        .event-past.event-full .event-progress-bar span {
            background-color: #ef4444 !important;
        }

        #scheduleModal .modal-content {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        #scheduleModal .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }

        #scheduleModal .modal-title {
            font-weight: 600;
            color: #343a40;
            display: flex;
            align-items: center;
        }

        #scheduleModal .modal-body {
            padding: 1.5rem;
        }

        #scheduleModal .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }

        .date-display-box {
            background-color: #e9ecef;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
            border: 1px solid #ced4da;
        }

        .date-display-box i {
            font-size: 1.5rem;
            color: #6c757d;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-right: 0;
            color: #6c757d;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
            border-color: #86b7fe;
        }

        .input-group .form-control {
            border-left: 0;
            padding-left: 0.5rem;
        }

        .metric-display {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            text-align: center;
            padding: 1.5rem;
        }

        .metric-label {
            font-size: 0.875rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #212529;
            line-height: 1.2;
            margin-bottom: 0;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const openingHours = @json($openingHours ?? []);
            const serverNowString = '{{ $serverNow ?? now()->toIso8601String() }}';

            // ============= DOM ELEMENT REFERENCES (No changes) =============
            const calendarEl = document.getElementById('calendar');
            const scheduleModalEl = document.getElementById('scheduleModal');
            const scheduleModal = new bootstrap.Modal(scheduleModalEl);
            const confirmRemoveModal = new bootstrap.Modal(document.getElementById('confirmRemoveModal'));
            const modalForm = document.getElementById('scheduleModalForm');
            const modalTitleSpan = document.querySelector('#scheduleModalLabel span');
            const modalDateInput = document.getElementById('scheduleDate');
            const modalDateDisplay = document.getElementById('modalDateDisplay');
            const editableContent = document.getElementById('editableContent');
            const readOnlyContent = document.getElementById('readOnlyContent');
            const maxOrdersInput = document.getElementById('maxOrders');
            const maxOrdersValue = document.getElementById('maxOrdersValue');
            const infoBox = document.getElementById('infoBox');
            const actionButtonsContainer = document.getElementById('actionButtonsContainer');
            const closeButtonContainer = document.getElementById('closeButtonContainer');
            const removeBtn = document.getElementById('removeScheduleBtn');
            const confirmRemoveBtn = document.getElementById('confirmRemoveBtn');


            // ============= STATE VARIABLES (Correct) =============
            let currentScheduleId = null;
            let currentOrderCountForEvent = 0;
            const serverNow = new Date(serverNowString);
            const year = serverNow.getFullYear();
            const month = (serverNow.getMonth() + 1).toString().padStart(2, '0');
            const day = serverNow.getDate().toString().padStart(2, '0');
            const todayStr = `${year}-${month}-${day}`;


            // ============= HELPER FUNCTIONS (Correct) =============
            function getTodaysEditabilityStatus() {
                const now = new Date(serverNowString);
                const dayOfWeek = now.toLocaleDateString('en-US', {
                    weekday: 'long'
                });
                const todaysHours = openingHours[dayOfWeek];
                if (!todaysHours || todaysHours.is_closed == 1 || !todaysHours.closes_at) {
                    return {
                        editable: true,
                        reason: null,
                        time: null
                    };
                }
                const [h, m] = todaysHours.closes_at.split(':');
                const closingTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m);
                if (now >= closingTime) {
                    return {
                        editable: false,
                        reason: 'PAST_CLOSING',
                        time: closingTime
                    };
                }
                return {
                    editable: true,
                    reason: null,
                    time: null
                };
            }

            function isDateEditable(dateStr) {
                if (dateStr < todayStr) return false;
                if (dateStr > todayStr) return true;
                return getTodaysEditabilityStatus().editable;
            }

            // ============= CALENDAR INITIALIZATION (Updated with Final Fix) =============
            const calendar = new FullCalendar.Calendar(calendarEl, {
                now: serverNowString,
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth'
                },
                events: "{{ route('vendor.schedule.events') }}",
                selectable: true,
                expandRows: true,

                dayCellDidMount: info => {
                    // Apply styles based on the checks
                    if (info.isPast) {
                        info.el.classList.add('fc-day-past');
                    }
                    if (info.isToday) {
                        info.el.style.backgroundColor = '#fffbeb';
                    } else if (info.isPast) {
                        info.el.style.backgroundColor = '#f9fafb';
                    }
                },

                eventContent: arg => {
                    const props = arg.event.extendedProps;
                    const percentage = props.max_orders > 0 ? (props.current_order_count / props
                        .max_orders) * 100 : 0;
                    return {
                        html: `<div class="event-wrapper" title="${props.current_order_count} out of ${props.max_orders} slots taken"><div class="event-details"><span class="event-count"><i class="ri-shopping-bag-line"></i> ${props.current_order_count} / ${props.max_orders}</span></div><div class="event-progress-bar" title="${Math.round(percentage)}% full"><span style="width: ${percentage}%;"></span></div></div>`
                    };
                },
                select: info => {
                    const selectedDate = new Date(info.startStr);
                    const dayOfWeek = selectedDate.toLocaleDateString('en-US', {
                        weekday: 'long'
                    });
                    const todaysHours = openingHours[dayOfWeek];

                    // Check if the business is closed that day
                    if (!todaysHours || todaysHours.is_closed == 1) {
                        toastr.warning(
                            `The business is closed every ${dayOfWeek}. You can’t set a pre‑order schedule.`
                        );
                        calendar.unselect();
                        return;
                    }

                    // Prevent if the date is before today
                    if (info.startStr < todayStr) {
                        toastr.error('You cannot set a schedule for a past date.');
                        calendar.unselect();
                        return;
                    }

                    // Special handling for today's date
                    if (info.startStr === todayStr && !isDateEditable(info.startStr)) {
                        const closingInfo = getTodaysEditabilityStatus();
                        if (closingInfo.reason === 'PAST_CLOSING') {
                            const formattedTime = closingInfo.time.toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                            toastr.error(
                                `You’re past today’s closing time of ${formattedTime}. No new schedule allowed.`
                            );
                        } else {
                            toastr.error('Scheduling for today is currently locked.');
                        }
                        calendar.unselect();
                        return;
                    }

                    // All checks passed → continue
                    openModalForNewEvent(info.startStr);
                    calendar.unselect();
                },
                eventClick: info => {
                    isDateEditable(info.event.startStr) ? openModalForFutureEvent(info) :
                        openModalForPastEvent(info);
                }
            });
            calendar.render();

            // ============= MODAL CONTROL LOGIC & FETCH FUNCTIONS (No changes needed) =============
            function setupModal(config) {
                modalForm.reset();
                currentScheduleId = config.id || null;
                currentOrderCountForEvent = config.currentOrders || 0;
                modalTitleSpan.textContent = config.title;
                modalDateInput.value = config.date;
                modalDateDisplay.textContent = formatDate(config.date);

                infoBox.classList.add('d-none');
                infoBox.classList.remove('alert-warning', 'alert-danger', 'alert-info');
                editableContent.classList.toggle('d-none', !config.isEditable);
                readOnlyContent.classList.toggle('d-none', config.isEditable);
                actionButtonsContainer.classList.toggle('d-none', !config.isEditable);
                closeButtonContainer.classList.toggle('d-none', config.isEditable);

                if (config.isEditable) {
                    maxOrdersInput.value = config.maxOrders || '10';
                    const canRemove = config.showRemove && parseInt(currentOrderCountForEvent) === 0;
                    removeBtn.style.display = canRemove ? 'inline-block' : 'none';

                    if (config.showRemove && !canRemove) {
                        infoBox.innerHTML =
                            `<i class="ri-error-warning-line me-2"></i> This schedule has <strong>${currentOrderCountForEvent} existing order(s)</strong> and cannot be removed. You can still change the maximum capacity.`;
                        infoBox.classList.add('alert-warning');
                        infoBox.classList.remove('d-none');
                    }
                } else { // Read-only view
                    maxOrdersValue.textContent = config.maxOrders || 'N/A';
                    if (config.date === todayStr) {
                        const status = getTodaysEditabilityStatus();
                        if (status.reason === 'PAST_CLOSING') {
                            const formattedTime = status.time.toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                            infoBox.innerHTML =
                                `<i class="ri-door-closed-line me-2"></i> This schedule cannot be changed as it is past today's closing time of <strong>${formattedTime}</strong>.`;
                            infoBox.classList.add('alert-warning');
                            infoBox.classList.remove('d-none');
                        }
                    } else {
                        infoBox.innerHTML =
                            `<i class="ri-lock-line me-2"></i> This is a past schedule and cannot be modified.`;
                        infoBox.classList.add('alert-info');
                        infoBox.classList.remove('d-none');
                    }
                }
                scheduleModal.show();
            }

            function openModalForNewEvent(dateStr) {
                setupModal({
                    title: 'Set Pre-Order Capacity',
                    date: dateStr,
                    isEditable: true,
                    showRemove: false,
                    maxOrders: '10',
                    currentOrders: 0
                });
            }

            function openModalForFutureEvent(info) {
                const props = info.event.extendedProps;
                setupModal({
                    id: info.event.id,
                    title: 'Edit Pre-Order Capacity',
                    date: info.event.startStr,
                    isEditable: true,
                    showRemove: true,
                    maxOrders: props.max_orders,
                    currentOrders: props.current_order_count
                });
            }

            function openModalForPastEvent(info) {
                const props = info.event.extendedProps;
                setupModal({
                    id: info.event.id,
                    title: 'View Past Schedule',
                    date: info.event.startStr,
                    isEditable: false,
                    maxOrders: props.max_orders
                });
            }

            function formatDate(dateStr) {
                return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }

            modalForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const date = modalDateInput.value;
                const maxOrders = parseInt(maxOrdersInput.value);
                if (date && maxOrders > 0) saveSchedule(date, maxOrders);
            });

            removeBtn.addEventListener('click', () => {
                if (parseInt(currentOrderCountForEvent) > 0) {
                    toastr.error('Cannot remove a schedule that has existing orders.');
                    return;
                }
                scheduleModal.hide();
                confirmRemoveModal.show();
            });

            confirmRemoveBtn.addEventListener('click', () => {
                if (currentScheduleId) removeSchedule(currentScheduleId);
            });

            function saveSchedule(date, maxOrders) {
                fetch("{{ route('vendor.schedule.store') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            date,
                            max_orders: maxOrders
                        })
                    }).then(res => res.ok ? res.json() : res.json().then(err => {
                        throw new Error(err.message)
                    }))
                    .then(data => {
                        toastr.success(data.message);
                        calendar.refetchEvents();
                        scheduleModal.hide();
                    }).catch(error => {
                        console.error('Error saving schedule:', error);
                        toastr.error(error.message || 'A client-side error occurred.');
                    });
            }

            function removeSchedule(scheduleId) {
                fetch(`/vendor/schedule/${scheduleId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).then(res => res.ok ? res.json() : res.json().then(err => {
                        throw new Error(err.message)
                    }))
                    .then(data => {
                        toastr.success(data.message);
                        calendar.refetchEvents();
                        confirmRemoveModal.hide();
                    }).catch(error => {
                        console.error('Error removing schedule:', error);
                        toastr.error(error.message || 'A client-side error occurred.');
                        confirmRemoveModal.hide();
                    });
            }
        });
    </script>
@endsection
