/**
 * Daily Expense Tracker V2 - Modern Charts Engine
 * Pure SVG & CSS responsive charting system.
 * Zero external dependencies - 100% offline & dark-mode compatible.
 */

(function (window) {
    'use strict';

    // Standard Category Color Palette (Matching User's Reference Mockup)
    const CATEGORY_COLORS = {
        'food': '#3b82f6',          // Vibrant Blue
        'bills': '#0284c7',         // Sky / Cyan Blue
        'transport': '#8b5cf6',     // Violet / Purple
        'travel': '#8b5cf6',        // Violet / Purple
        'shopping': '#f59e0b',      // Warm Amber / Orange
        'entertainment': '#ec4899', // Pink / Coral
        'education': '#10b981',     // Emerald Green
        'health': '#06b6d4',        // Cyan / Teal
        'other': '#64748b',         // Slate Gray
        'others': '#64748b'
    };

    const FALLBACK_PALETTE = [
        '#3b82f6', '#0284c7', '#8b5cf6', '#f59e0b',
        '#ec4899', '#10b981', '#06b6d4', '#64748b',
        '#6366f1', '#14b8a6', '#f97316', '#84cc16'
    ];

    function getCategoryColor(name, index = 0) {
        if (!name) return FALLBACK_PALETTE[index % FALLBACK_PALETTE.length];
        const key = name.toLowerCase().trim();
        if (CATEGORY_COLORS[key]) return CATEGORY_COLORS[key];
        return FALLBACK_PALETTE[index % FALLBACK_PALETTE.length];
    }

    function formatCurrency(num) {
        return '₹' + Number(num || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatShortCurrency(num) {
        const val = Number(num || 0);
        if (val >= 10000000) return '₹' + (val / 10000000).toFixed(1) + 'Cr';
        if (val >= 100000) return '₹' + (val / 100000).toFixed(1) + 'L';
        if (val >= 1000) return '₹' + (val / 1000).toFixed(val % 1000 === 0 ? 0 : 1) + 'K';
        return '₹' + val;
    }

    /**
     * Render Interactive Category Donut Chart
 */
    function renderDonutChart(containerId, items, options = {}) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;

        const totalSpent = items.reduce((sum, item) => sum + Number(item.total || item.amount || 0), 0);
        const centerTitle = options.centerTitle || 'Total Spent';
        const isCompact = options.compact || false;

        // If no items or total is 0, render empty donut state
        if (!items || items.length === 0 || totalSpent <= 0) {
            container.innerHTML = `
                <div class="donut-chart-empty">
                    <svg viewBox="0 0 240 240" class="donut-svg">
                        <circle cx="120" cy="120" r="70" fill="none" stroke="var(--border-color, #e2e8f0)" stroke-width="26" />
                    </svg>
                    <div class="donut-center-info">
                        <span class="donut-center-amount">₹0.00</span>
                        <span class="donut-center-label">No Expenses</span>
                    </div>
                </div>
            `;
            return;
        }

        const radius = 70;
        const strokeWidth = isCompact ? 24 : 28;
        const circumference = 2 * Math.PI * radius; // ~439.82
        let currentOffset = 0;

        const processedItems = items.map((item, idx) => {
            const amount = Number(item.total || item.amount || 0);
            const percentage = totalSpent > 0 ? (amount / totalSpent) * 100 : 0;
            const color = item.color || getCategoryColor(item.category || item.name, idx);
            const strokeDash = (percentage / 100) * circumference;

            const segment = {
                category: item.category || item.name || 'Other',
                amount: amount,
                percentage: percentage,
                color: color,
                strokeDash: strokeDash,
                offset: currentOffset,
                index: idx
            };

            currentOffset += strokeDash;
            return segment;
        });

        // Build SVG Donut Segments
        let svgSegments = '';
        processedItems.forEach((seg) => {
            const dashLength = processedItems.length > 1 ? Math.max(1, seg.strokeDash - 2) : seg.strokeDash;
            const gapLength = circumference - dashLength;

            svgSegments += `
                <circle
                    cx="120"
                    cy="120"
                    r="${radius}"
                    fill="none"
                    stroke="${seg.color}"
                    stroke-width="${strokeWidth}"
                    stroke-dasharray="${dashLength} ${gapLength}"
                    stroke-dashoffset="-${seg.offset}"
                    class="donut-segment"
                    data-category="${seg.category}"
                    data-amount="${seg.amount}"
                    data-percent="${seg.percentage.toFixed(1)}"
                    data-color="${seg.color}"
                    data-index="${seg.index}"
                    style="transform: rotate(-90deg); transform-origin: 120px 120px; transition: stroke-width 0.25s ease, opacity 0.25s ease;"
                />
            `;
        });

        // Build Legend Items (Matching Image 4)
        let legendHtml = '<div class="donut-legend">';
        processedItems.forEach((seg) => {
            legendHtml += `
                <div class="donut-legend-item" data-index="${seg.index}">
                    <span class="legend-color-dot" style="background-color: ${seg.color};"></span>
                    <span class="legend-category-name">${escapeHtml(seg.category)}</span>
                    <span class="legend-percent">${seg.percentage.toFixed(1)}%</span>
                    <span class="legend-amount">${formatCurrency(seg.amount)}</span>
                </div>
            `;
        });
        legendHtml += '</div>';

        // Render full component HTML
        container.innerHTML = `
            <div class="donut-chart-container ${isCompact ? 'compact' : ''}">
                <div class="donut-visual-wrap">
                    <svg viewBox="0 0 240 240" class="donut-svg">
                        <circle cx="120" cy="120" r="${radius}" fill="none" stroke="var(--border-color, #f1f5f9)" stroke-width="${strokeWidth}" opacity="0.4" />
                        ${svgSegments}
                    </svg>
                    <div class="donut-center-info" id="${container.id || 'chart'}-center">
                        <span class="donut-center-amount" id="${container.id || 'chart'}-center-val">${formatCurrency(totalSpent)}</span>
                        <span class="donut-center-label" id="${container.id || 'chart'}-center-lbl">${centerTitle}</span>
                    </div>
                </div>
                ${legendHtml}
            </div>
        `;

        // Interactive hover binding
        const centerVal = container.querySelector('.donut-center-amount');
        const centerLbl = container.querySelector('.donut-center-label');
        const segments = container.querySelectorAll('.donut-segment');
        const legendItems = container.querySelectorAll('.donut-legend-item');

        function activateSegment(idx) {
            const seg = processedItems[idx];
            if (!seg) return;

            segments.forEach((s, sIdx) => {
                if (sIdx === idx) {
                    s.setAttribute('stroke-width', strokeWidth + 6);
                    s.style.opacity = '1';
                } else {
                    s.setAttribute('stroke-width', strokeWidth);
                    s.style.opacity = '0.45';
                }
            });

            legendItems.forEach((l, lIdx) => {
                if (lIdx === idx) {
                    l.classList.add('is-active');
                } else {
                    l.classList.remove('is-active');
                }
            });

            if (centerVal && centerLbl) {
                centerVal.textContent = formatCurrency(seg.amount);
                centerLbl.textContent = `${seg.category} (${seg.percentage.toFixed(1)}%)`;
                centerLbl.style.color = seg.color;
            }
        }

        function resetSegments() {
            segments.forEach((s) => {
                s.setAttribute('stroke-width', strokeWidth);
                s.style.opacity = '1';
            });
            legendItems.forEach((l) => l.classList.remove('is-active'));
            if (centerVal && centerLbl) {
                centerVal.textContent = formatCurrency(totalSpent);
                centerLbl.textContent = centerTitle;
                centerLbl.style.color = '';
            }
        }

        segments.forEach((segment) => {
            const idx = parseInt(segment.getAttribute('data-index'), 10);
            segment.addEventListener('mouseenter', () => activateSegment(idx));
            segment.addEventListener('mouseleave', resetSegments);
            segment.addEventListener('click', (e) => {
                e.stopPropagation();
                activateSegment(idx);
            });
        });

        legendItems.forEach((item) => {
            const idx = parseInt(item.getAttribute('data-index'), 10);
            item.addEventListener('mouseenter', () => activateSegment(idx));
            item.addEventListener('mouseleave', resetSegments);
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                activateSegment(idx);
            });
        });

        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) {
                resetSegments();
            }
        });
    }

    /**
     * Render Modern Vertical Bar Chart (Clean: X-Axis Labels Hidden, Interactive Hover/Tap Display)
 */
    function renderVerticalBarChart(containerId, items, options = {}) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;

        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="barchart-empty">
                    <p class="empty-text">No spending data to display for this period.</p>
                </div>
            `;
            return;
        }

        const maxVal = Math.max(...items.map(item => Number(item.total || item.amount || 0)), 0);
        const totalSum = items.reduce((sum, item) => sum + Number(item.total || item.amount || 0), 0);

        let scaleMax = 1000;
        if (maxVal > 0) {
            const magnitude = Math.pow(10, Math.floor(Math.log10(maxVal)));
            const factor = maxVal / magnitude;
            if (factor <= 1) scaleMax = 1 * magnitude;
            else if (factor <= 2) scaleMax = 2 * magnitude;
            else if (factor <= 5) scaleMax = 5 * magnitude;
            else scaleMax = 10 * magnitude;
        }
        if (scaleMax < maxVal) scaleMax = Math.ceil(maxVal * 1.15);

        // 4 Y-Axis scale marks
        const yMarks = [
            scaleMax,
            Math.round((scaleMax * 2) / 3),
            Math.round(scaleMax / 3),
            0
        ];

        let gridLinesHtml = '';
        yMarks.forEach((mark) => {
            gridLinesHtml += `
                <div class="barchart-grid-row">
                    <span class="barchart-y-label">${formatShortCurrency(mark)}</span>
                    <div class="barchart-grid-line"></div>
                </div>
            `;
        });

        // Columns: Note that static X-axis labels are REMOVED per user request
        // User views category label and amount by clicking or hovering on the bars
        let columnsHtml = '';
        items.forEach((item, idx) => {
            const amount = Number(item.total || item.amount || 0);
            const heightPercent = scaleMax > 0 ? (amount / scaleMax) * 100 : 0;
            const categoryName = item.category || item.label || item.date || 'Other';
            const color = item.color || getCategoryColor(categoryName, idx);
            const pct = totalSum > 0 ? ((amount / totalSum) * 100).toFixed(1) : '0.0';

            columnsHtml += `
                <div class="barchart-col" data-idx="${idx}" data-amount="${amount}" data-label="${escapeHtml(categoryName)}" data-percent="${pct}">
                    <div class="barchart-bar-wrapper">
                        <div class="barchart-tooltip">
                            <strong>${escapeHtml(categoryName)}</strong>
                            <span>${formatCurrency(amount)}</span>
                            <small class="tooltip-percent">${pct}% of total</small>
                        </div>
                        <div class="barchart-bar-fill" style="height: ${Math.min(100, Math.max(heightPercent, 2))}%; background-color: ${color};">
                        </div>
                    </div>
                </div>
            `;
        });

        const caption = options.caption || 'Understand your spending with charts.';

        container.innerHTML = `
            <div class="barchart-wrapper">
                <div class="barchart-main-area">
                    <div class="barchart-grid">
                        ${gridLinesHtml}
                    </div>
                    <div class="barchart-columns clean-columns">
                        ${columnsHtml}
                    </div>
                </div>
                <div class="barchart-active-display">
                    <span class="active-guide-text">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Tap or hover any bar to view category and amount
                    </span>
                </div>
                ${caption ? `<p class="barchart-caption">${escapeHtml(caption)}</p>` : ''}
            </div>
        `;

        bindBarInteractions(container);
    }

    /**
     * Render Spending Trend Timeline Bar Chart (Non-Scrollable, Fits Perfectly to Container)
 */
    function renderTrendBarChart(containerId, items, options = {}) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;

        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="barchart-empty">
                    <p class="empty-text">No spending trend data available for this month.</p>
                </div>
            `;
            return;
        }

        const maxVal = Math.max(...items.map(item => Number(item.total || item.amount || 0)), 0);
        let scaleMax = 1000;
        if (maxVal > 0) {
            const magnitude = Math.pow(10, Math.floor(Math.log10(maxVal)));
            const factor = maxVal / magnitude;
            if (factor <= 1) scaleMax = 1 * magnitude;
            else if (factor <= 2) scaleMax = 2 * magnitude;
            else if (factor <= 5) scaleMax = 5 * magnitude;
            else scaleMax = 10 * magnitude;
        }
        if (scaleMax < maxVal) scaleMax = Math.ceil(maxVal * 1.15);

        const yMarks = [scaleMax, Math.round(scaleMax / 2), 0];

        let gridLinesHtml = '';
        yMarks.forEach((mark) => {
            gridLinesHtml += `
                <div class="barchart-grid-row">
                    <span class="barchart-y-label">${formatShortCurrency(mark)}</span>
                    <div class="barchart-grid-line"></div>
                </div>
            `;
        });

        // Columns: NON-SCROLLABLE (fits 100% width) & X-axis labels hidden on chart itself, accessible via hover/click
        let columnsHtml = '';
        items.forEach((item, idx) => {
            const amount = Number(item.total || item.amount || 0);
            const heightPercent = scaleMax > 0 ? (amount / scaleMax) * 100 : 0;
            const dateLabel = item.dateLabel || item.date || item.month || `Day ${idx + 1}`;
            const isPeak = amount === maxVal && maxVal > 0;
            const barColor = isPeak ? '#2563eb' : '#3b82f6';

            columnsHtml += `
                <div class="barchart-col trend-col ${isPeak ? 'is-peak' : ''}" data-idx="${idx}" data-amount="${amount}" data-label="${escapeHtml(dateLabel)}">
                    <div class="barchart-bar-wrapper">
                        <div class="barchart-tooltip">
                            <strong>${escapeHtml(dateLabel)}</strong>
                            <span>${formatCurrency(amount)}</span>
                            ${isPeak ? '<small class="peak-badge">Peak Day</small>' : ''}
                        </div>
                        <div class="barchart-bar-fill" style="height: ${Math.min(100, Math.max(heightPercent, 2))}%; background-color: ${barColor};">
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = `
            <div class="barchart-wrapper trend-barchart">
                <div class="barchart-main-area">
                    <div class="barchart-grid">
                        ${gridLinesHtml}
                    </div>
                    <div class="barchart-columns fit-trend-columns">
                        ${columnsHtml}
                    </div>
                </div>
                <div class="barchart-active-display">
                    <span class="active-guide-text">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Tap or hover any day bar to view date and spending
                    </span>
                </div>
            </div>
        `;

        bindBarInteractions(container);
    }

    /**
     * Shared Bar Interactivity: Clicking or Hovering displays label & amount
 */
    function bindBarInteractions(container) {
        const cols = container.querySelectorAll('.barchart-col');
        const activeDisplay = container.querySelector('.barchart-active-display');
        const defaultGuideHtml = activeDisplay ? activeDisplay.innerHTML : '';

        function highlightBar(col) {
            cols.forEach(c => c.classList.remove('is-active-bar'));
            col.classList.add('is-active-bar');

            if (activeDisplay) {
                const label = col.getAttribute('data-label') || '';
                const amount = Number(col.getAttribute('data-amount') || 0);
                const percent = col.getAttribute('data-percent');
                const fillEl = col.querySelector('.barchart-bar-fill');
                const color = fillEl ? fillEl.style.backgroundColor : '#3b82f6';

                activeDisplay.innerHTML = `
                    <span class="active-dot" style="background-color: ${color};"></span>
                    <strong class="active-bar-label">${escapeHtml(label)}</strong>:
                    <span class="active-bar-val">${formatCurrency(amount)}</span>
                    ${percent ? `<span class="active-bar-pct">(${percent}%)</span>` : ''}
                `;
                activeDisplay.classList.add('has-selection');
            }
        }

        function resetBar(col) {
            col.classList.remove('is-active-bar');
            if (activeDisplay) {
                activeDisplay.innerHTML = defaultGuideHtml;
                activeDisplay.classList.remove('has-selection');
            }
        }

        cols.forEach(col => {
            col.addEventListener('mouseenter', () => highlightBar(col));
            col.addEventListener('mouseleave', () => resetBar(col));

            // Mobile click / touch support
            col.addEventListener('click', (e) => {
                e.stopPropagation();
                highlightBar(col);
            });
        });

        // Click outside clears selected bar
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) {
                cols.forEach(c => c.classList.remove('is-active-bar'));
                if (activeDisplay) {
                    activeDisplay.innerHTML = defaultGuideHtml;
                    activeDisplay.classList.remove('has-selection');
                }
            }
        });
    }

    /**
     * Render Circular Budget Gauge Meter
 */
    function renderBudgetGauge(containerId, percentage, spent, budget) {
        const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
        if (!container) return;

        const pct = Math.max(0, Math.min(percentage, 100));
        const radius = 60;
        const circumference = 2 * Math.PI * radius; // ~376.99
        const dashoffset = circumference - (pct / 100) * circumference;

        let statusColor = '#10b981'; // green
        if (percentage >= 100) statusColor = '#ef4444'; // danger red
        else if (percentage >= 80) statusColor = '#f59e0b'; // warning amber

        container.innerHTML = `
            <div class="budget-gauge-wrap">
                <svg viewBox="0 0 160 160" class="gauge-svg">
                    <circle cx="80" cy="80" r="${radius}" fill="none" stroke="var(--border-color, #e2e8f0)" stroke-width="14" opacity="0.5" />
                    <circle
                        cx="80"
                        cy="80"
                        r="${radius}"
                        fill="none"
                        stroke="${statusColor}"
                        stroke-width="14"
                        stroke-linecap="round"
                        stroke-dasharray="${circumference}"
                        stroke-dashoffset="${dashoffset}"
                        style="transform: rotate(-90deg); transform-origin: 80px 80px; transition: stroke-dashoffset 0.8s ease;"
                    />
                </svg>
                <div class="budget-gauge-center">
                    <span class="budget-gauge-pct" style="color: ${statusColor};">${percentage.toFixed(1)}%</span>
                    <span class="budget-gauge-lbl">Budget Used</span>
                </div>
            </div>
        `;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Expose API to global window
    window.ExpenseCharts = {
        renderDonutChart,
        renderVerticalBarChart,
        renderTrendBarChart,
        renderBudgetGauge,
        getCategoryColor,
        formatCurrency,
        formatShortCurrency
    };
})(window);
