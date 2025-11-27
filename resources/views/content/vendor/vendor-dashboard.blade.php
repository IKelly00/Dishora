@extends('layouts/contentNavbarLayout')

@section('title', 'Dashboard')

@section('content')
    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            margin-bottom: 2rem;
            min-height: 70vh;
        }
    </style>

    {{-- HIDDEN INPUT TO PASS BUSINESS NAME TO JS --}}
    <input type="hidden" id="business-name" value="{{ $business->business_name }}">

    <div class="container-fluid px-4">

        <div class="main-content-area">
            <h1 class="mt-4">Dashboard: {{ $business->business_name }}</h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item active">Overview</li>
            </ol>

            {{-- 1. TOP SUMMARY CARDS --}}
            <div class="row">
                {{-- REVENUE BOX --}}
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fs-5 fw-bold">Total Revenue</div>
                                    <div class="fs-3" id="real-revenue">₱{{ number_format($totalRevenue, 2) }}</div>
                                </div>
                                <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ORDERS BOX --}}
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-warning text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fs-5 fw-bold">New Orders</div>
                                    <div class="fs-3" id="real-orders">{{ $newOrdersCount }}</div>
                                </div>
                                <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ACTIVE PRODUCTS --}}
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fs-5 fw-bold">Active Products</div>
                                    <div class="fs-3">{{ $activeProductsCount }}</div>
                                </div>
                                <i class="fas fa-box fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- AVG RATING --}}
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-danger text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fs-5 fw-bold">Avg. Rating</div>
                                    <div class="fs-3">{{ $averageRating }} / 5.0</div>
                                </div>
                                <i class="fas fa-star fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. SALES REPORT SECTION --}}
            <div class="row">
                <div class="col-xl-12">
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="m-0 font-weight-bold text-dark">
                                    <i class="fas fa-chart-area me-2 text-primary"></i>Monthly Sales Report
                                </h5>
                                {{-- DOWNLOAD BUTTON --}}
                                <button onclick="downloadStyledExcel()" class="btn btn-sm btn-outline-success shadow-sm">
                                    <i class="fas fa-file-excel me-1"></i> Export Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="height: 350px;">
                                <canvas id="salesHistoryChart"></canvas>
                            </div>
                            <div class="small text-muted mt-3 text-center">
                                <i class="fas fa-info-circle me-1"></i>
                                Data is automatically saved month-over-month based on your live dashboard stats.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. RECENT REVIEWS --}}
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-star me-1"></i>
                    Recent Customer Reviews
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentReviews as $review)
                                    <tr>
                                        <td>{{ $review->customer->fullname ?? 'N/A' }}</td>
                                        <td>
                                            <span class="text-warning">

                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= $review->rating)
                                                        <i class="fas fa-star"></i>
                                                    @else
                                                        <i class="far fa-star"></i>
                                                    @endif
                                                @endfor
                                            </span>
                                        </td>
                                        <td>{{ Str::limit($review->comment, 60) ?? 'No comment' }}</td>
                                        <td>{{ $review->created_at->format('M d, Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No reviews found yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. SCRIPTS --}}
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- xlsx-js-style (Allows Colors/Styles in Excel) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            processSalesHistory();
        });

        // --- 1. DATA PROCESSING LOGIC ---
        function processSalesHistory() {
            const date = new Date();
            const currentMonthKey = date.getFullYear() + '-' + (date.getMonth() + 1); 
            const currentMonthLabel = date.toLocaleString('default', { month: 'short', year: 'numeric' });

            const rawRevenue = document.getElementById('real-revenue').innerText;
            const rawOrders = document.getElementById('real-orders').innerText;
            
            const currentRevenue = parseFloat(rawRevenue.replace(/[^0-9.-]+/g,""));
            const currentBoxOrders = parseFloat(rawOrders.replace(/[^0-9.-]+/g,""));

            let history = JSON.parse(localStorage.getItem('vendor_sales_history')) || [];
            const existingIndex = history.findIndex(item => item.key === currentMonthKey);

            if (existingIndex !== -1) {
                let storedTotalOrders = history[existingIndex].total_orders || 0;
                let lastSeenBoxCount = history[existingIndex].last_box_count || 0;

                if (currentBoxOrders > lastSeenBoxCount) {
                    let difference = currentBoxOrders - lastSeenBoxCount;
                    history[existingIndex].total_orders = storedTotalOrders + difference;
                }
                
                history[existingIndex].last_box_count = currentBoxOrders;
                history[existingIndex].revenue = currentRevenue; 
            } else {
                history.push({
                    key: currentMonthKey,
                    label: currentMonthLabel,
                    revenue: currentRevenue,
                    total_orders: currentBoxOrders, 
                    last_box_count: currentBoxOrders 
                });
            }

            localStorage.setItem('vendor_sales_history', JSON.stringify(history));
            renderChart(history);
        }

        // --- 2. CHART RENDERING ---
        function renderChart(data) {
            const ctx = document.getElementById('salesHistoryChart').getContext('2d');
            
            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(13, 110, 253, 0.5)');
            gradient.addColorStop(1, 'rgba(13, 110, 253, 0.0)');

            const labels = data.map(item => item.label);
            const revenues = data.map(item => item.revenue);
            const orders = data.map(item => item.total_orders);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Revenue (₱)',
                            data: revenues,
                            borderColor: '#0d6efd',
                            backgroundColor: gradient,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointHoverRadius: 8,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#0d6efd',
                            pointBorderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y',
                            order: 1
                        },
                        {
                            label: 'Total Cumulative Orders',
                            data: orders,
                            backgroundColor: 'rgba(255, 193, 7, 0.8)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 0,
                            borderRadius: 4,
                            barPercentage: 0.5,
                            type: 'bar',
                            yAxisID: 'y1',
                            order: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: { usePointStyle: true, boxWidth: 10 }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(33, 37, 41, 0.9)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: true
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: { borderDash: [5, 5], color: '#e9ecef'},
                            title: { display: true, text: 'Revenue' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Orders' }
                        }
                    }
                }
            });
        }

        // --- 3. STYLED EXCEL EXPORT ---
        function downloadStyledExcel() {
            const history = JSON.parse(localStorage.getItem('vendor_sales_history'));
            const businessName = document.getElementById('business-name').value || 'Vendor';

            if (!history || history.length === 0) {
                alert("No sales data to export.");
                return;
            }

            // 1. Define Styles
            const styles = {
                title: {
                    font: { bold: true, sz: 16, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "212529" } }, // Dark BG
                    alignment: { horizontal: "center", vertical: "center" }
                },
                header: {
                    font: { bold: true, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "0D6EFD" } }, // Blue BG
                    alignment: { horizontal: "center" },
                    border: {
                        top: { style: "thin" }, bottom: { style: "thin" },
                        left: { style: "thin" }, right: { style: "thin" }
                    }
                },
                cell: {
                    alignment: { horizontal: "center" },
                    border: { bottom: { style: "thin", color: { rgb: "E0E0E0" } } }
                },
                currency: {
                    numFmt: '"₱"#,##0.00', // PHP Format
                    alignment: { horizontal: "right" },
                    border: { bottom: { style: "thin", color: { rgb: "E0E0E0" } } }
                },
                totalRow: {
                    font: { bold: true },
                    fill: { fgColor: { rgb: "FFC107" } }, // Yellow BG
                    border: { top: { style: "double" } }
                }
            };

            // 2. Build the Worksheet Rows Array
            let wsData = [
                [{ v: businessName.toUpperCase() + " - SALES REPORT", s: styles.title }, "", ""], // Row 1: Title
                [{ v: "Generated: " + new Date().toLocaleDateString(), s: { alignment: { horizontal: "left" } } }, "", ""], // Row 2: Date
                ["", "", ""], // Row 3: Spacer
                [ // Row 4: Headers
                    { v: "Month", s: styles.header },
                    { v: "Total Orders", s: styles.header },
                    { v: "Total Revenue", s: styles.header }
                ]
            ];

            // 3. Add Data Rows
            let totalOrders = 0;
            let totalRevenue = 0;

            history.forEach(item => {
                totalOrders += item.total_orders;
                totalRevenue += item.revenue;

                wsData.push([
                    { v: item.label, s: styles.cell },
                    { v: item.total_orders, s: styles.cell },
                    { v: item.revenue, s: styles.currency, t: 'n' } // 't: n' means type number
                ]);
            });

            // 4. Add Total Row
            wsData.push([
                { v: "GRAND TOTAL", s: styles.totalRow },
                { v: totalOrders, s: styles.totalRow },
                { v: totalRevenue, s: { ...styles.totalRow, ...styles.currency, fill: { fgColor: { rgb: "FFC107" } } } }
            ]);

            // 5. Create Workbook & Sheet
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(wsData);

            // 6. Set Column Widths
            ws['!cols'] = [{ wch: 20 }, { wch: 15 }, { wch: 25 }];

            // 7. Merge Title Cells (A1 to C1)
            ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 2 } }];

            XLSX.utils.book_append_sheet(wb, ws, "Monthly Report");
            XLSX.writeFile(wb, `Sales_Report_${businessName}.xlsx`);
        }
    </script>
@endsection
