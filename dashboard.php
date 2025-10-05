<?php
require_once 'config.php';
requireLogin();

$settings = getSettings();
$userId = getUserId();
$userRole = getUserRole();

// Get dashboard statistics
$stats = [];

// Total projects
if (hasPermission('projects', 'view')) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status = 'active'");
    $stats['active_projects'] = $stmt->fetch()['count'];
}

// Total plots
if (hasPermission('plots', 'view')) {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked,
        SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold
        FROM plots");
    $plotStats = $stmt->fetch();
    $stats['total_plots'] = $plotStats['total'];
    $stats['available_plots'] = $plotStats['available'];
    $stats['booked_plots'] = $plotStats['booked'];
    $stats['sold_plots'] = $plotStats['sold'];
}

// Sales statistics
if (hasPermission('sales', 'view')) {
    // This month sales
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(sale_price), 0) as total 
                         FROM sales 
                         WHERE MONTH(sale_date) = MONTH(CURRENT_DATE()) 
                         AND YEAR(sale_date) = YEAR(CURRENT_DATE())
                         AND status != 'cancelled'");
    $salesData = $stmt->fetch();
    $stats['monthly_sales'] = $salesData['count'];
    $stats['monthly_revenue'] = $salesData['total'];
    
    // Total sales
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(sale_price), 0) as total 
                         FROM sales WHERE status != 'cancelled'");
    $totalSales = $stmt->fetch();
    $stats['total_sales'] = $totalSales['count'];
    $stats['total_revenue'] = $totalSales['total'];
}

// Payments this month
if (hasPermission('payments', 'view')) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total 
                         FROM payments 
                         WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
                         AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
    $stats['monthly_payments'] = $stmt->fetch()['total'];
}

// My leads (for sales agents)
if ($userRole === 'sales_agent' && hasPermission('leads', 'view')) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE assigned_to = ? AND status NOT IN ('converted', 'lost')");
    $stmt->execute([$userId]);
    $stats['my_leads'] = $stmt->fetch()['count'];
}

// All active leads
if (hasPermission('leads', 'view') && in_array($userRole, ['admin', 'manager', 'reception'])) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leads WHERE status NOT IN ('converted', 'lost')");
    $stats['active_leads'] = $stmt->fetch()['count'];
}

// Recent activities
$recentSales = [];
if (hasPermission('sales', 'view')) {
    $stmt = $pdo->query("SELECT s.*, c.full_name as client_name, p.plot_number, pr.project_name, u.full_name as agent_name
                         FROM sales s
                         JOIN clients c ON s.client_id = c.id
                         JOIN plots p ON s.plot_id = p.id
                         JOIN projects pr ON p.project_id = pr.id
                         JOIN users u ON s.agent_id = u.id
                         WHERE s.status != 'cancelled'
                         ORDER BY s.created_at DESC LIMIT 5");
    $recentSales = $stmt->fetchAll();
}

// Sales chart data (last 6 months)
$chartData = [];
if (hasPermission('sales', 'view')) {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(sale_price), 0) as total 
                               FROM sales 
                               WHERE DATE_FORMAT(sale_date, '%Y-%m') = ? AND status != 'cancelled'");
        $stmt->execute([$month]);
        $data = $stmt->fetch();
        
        $chartData[] = [
            'month' => $monthName,
            'sales' => $data['count'],
            'revenue' => $data['total']
        ];
    }
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <!-- Welcome Section -->
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Welcome back, <?php echo sanitize(explode(' ', getUserName())[0]); ?>!</h1>
        <p class="text-gray-600 mt-1">Here's what's happening with your business today.</p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php if (isset($stats['active_projects'])): ?>
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">Active Projects</p>
                    <p class="text-2xl md:text-3xl font-bold mt-2" style="color: var(--primary-color);">
                        <?php echo $stats['active_projects']; ?>
                    </p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 md:w-8 md:h-8" style="color: var(--primary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($stats['sold_plots'])): ?>
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">Plots Sold</p>
                    <p class="text-2xl md:text-3xl font-bold mt-2" style="color: var(--secondary-color);">
                        <?php echo $stats['sold_plots']; ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo $stats['available_plots']; ?> available</p>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-6 h-6 md:w-8 md:h-8" style="color: var(--secondary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($stats['monthly_sales'])): ?>
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">This Month Sales</p>
                    <p class="text-2xl md:text-3xl font-bold mt-2" style="color: var(--primary-color);">
                        <?php echo $stats['monthly_sales']; ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo formatMoney($stats['monthly_revenue']); ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 md:w-8 md:h-8" style="color: var(--primary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($stats['monthly_payments'])): ?>
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">Monthly Deposits</p>
                    <p class="text-xl md:text-2xl font-bold mt-2" style="color: var(--secondary-color);">
                        <?php echo formatMoney($stats['monthly_payments']); ?>
                    </p>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-6 h-6 md:w-8 md:h-8" style="color: var(--secondary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Charts and Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Sales Chart -->
        <?php if (!empty($chartData)): ?>
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <h2 class="text-lg font-bold mb-4">Sales Overview</h2>
            <canvas id="salesChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>
        <?php endif; ?>
        
        <!-- Recent Sales -->
        <?php if (!empty($recentSales)): ?>
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <h2 class="text-lg font-bold mb-4">Recent Sales</h2>
            <div class="space-y-3 overflow-y-auto" style="max-height: 300px;">
                <?php foreach ($recentSales as $sale): ?>
                <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate"><?php echo sanitize($sale['client_name']); ?></p>
                        <p class="text-xs text-gray-600">
                            <?php echo sanitize($sale['project_name'] . ' - Plot ' . $sale['plot_number']); ?>
                        </p>
                        <p class="text-xs text-gray-500"><?php echo formatDate($sale['sale_date']); ?></p>
                    </div>
                    <div class="text-right ml-4">
                        <p class="font-bold text-sm" style="color: var(--primary-color);">
                            <?php echo formatMoney($sale['sale_price']); ?>
                        </p>
                        <span class="text-xs px-2 py-1 rounded-full <?php echo $sale['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo ucfirst($sale['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-4 md:p-6">
        <h2 class="text-lg font-bold mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <?php if (hasPermission('leads', 'create')): ?>
            <a href="/leads.php?action=create" class="flex flex-col items-center justify-center p-4 rounded-lg border-2 border-gray-200 hover:border-primary transition">
                <svg class="w-8 h-8 mb-2" style="color: var(--primary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                <span class="text-xs font-semibold text-center">Add Lead</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('clients', 'create')): ?>
            <a href="/clients.php?action=create" class="flex flex-col items-center justify-center p-4 rounded-lg border-2 border-gray-200 hover:border-primary transition">
                <svg class="w-8 h-8 mb-2" style="color: var(--secondary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="text-xs font-semibold text-center">Add Client</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('projects', 'view')): ?>
            <a href="/projects.php" class="flex flex-col items-center justify-center p-4 rounded-lg border-2 border-gray-200 hover:border-primary transition">
                <svg class="w-8 h-8 mb-2" style="color: var(--primary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span class="text-xs font-semibold text-center">Projects</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('attendance', 'create')): ?>
            <a href="/attendance.php" class="flex flex-col items-center justify-center p-4 rounded-lg border-2 border-gray-200 hover:border-primary transition">
                <svg class="w-8 h-8 mb-2" style="color: var(--secondary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-xs font-semibold text-center">Clock In/Out</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('site_visits', 'view')): ?>
            <a href="/site-visits.php" class="flex flex-col items-center justify-center p-4 rounded-lg border-2 border-gray-200 hover:border-primary transition">
                <svg class="w-8 h-8 mb-2" style="color: var(--primary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="text-xs font-semibold text-center">Site Visits</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('reports', 'view')): ?>
            <a href="/reports.php" class="flex flex-col items-center justify-center p-4 rounded-lg border-2 border-gray-200 hover:border-primary transition">
                <svg class="w-8 h-8 mb-2" style="color: var(--secondary-color);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-xs font-semibold text-center">Reports</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($chartData)): ?>
const ctx = document.getElementById('salesChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($chartData, 'month')); ?>,
        datasets: [{
            label: 'Sales Count',
            data: <?php echo json_encode(array_column($chartData, 'sales')); ?>,
            backgroundColor: '<?php echo $settings['primary_color']; ?>',
            borderColor: '<?php echo $settings['primary_color']; ?>',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>