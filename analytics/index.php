<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_login();

$user_id = get_user_id();

$page_title = "Analytics";
$page_css = ["analytics"];

/* Selected Month & Ranges */

$selected_month = $_GET["month"] ?? date("Y-m");

if (!preg_match("/^\d{4}-\d{2}$/", $selected_month)) {
    $selected_month = date("Y-m");
}

$month_start = $selected_month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));
$month_display_name = date("F Y", strtotime($month_start));

$prev_month_ts = strtotime($month_start . " -1 month");
$prev_month = date("Y-m", $prev_month_ts);
$prev_month_start = $prev_month . "-01";
$prev_month_end = date("Y-m-t", $prev_month_ts);
$prev_month_name = date("F Y", $prev_month_ts);

$next_month_ts = strtotime($month_start . " +1 month");
$next_month = date("Y-m", $next_month_ts);

/* Current Month Total Spent */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT COALESCE(SUM(amount), 0) AS total, COUNT(id) AS tx_count
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    "
);

mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$total_spent = (float) ($total_row["total"] ?? 0);
$total_transactions = (int) ($total_row["tx_count"] ?? 0);

/* Previous Month Total Spent (For Comparison) */

$stmt_prev = mysqli_prepare(
    $conn,
    "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    "
);

mysqli_stmt_bind_param($stmt_prev, "iss", $user_id, $prev_month_start, $prev_month_end);
mysqli_stmt_execute($stmt_prev);
$prev_res = mysqli_stmt_get_result($stmt_prev);
$prev_row = mysqli_fetch_assoc($prev_res);
mysqli_stmt_close($stmt_prev);

$prev_total_spent = (float) ($prev_row["total"] ?? 0);
$diff_amount = $total_spent - $prev_total_spent;
$diff_percentage = 0;
if ($prev_total_spent > 0) {
    $diff_percentage = ($diff_amount / $prev_total_spent) * 100;
} elseif ($total_spent > 0) {
    $diff_percentage = 100;
}

/* Category Breakdown */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        category,
        SUM(amount) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total DESC
    "
);

mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$category_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $category_data[] = [
        "category" => $row["category"],
        "total" => (float) $row["total"]
    ];
}
mysqli_stmt_close($stmt);

/* Category Percentage & Max Category */

$top_category = null;
$max_category_spending = 0;

foreach ($category_data as &$category) {
    if ($total_spent > 0) {
        $category["percentage"] = ($category["total"] / $total_spent) * 100;
    } else {
        $category["percentage"] = 0;
    }

    if ($category["total"] > $max_category_spending) {
        $max_category_spending = $category["total"];
        $top_category = $category["category"];
    }
}
unset($category);

/* Previous Month Category Map (For Comparison Tab) */

$stmt_prev_cats = mysqli_prepare(
    $conn,
    "
    SELECT category, SUM(amount) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    GROUP BY category
    "
);

mysqli_stmt_bind_param($stmt_prev_cats, "iss", $user_id, $prev_month_start, $prev_month_end);
mysqli_stmt_execute($stmt_prev_cats);
$prev_cats_res = mysqli_stmt_get_result($stmt_prev_cats);
$prev_category_map = [];
while ($row = mysqli_fetch_assoc($prev_cats_res)) {
    $prev_category_map[$row["category"]] = (float) $row["total"];
}
mysqli_stmt_close($stmt_prev_cats);

/* Daily Spending Trend */

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT
        expense_date,
        SUM(amount) AS total
    FROM expenses
    WHERE user_id = ?
    AND expense_date BETWEEN ? AND ?
    GROUP BY expense_date
    ORDER BY expense_date ASC
    "
);

mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$daily_spending = [];
$peak_day_date = null;
$peak_day_amount = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $amount = (float) $row["total"];
    $daily_spending[] = [
        "date" => $row["expense_date"],
        "total" => $amount
    ];

    if ($amount > $peak_day_amount) {
        $peak_day_amount = $amount;
        $peak_day_date = $row["expense_date"];
    }
}
mysqli_stmt_close($stmt);

$active_days_count = count($daily_spending);
$days_in_month = (int) date("t", strtotime($month_start));
$avg_daily_spent = $days_in_month > 0 ? ($total_spent / $days_in_month) : 0;

require_once __DIR__ . "/../includes/header.php";

?>

<div class="analytics-page">

    <!-- Page Header with Month Navigation & Dropdown -->
    <div class="page-header">
        <div>
            <h1>Analytics</h1>
            <p>Understand where your money is going.</p>
        </div>

        <!-- Month Navigation Toolbar -->
        <div class="analytics-month-selector">
            <a href="?month=<?= htmlspecialchars($prev_month); ?>" class="month-btn prev-btn" title="Previous Month">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </a>

            <form method="GET" class="month-form" id="analytics-month-form">
                <input
                    type="month"
                    id="analytics-month"
                    name="month"
                    value="<?= htmlspecialchars($selected_month); ?>"
                    onchange="this.form.submit()"
                    class="month-input-picker"
                    aria-label="Select Analytics Month">
            </form>

            <a href="?month=<?= htmlspecialchars($next_month); ?>" class="month-btn next-btn" title="Next Month">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
    </div>

    <!-- 3 Quick Metric Stat Cards -->
    <div class="analytics-stat-grid">
        <div class="analytics-card stat-card">
            <span class="stat-label">Total Spent</span>
            <strong class="stat-val">₹<?= number_format($total_spent, 2); ?></strong>
            <small class="stat-subtext"><?= $total_transactions; ?> expenses logged in <?= htmlspecialchars($month_display_name); ?></small>
        </div>

        <div class="analytics-card stat-card">
            <span class="stat-label">Daily Average</span>
            <strong class="stat-val">₹<?= number_format($avg_daily_spent, 2); ?></strong>
            <small class="stat-subtext">Across <?= $days_in_month; ?> days (<?= $active_days_count; ?> active spending days)</small>
        </div>

        <div class="analytics-card stat-card">
            <span class="stat-label">Top Category</span>
            <strong class="stat-val"><?= htmlspecialchars($top_category ?? 'None'); ?></strong>
            <small class="stat-subtext"><?= $top_category ? '₹' . number_format($max_category_spending, 2) . ' spent' : 'No expenses yet'; ?></small>
        </div>
    </div>

    <!-- Navigation Tabs (Matching Image 3: Category | Monthly Trend | Compare) -->
    <div class="analytics-tab-nav" role="tablist">
        <button type="button" class="analytics-tab-btn active" data-tab="category" role="tab" aria-selected="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
            Category
        </button>
        <button type="button" class="analytics-tab-btn" data-tab="trend" role="tab" aria-selected="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            Monthly Trend
        </button>
        <button type="button" class="analytics-tab-btn" data-tab="compare" role="tab" aria-selected="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
            Compare
        </button>
    </div>

    <!-- ======================================================================
         TAB 1: CATEGORY (Donut Chart + Vertical Bar Chart)
         ====================================================================== -->
    <div class="analytics-tab-pane active" id="pane-category" role="tabpanel">

        <!-- 1. Category Breakdown Donut Chart (Matching Image 4) -->
        <section class="analytics-card category-donut-card">
            <div class="analytics-card-header">
                <h2>Category Breakdown</h2>
                <span><?= htmlspecialchars($month_display_name); ?></span>
            </div>

            <?php if (!empty($category_data)): ?>
                <div id="analytics-category-donut"></div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No expenses found for <?= htmlspecialchars($month_display_name); ?>.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. Category Vertical Bar Chart (Matching Image 3) -->
        <section class="analytics-card category-barchart-card">
            <div class="analytics-card-header">
                <h2>Category Spending Distribution</h2>
                <span><?= htmlspecialchars($month_display_name); ?></span>
            </div>

            <?php if (!empty($category_data)): ?>
                <div id="analytics-category-barchart"></div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No category spending data to graph for this month.</p>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <!-- ======================================================================
         TAB 2: MONTHLY TREND (Vertical Day-by-Day Columns)
         ====================================================================== -->
    <div class="analytics-tab-pane" id="pane-trend" role="tabpanel">
        <section class="analytics-card trend-card">
            <div class="analytics-card-header">
                <h2>Daily Spending Trend</h2>
                <span><?= htmlspecialchars($month_display_name); ?></span>
            </div>

            <?php if (!empty($daily_spending)): ?>
                <div id="analytics-trend-barchart"></div>

                <!-- Daily Breakdown Summary Chips -->
                <div class="trend-insights">
                    <div class="insight-chip">
                        <span>Peak Spending Day</span>
                        <strong><?= $peak_day_date ? date("d M Y", strtotime($peak_day_date)) : 'N/A'; ?></strong>
                        <small>₹<?= number_format($peak_day_amount, 2); ?></small>
                    </div>
                    <div class="insight-chip">
                        <span>Active Spending Days</span>
                        <strong><?= $active_days_count; ?> of <?= $days_in_month; ?> days</strong>
                        <small><?= number_format(($active_days_count / $days_in_month) * 100, 1); ?>% of month</small>
                    </div>
                    <div class="insight-chip">
                        <span>Total Month Spend</span>
                        <strong>₹<?= number_format($total_spent, 2); ?></strong>
                        <small><?= $total_transactions; ?> total transactions</small>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No daily spending recorded for <?= htmlspecialchars($month_display_name); ?>.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ======================================================================
         TAB 3: COMPARE (Month-over-Month Comparison)
         ====================================================================== -->
    <div class="analytics-tab-pane" id="pane-compare" role="tabpanel">
        <section class="analytics-card compare-card">
            <div class="analytics-card-header">
                <h2>Month-over-Month Comparison</h2>
                <span><?= htmlspecialchars($month_display_name); ?> vs <?= htmlspecialchars($prev_month_name); ?></span>
            </div>

            <div class="compare-grid">
                <div class="compare-stat-card">
                    <span class="compare-stat-label">Current Month (<?= htmlspecialchars($month_display_name); ?>)</span>
                    <strong class="compare-stat-value">₹<?= number_format($total_spent, 2); ?></strong>
                </div>

                <div class="compare-stat-card">
                    <span class="compare-stat-label">Previous Month (<?= htmlspecialchars($prev_month_name); ?>)</span>
                    <strong class="compare-stat-value">₹<?= number_format($prev_total_spent, 2); ?></strong>
                </div>

                <div class="compare-stat-card">
                    <span class="compare-stat-label">Spending Change</span>
                    <strong class="compare-stat-value">
                        <?= $diff_amount >= 0 ? '+' : ''; ?>₹<?= number_format($diff_amount, 2); ?>
                    </strong>
                    <span class="compare-badge <?= $diff_amount > 0 ? 'up' : ($diff_amount < 0 ? 'down' : 'neutral'); ?>">
                        <?= $diff_amount > 0 ? '↑ Increased by ' : ($diff_amount < 0 ? '↓ Decreased by ' : 'No change '); ?>
                        <?= number_format(abs($diff_percentage), 1); ?>%
                    </span>
                </div>
            </div>

            <!-- Category Comparison List -->
            <?php
            // Merge all categories from current and previous month
            $all_categories = array_unique(array_merge(
                array_column($category_data, 'category'),
                array_keys($prev_category_map)
            ));
            sort($all_categories);
            ?>

            <?php if (!empty($all_categories)): ?>
                <div class="compare-category-list">
                    <h3 class="compare-subheading">Category Comparison</h3>
                    <div class="compare-table-wrapper">
                        <table class="compare-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th><?= date("M Y", strtotime($month_start)); ?></th>
                                    <th><?= date("M Y", strtotime($prev_month_start)); ?></th>
                                    <th>Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_categories as $cat): ?>
                                    <?php
                                    $curr_val = 0;
                                    foreach ($category_data as $cd) {
                                        if ($cd["category"] === $cat) {
                                            $curr_val = $cd["total"];
                                            break;
                                        }
                                    }
                                    $prev_val = $prev_category_map[$cat] ?? 0;
                                    $cat_diff = $curr_val - $prev_val;
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="compare-cat-name">
                                                <span class="legend-color-dot" style="background-color: var(--cat-color, #3b82f6);" data-category="<?= htmlspecialchars($cat); ?>"></span>
                                                <?= htmlspecialchars($cat); ?>
                                            </span>
                                        </td>
                                        <td><strong>₹<?= number_format($curr_val, 2); ?></strong></td>
                                        <td><span class="text-muted">₹<?= number_format($prev_val, 2); ?></span></td>
                                        <td>
                                            <span class="compare-badge <?= $cat_diff > 0 ? 'up' : ($cat_diff < 0 ? 'down' : 'neutral'); ?>">
                                                <?= $cat_diff > 0 ? '+' : ''; ?>₹<?= number_format($cat_diff, 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No expense data available for comparison.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>

</div>

<!-- Pass Data to JavaScript for High-Performance Chart Rendering -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (!window.ExpenseCharts) return;

    // 1. Render Category Donut Chart
    const categoryData = <?= json_encode(array_map(function($c) {
        return [
            'category' => $c['category'],
            'total' => (float)$c['total'],
            'percentage' => (float)$c['percentage']
        ];
    }, $category_data)); ?>;

    if (categoryData.length > 0) {
        ExpenseCharts.renderDonutChart('analytics-category-donut', categoryData, {
            centerTitle: 'Total Spent',
            compact: false
        });

        // 2. Render Category Vertical Bar Chart (Matching Mockup 3)
        ExpenseCharts.renderVerticalBarChart('analytics-category-barchart', categoryData, {
            caption: 'Understand your spending with charts.'
        });
    }

    // 3. Render Trend Bar Chart
    const trendData = <?= json_encode(array_map(function($d) {
        return [
            'dateLabel' => date("d M", strtotime($d['date'])),
            'date' => $d['date'],
            'total' => (float)$d['total']
        ];
    }, $daily_spending)); ?>;

    if (trendData.length > 0) {
        ExpenseCharts.renderTrendBarChart('analytics-trend-barchart', trendData);
    }

    // Set dynamic category dots in comparison table
    document.querySelectorAll('.compare-cat-name .legend-color-dot').forEach(function(dot) {
        const catName = dot.getAttribute('data-category');
        if (catName && window.ExpenseCharts.getCategoryColor) {
            dot.style.backgroundColor = window.ExpenseCharts.getCategoryColor(catName);
        }
    });

    // 4. Tab Switching Logic (Category | Monthly Trend | Compare)
    const tabBtns = document.querySelectorAll('.analytics-tab-btn');
    const tabPanes = document.querySelectorAll('.analytics-tab-pane');

    function switchTab(targetTab) {
        tabBtns.forEach(btn => {
            const isActive = btn.getAttribute('data-tab') === targetTab;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        tabPanes.forEach(pane => {
            const isActive = pane.id === 'pane-' + targetTab;
            pane.classList.toggle('active', isActive);
        });

        // Update URL hash
        if (history.replaceState) {
            history.replaceState(null, null, '#' + targetTab);
        }
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const target = this.getAttribute('data-tab');
            switchTab(target);
        });
    });

    // Support initial hash in URL
    const hash = window.location.hash.replace('#', '');
    if (hash && (hash === 'category' || hash === 'trend' || hash === 'compare')) {
        switchTab(hash);
    }
});
</script>

<?php

require_once __DIR__ . "/../includes/footer.php";
