document.addEventListener('DOMContentLoaded', function() {
    // Handle spreadsheet inputs
    const spreadsheetInputs = document.querySelectorAll('.spreadsheet-input');
    spreadsheetInputs.forEach(input => {
        input.addEventListener('input', updateComputedValuesForDate);
        input.addEventListener('change', updateComputedValuesForDate);
    });
    
    // Update all computed values on load
    updateAllComputedValues();
    
    // Handle store pills
    const storePills = document.querySelectorAll('.store-pill');
    storePills.forEach(pill => {
        pill.addEventListener('click', function(e) {
            e.preventDefault();
            const storeId = this.dataset.storeId;
            const url = new URL(window.location);
            url.searchParams.set('store', storeId);
            window.location.href = url.toString();
        });
    });
    
    // Handle date input
    const dateInput = document.getElementById('entry-date');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const url = new URL(window.location);
            url.searchParams.set('date', this.value);
            window.location.href = url.toString();
        });
    }
    
    // Initialize chart (or attach legend/controls if chart was already created inline)
    if (window.kpiChart) {
        kpiChart = window.kpiChart;
        if (typeof createCustomLegend === 'function') createCustomLegend();
        if (typeof setupChartControls === 'function') setupChartControls();
    } else {
        initializeChart();
    }
    
    // Daily on-hand cell saves (legacy onhand-cell-input)
    document.querySelectorAll('.onhand-cell-input').forEach(function(el) {
        el.addEventListener('change', saveOnHandCell);
        el.addEventListener('blur', saveOnHandCell);
    });
    // V2: daily sales inputs
    document.querySelectorAll('.daily-sale-input').forEach(function(el) {
        el.addEventListener('change', saveDailySale);
        el.addEventListener('blur', saveDailySale);
    });
    // V2: daily purchases (manual part; total = received + manual)
    document.querySelectorAll('.daily-purchase-input').forEach(function(el) {
        el.addEventListener('change', saveDailyPurchase);
        el.addEventListener('blur', saveDailyPurchase);
    });
    // V2: starting inventory (snapshot for day before first)
    document.querySelectorAll('.starting-inventory-input').forEach(function(el) {
        el.addEventListener('change', saveStartingSnapshot);
        el.addEventListener('blur', saveStartingSnapshot);
    });
    // V2: Update all (recalc on hand) button
    const btnUpdateDailyOnHand = document.getElementById('btn-update-daily-onhand');
    if (btnUpdateDailyOnHand) {
        btnUpdateDailyOnHand.addEventListener('click', updateDailyOnHandAll);
    }
    // Scroll to inventory chart when arriving via SKU link (#inventory-chart)
    if (window.location.hash === '#inventory-chart') {
        const chartEl = document.getElementById('inventory-chart');
        if (chartEl) {
            setTimeout(function() {
                chartEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        }
    }
});

function updateComputedValuesForDate(event) {
    const input = event.target;
    if (!input.dataset.metric) return;
    const date = input.dataset.date;
    if (!date) return;
    
    // Get all inputs for this date
    const bankInput = document.querySelector(`input[data-date="${date}"][data-metric="bank_balance"]`);
    const safeInput = document.querySelector(`input[data-date="${date}"][data-metric="safe_balance"]`);
    const salesInput = document.querySelector(`input[data-date="${date}"][data-metric="sales_today"]`);
    const cogsInput = document.querySelector(`input[data-date="${date}"][data-metric="cogs_today"]`);
    const laborInput = document.querySelector(`input[data-date="${date}"][data-metric="labor_today"]`);
    const overheadInput = document.querySelector(`input[data-date="${date}"][data-metric="avg_daily_overhead"]`);
    
    const bank = parseFloat(bankInput?.value || 0);
    const safe = parseFloat(safeInput?.value || 0);
    const sales = parseFloat(salesInput?.value || 0);
    const cogs = parseFloat(cogsInput?.value || 0);
    const labor = parseFloat(laborInput?.value || 0);
    const overhead = parseFloat(overheadInput?.value || 0);
    
    // Update cash available
    const cashAvailable = bank + safe;
    const cashAvailableCell = document.querySelector(`.computed-cell[data-date="${date}"][data-metric="cash_available"]`);
    if (cashAvailableCell) {
        const valueEl = cashAvailableCell.querySelector('.computed-value');
        if (valueEl) {
            valueEl.textContent = formatCurrency(cashAvailable);
        }
    }
    
    // Update profit
    const profit = sales - cogs - labor - overhead;
    const profitCell = document.querySelector(`.computed-cell[data-date="${date}"][data-metric="profit"]`);
    if (profitCell) {
        const valueEl = profitCell.querySelector('.computed-value');
        if (valueEl) {
            valueEl.textContent = formatCurrency(profit);
            valueEl.className = 'computed-value' + (profit < 0 ? ' negative' : '');
        }
    }
}

function updateAllComputedValues() {
    // Get all unique dates from inputs
    const dateInputs = document.querySelectorAll('.spreadsheet-input[data-date]');
    const dates = new Set();
    dateInputs.forEach(input => {
        if (input.dataset.date) {
            dates.add(input.dataset.date);
        }
    });
    
    // Update computed values for each date
    dates.forEach(date => {
        const bankInput = document.querySelector(`input[data-date="${date}"][data-metric="bank_balance"]`);
        const safeInput = document.querySelector(`input[data-date="${date}"][data-metric="safe_balance"]`);
        const salesInput = document.querySelector(`input[data-date="${date}"][data-metric="sales_today"]`);
        const cogsInput = document.querySelector(`input[data-date="${date}"][data-metric="cogs_today"]`);
        const laborInput = document.querySelector(`input[data-date="${date}"][data-metric="labor_today"]`);
        const overheadInput = document.querySelector(`input[data-date="${date}"][data-metric="avg_daily_overhead"]`);
        
        if (!bankInput || !safeInput || !salesInput || !cogsInput || !laborInput || !overheadInput) return;
        
        const bank = parseFloat(bankInput.value || 0);
        const safe = parseFloat(safeInput.value || 0);
        const sales = parseFloat(salesInput.value || 0);
        const cogs = parseFloat(cogsInput.value || 0);
        const labor = parseFloat(laborInput.value || 0);
        const overhead = parseFloat(overheadInput.value || 0);
        
        // Update cash available
        const cashAvailable = bank + safe;
        const cashAvailableCell = document.querySelector(`.computed-cell[data-date="${date}"][data-metric="cash_available"]`);
        if (cashAvailableCell) {
            const valueEl = cashAvailableCell.querySelector('.computed-value');
            if (valueEl) {
                valueEl.textContent = formatCurrency(cashAvailable);
            }
        }
        
        // Update profit
        const profit = sales - cogs - labor - overhead;
        const profitCell = document.querySelector(`.computed-cell[data-date="${date}"][data-metric="profit"]`);
        if (profitCell) {
            const valueEl = profitCell.querySelector('.computed-value');
            if (valueEl) {
                valueEl.textContent = formatCurrency(profit);
                valueEl.className = 'computed-value' + (profit < 0 ? ' negative' : '');
            }
        }
    });
}

// Legacy function for backward compatibility
function calculateComputedValues() {
    updateAllComputedValues();
}

function formatCurrency(amount) {
    return '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatPercentage(value) {
    return value.toFixed(2) + '%';
}

function changeView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    if (view === 'custom') {
        const customDays = document.getElementById('custom-days')?.value || 7;
        url.searchParams.set('days', customDays);
    } else {
        url.searchParams.delete('days');
    }
    window.location.href = url.toString();
}

function changeCustomDays(days) {
    const url = new URL(window.location);
    url.searchParams.set('view', 'custom');
    url.searchParams.set('days', days);
    window.location.href = url.toString();
}

// Chart initialization
let kpiChart = null;
function getKpiChart() {
    var canvas = document.getElementById('kpiChart');
    if (canvas && typeof Chart !== 'undefined' && Chart.getChart) {
        var bound = Chart.getChart(canvas);
        if (bound) {
            kpiChart = bound;
            if (window.kpiChart !== bound) window.kpiChart = bound;
            return bound;
        }
    }
    var ch = kpiChart || window.kpiChart || null;
    if (ch && !kpiChart) kpiChart = ch;
    return ch;
}

function initializeChart() {
    const chartCanvas = document.getElementById('kpiChart');
    const chartData = typeof window.chartData !== 'undefined' ? window.chartData : (typeof chartData !== 'undefined' ? chartData : null);
    if (!chartCanvas || typeof Chart === 'undefined') return;
    if (!chartData || !chartData.labels || !chartData.datasets) {
        // Fallback: render chart with empty data so canvas at least shows
        const labels = ['No data'];
        const emptyData = { labels: labels, datasets: [{ label: 'Sales', data: [0], borderColor: 'rgb(52, 152, 219)', backgroundColor: 'rgba(52, 152, 219, 0.1)', fill: true, tension: 0.4, hidden: false }] };
        if (kpiChart) kpiChart.destroy();
        kpiChart = new Chart(chartCanvas.getContext('2d'), { type: 'line', data: emptyData, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } } });
        return;
    }
    const ctx = chartCanvas.getContext('2d');
    if (kpiChart) {
        kpiChart.destroy();
    }
    kpiChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false // We'll use custom legend
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label === 'Labor %') {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + '%';
                                }
                                return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(1) + '%';
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

    // Create custom legend with clickable items
    createCustomLegend();

    // Add show all / hide all buttons
    setupChartControls();
}

function setupChartControls() {
    const showAllBtn = document.getElementById('show-all-chart');
    const hideAllBtn = document.getElementById('hide-all-chart');

    document.querySelectorAll('.kpi-series-btn').forEach(function(btn) {
        if (btn.dataset.kpiWired) return;
        btn.dataset.kpiWired = '1';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var index = parseInt(this.getAttribute('data-index'), 10);
            if (!isNaN(index)) toggleDataset(index, null);
        });
    });

    if (showAllBtn) {
        showAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showAllDatasets();
        });
    }

    if (hideAllBtn) {
        hideAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideAllDatasets();
        });
    }

    updateKpiButtonStates();
}

function setKpiDatasetVisibility(ch, index, visible) {
    if (typeof ch.setDatasetVisibility === 'function') {
        ch.setDatasetVisibility(index, visible);
        return;
    }
    var meta = ch.getDatasetMeta(index);
    if (meta) meta.hidden = !visible;
    if (ch.data.datasets[index]) ch.data.datasets[index].hidden = !visible;
}

function getKpiDatasetVisible(ch, index) {
    if (typeof ch.isDatasetVisible === 'function') return ch.isDatasetVisible(index);
    var meta = ch.getDatasetMeta(index);
    if (meta && typeof meta.hidden === 'boolean') return !meta.hidden;
    var ds = ch.data.datasets[index];
    if (ds && typeof ds.hidden === 'boolean') return !ds.hidden;
    return true;
}

function showAllDatasets() {
    var ch = getKpiChart();
    if (!ch) return;
    for (var i = 0; i < ch.data.datasets.length; i++) setKpiDatasetVisibility(ch, i, true);
    ch.update('none');
    updateLegendStates();
}

function hideAllDatasets() {
    var ch = getKpiChart();
    if (!ch) return;
    for (var i = 0; i < ch.data.datasets.length; i++) setKpiDatasetVisibility(ch, i, false);
    ch.update('none');
    updateLegendStates();
}

function updateLegendStates() {
    var ch = getKpiChart();
    if (!ch) return;
    var legendContainer = document.getElementById('chart-legend');
    if (legendContainer) {
        var allItems = legendContainer.querySelectorAll('.legend-item');
        allItems.forEach(function(item, idx) {
            var visible = getKpiDatasetVisible(ch, idx);
            if (visible) item.classList.remove('inactive'); else item.classList.add('inactive');
        });
    }
    updateKpiButtonStates();
}

function updateKpiButtonStates() {
    var ch = getKpiChart();
    if (!ch) return;
    document.querySelectorAll('.kpi-series-btn').forEach(function(btn) {
        var index = parseInt(btn.getAttribute('data-index'), 10);
        if (isNaN(index)) return;
        var visible = getKpiDatasetVisible(ch, index);
        if (visible) btn.classList.remove('inactive'); else btn.classList.add('inactive');
    });
}

// Chart will be initialized in DOMContentLoaded above

// Collapse functions
function toggleChartCollapse() {
    const content = document.getElementById('chart-content');
    const icon = document.getElementById('chart-toggle-icon');
    if (content && icon) {
        const isCollapsed = content.classList.contains('collapsed');
        if (isCollapsed) {
            content.classList.remove('collapsed');
            icon.textContent = '▼';
        } else {
            content.classList.add('collapsed');
            icon.textContent = '▶';
        }
    }
}

function toggleInventoryCollapse() {
    const content = document.getElementById('inventory-content');
    const icon = document.getElementById('inventory-toggle-icon');
    if (content && icon) {
        const isCollapsed = content.classList.contains('collapsed');
        if (isCollapsed) {
            content.classList.remove('collapsed');
            icon.textContent = '▼';
        } else {
            content.classList.add('collapsed');
            icon.textContent = '▶';
        }
    }
}

// Inventory modal functions
function showProductModal(productId = null) {
    const modal = document.getElementById('productModal');
    const form = document.getElementById('productForm');
    const title = document.getElementById('productModalTitle');
    
    if (productId) {
        // Load product data via AJAX or from data attribute
        title.textContent = 'Edit Product';
        document.getElementById('product_id').value = productId;
        // TODO: Load product data
    } else {
        title.textContent = 'Add Product';
        form.reset();
        document.getElementById('product_id').value = '';
    }
    
    modal.style.display = 'block';
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
}

function showVendorModal(vendorId = null) {
    const modal = document.getElementById('vendorModal');
    const form = document.getElementById('vendorForm');
    const title = document.getElementById('vendorModalTitle');
    
    if (vendorId) {
        title.textContent = 'Edit Vendor';
        document.getElementById('vendor_id').value = vendorId;
        const u = new URL(window.location.href);
        u.searchParams.set('api', 'vendor');
        u.searchParams.set('id', vendorId);
        fetch(u.toString())
            .then(r => r.json())
            .then(v => {
                if (v.error) { alert(v.error); return; }
                document.getElementById('vendor_name').value = v.name || '';
                document.getElementById('contact_name').value = v.contact_name || '';
                document.getElementById('phone').value = v.phone || '';
                document.getElementById('email').value = v.email || '';
                document.getElementById('order_method').value = v.order_method || '';
                document.getElementById('cutoff_time').value = v.cutoff_time || '';
                document.getElementById('typical_lead_time').value = v.typical_lead_time || '';
                document.getElementById('shipping_speed_notes').value = v.shipping_speed_notes || '';
                document.getElementById('free_ship_threshold').value = v.free_ship_threshold || '';
                document.getElementById('account_info').value = v.account_info || '';
                document.getElementById('password').value = v.password || '';
                document.getElementById('vendor_notes').value = v.notes || '';
                const rating = Math.max(1, Math.min(5, parseInt(v.rating, 10) || 3));
                document.getElementById('vendor_rating').value = String(rating);
                document.getElementById('is_preferred').checked = !!v.is_preferred;
                document.getElementById('is_active').checked = v.is_active !== false;
            })
            .catch(e => { console.error(e); alert('Could not load vendor.'); });
    } else {
        title.textContent = 'Add Vendor';
        form.reset();
        document.getElementById('vendor_id').value = '';
        document.getElementById('vendor_rating').value = '3';
        document.getElementById('is_active').checked = true;
    }
    
    modal.style.display = 'block';
}

function closeVendorModal() {
    document.getElementById('vendorModal').style.display = 'none';
}

function editInventory(inventoryId) {
    const modal = document.getElementById('inventoryModal');
    const title = document.getElementById('inventory-modal-title');
    const productSelector = document.getElementById('inventory-product-selector');
    const productIdHidden = document.getElementById('inventory_product_id');
    const inventoryIdInput = document.getElementById('inventory_id');

    // Hide product selector for editing (product is already set)
    productSelector.style.display = 'none';
    title.textContent = 'Edit Inventory';
    
    const u = new URL(window.location.href);
    u.searchParams.set('api', 'inventory');
    u.searchParams.set('id', inventoryId);
    fetch(u.toString())
        .then(r => r.json())
        .then(inv => {
            if (inv.error) { alert(inv.error); return; }
            inventoryIdInput.value = inv.id || '';
            productIdHidden.value = inv.product_id || '';
            document.getElementById('on_hand').value = inv.on_hand != null ? Math.round(parseFloat(inv.on_hand)) : '';
            document.getElementById('avg_daily_usage').value = inv.avg_daily_usage ?? '';
            document.getElementById('days_of_stock').value = inv.days_of_stock ?? 7;
            document.getElementById('reorder_point').value = inv.reorder_point ?? '';
            document.getElementById('target_max').value = inv.target_max ?? '';
            document.getElementById('substitution_product_id').value = inv.substitution_product_id || '';
            const subSelect = document.getElementById('substitution_product_id');
            [].forEach.call(subSelect.options, function(opt) {
                opt.disabled = false;
                if (opt.value && parseInt(opt.value, 10) === parseInt(inv.product_id, 10)) opt.disabled = true;
            });
            document.getElementById('inventory_vendor_id').value = inv.vendor_id || '';
            document.getElementById('vendor_sku').value = inv.vendor_sku || '';
            document.getElementById('vendor_link').value = inv.vendor_link || '';
            document.getElementById('lead_time_days').value = inv.lead_time_days ?? 0;
            document.getElementById('unit_cost').value = inv.unit_cost ?? '';
            document.getElementById('inventory_notes').value = inv.notes || '';
        })
        .catch(e => { console.error(e); alert('Could not load inventory.'); });
    modal.style.display = 'block';
}

function closeInventoryModal() {
    document.getElementById('inventoryModal').style.display = 'none';
}

function showInventoryModal() {
    const modal = document.getElementById('inventoryModal');
    const title = document.getElementById('inventory-modal-title');
    const form = document.getElementById('inventoryForm');
    const productSelector = document.getElementById('inventory-product-selector');
    const productIdHidden = document.getElementById('inventory_product_id');
    const productIdSelect = document.getElementById('inventory_product_id_select');
    const inventoryId = document.getElementById('inventory_id');
    
    // Reset form
    title.textContent = 'Add Product to Inventory';
    form.reset();
    inventoryId.value = '';
    productIdHidden.value = '';
    if (productIdSelect) {
        productIdSelect.value = '';
    }
    
    // Show product selector for new inventory
    if (productSelector) {
        productSelector.style.display = 'block';
    }
    
    // Update hidden product_id when select changes
    if (productIdSelect && productIdHidden) {
        productIdSelect.onchange = function() {
            productIdHidden.value = this.value;
        };
    }
    
    // Set default values
    const onHandEl = document.getElementById('on_hand');
    if (onHandEl) onHandEl.value = '0';
    const avgDailyEl = document.getElementById('avg_daily_usage');
    if (avgDailyEl) avgDailyEl.value = '0';
    const daysStockEl = document.getElementById('days_of_stock');
    if (daysStockEl) daysStockEl.value = '7';
    const reorderEl = document.getElementById('reorder_point');
    if (reorderEl) reorderEl.value = '0';
    const targetEl = document.getElementById('target_max');
    if (targetEl) targetEl.value = '0';
    const leadTimeEl = document.getElementById('lead_time_days');
    if (leadTimeEl) leadTimeEl.value = '0';
    const unitCostEl = document.getElementById('unit_cost');
    if (unitCostEl) unitCostEl.value = '0';
    
    modal.style.display = 'block';
}

function showAddProductToGridModal() {
    // Same as showInventoryModal - opens modal to add product to inventory
    showInventoryModal();
}

function createOrder(productId, vendorId, unitCost, suggestedQty) {
    const modal = document.getElementById('orderModal');
    document.getElementById('order_quantity').value = suggestedQty || 0;
    document.getElementById('order_unit_cost').value = unitCost || 0;
    document.getElementById('order_product_id').value = productId || '';
    document.getElementById('order_vendor_id').value = vendorId || '';
    modal.style.display = 'block';
}

function markOrderReceived(orderId, productId, storeId, quantity) {
    if (!confirm(`Mark order as received? This will add ${quantity} units to inventory.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('mark_received', '1');
    formData.append('order_id', orderId);
    formData.append('product_id', productId);
    formData.append('store_id', storeId);
    formData.append('quantity', quantity);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to show updated inventory
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to mark order as received'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking order as received');
    });
}

function closeOrderModal() {
    document.getElementById('orderModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['productModal', 'vendorModal', 'inventoryModal', 'orderModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

function changeInventorySort(sortBy) {
    const url = new URL(window.location);
    url.searchParams.set('inventory_sort', sortBy);
    window.location.href = url.toString();
}

function calculateInventoryTargets() {
    const avgDailyUsage = parseFloat(document.getElementById('avg_daily_usage')?.value || 0);
    const leadTimeDays = parseFloat(document.getElementById('lead_time_days')?.value || 0);
    const daysOfStock = parseFloat(document.getElementById('days_of_stock')?.value || 7);
    
    if (avgDailyUsage > 0) {
        // Calculate reorder point: (avg daily usage * lead time) + (avg daily usage * days of stock)
        const reorderPoint = (avgDailyUsage * leadTimeDays) + (avgDailyUsage * daysOfStock);
        document.getElementById('reorder_point').value = reorderPoint.toFixed(2);
        
        // Calculate target max: avg daily usage * days of stock
        const targetMax = avgDailyUsage * daysOfStock;
        document.getElementById('target_max').value = targetMax.toFixed(2);
    }
}

function saveOnHandCell(ev) {
    const input = ev.target;
    if (!input.classList.contains('onhand-cell-input')) return;
    const storeId = input.dataset.storeId;
    const productId = input.dataset.productId;
    const date = input.dataset.date;
    const val = input.value.trim();
    const onHand = val === '' ? 0 : parseFloat(val);
    if (!storeId || !productId || !date) return;
    if (val !== '' && (isNaN(onHand) || onHand < 0)) return;
    
    const entryDateEl = document.getElementById('entry-date');
    const entryDate = entryDateEl ? entryDateEl.value : '';

    const formData = new FormData();
    formData.append('save_snapshot', '1');
    formData.append('store_id', storeId);
    formData.append('product_id', productId);
    formData.append('snapshot_date', date);
    formData.append('on_hand', onHand);
    if (entryDate) formData.append('entry_date', entryDate);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) console.warn('Snapshot save failed', data.message);
        })
        .catch(function(e) { console.error('Snapshot save error', e); });
}

// V2: save daily sale (user-entered), then recalc On Hand so displayed values update
function saveDailySale(ev) {
    const input = ev.target;
    if (!input.classList.contains('daily-sale-input')) return;
    const storeId = input.dataset.storeId;
    const productId = input.dataset.productId;
    const date = input.dataset.date;
    const quantity = Math.round(parseFloat(input.value) || 0);
    if (!storeId || !productId || !date) return;
    const formData = new FormData();
    formData.append('save_daily_sale', '1');
    formData.append('store_id', storeId);
    formData.append('product_id', productId);
    formData.append('sale_date', date);
    formData.append('quantity', quantity);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                console.warn('Save daily sale failed', data.message);
                return;
            }
            // Recalc On Hand for the date range so the table updates
            recalcOnHandThenReload(storeId);
        })
        .catch(function(e) { console.error('Save daily sale error', e); });
}

// Call update_daily_on_hand for current grid range; then reload so On Hand always shows updated values
function recalcOnHandThenReload(storeId) {
    const btn = document.getElementById('btn-update-daily-onhand');
    if (!btn) return;
    const startDate = btn.dataset.startDate;
    const endDate = btn.dataset.endDate;
    if (!storeId || !startDate || !endDate) return;
    const formData = new FormData();
    formData.append('update_daily_on_hand', '1');
    formData.append('store_id', storeId);
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(function(e) { console.error('Recalc on hand error', e); });
}

// Update each On Hand cell in the table from snapshots map { product_id: { date: on_hand } }; whole numbers only
function updateOnHandCellsInPlace(snapshots) {
    document.querySelectorAll('.on-hand-computed').forEach(function(cell) {
        const productId = cell.dataset.productId;
        const date = cell.dataset.date;
        const span = cell.querySelector('.on-hand-value');
        if (!span || !productId || !date) return;
        const byProduct = snapshots[productId];
        const raw = byProduct && byProduct[date] !== undefined ? byProduct[date] : null;
        const value = raw !== null && raw !== '' ? Math.round(parseFloat(raw)) : null;
        span.textContent = value !== null ? String(value) : '—';
    });
}

// V2: save daily purchase (manual part; backend stores manual, total = received + manual)
function saveDailyPurchase(ev) {
    const input = ev.target;
    if (!input.classList.contains('daily-purchase-input')) return;
    const storeId = input.dataset.storeId;
    const productId = input.dataset.productId;
    const date = input.dataset.date;
    const received = parseFloat(input.dataset.received || 0) || 0;
    const total = parseFloat(input.value) || 0;
    const manual = Math.max(0, total - received);
    if (!storeId || !productId || !date) return;
    const formData = new FormData();
    formData.append('save_daily_purchase', '1');
    formData.append('store_id', storeId);
    formData.append('product_id', productId);
    formData.append('purchase_date', date);
    formData.append('quantity', manual);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) console.warn('Save daily purchase failed', data.message);
        })
        .catch(function(e) { console.error('Save daily purchase error', e); });
}

// V2: save starting inventory (snapshot for day before first date)
function saveStartingSnapshot(ev) {
    const input = ev.target;
    if (!input.classList.contains('starting-inventory-input')) return;
    const storeId = input.dataset.storeId;
    const productId = input.dataset.productId;
    const snapshotDate = input.dataset.snapshotDate;
    const onHand = parseFloat(input.value) || 0;
    if (!storeId || !productId || !snapshotDate) return;
    const formData = new FormData();
    formData.append('save_snapshot', '1');
    formData.append('store_id', storeId);
    formData.append('product_id', productId);
    formData.append('snapshot_date', snapshotDate);
    formData.append('on_hand', onHand);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) console.warn('Save starting snapshot failed', data.message);
        })
        .catch(function(e) { console.error('Save starting snapshot error', e); });
}

// V2: Update all (recalc on hand for date range; update cells in place or reload)
function updateDailyOnHandAll() {
    const btn = document.getElementById('btn-update-daily-onhand');
    if (!btn) return;
    const storeId = btn.dataset.storeId;
    const startDate = btn.dataset.startDate;
    const endDate = btn.dataset.endDate;
    if (!storeId || !startDate || !endDate) return;
    btn.disabled = true;
    btn.textContent = 'Updating…';
    const formData = new FormData();
    formData.append('update_daily_on_hand', '1');
    formData.append('store_id', storeId);
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.snapshots && typeof data.snapshots === 'object') {
                updateOnHandCellsInPlace(data.snapshots);
            } else if (data.success) {
                window.location.reload();
            }
            btn.disabled = false;
            btn.textContent = 'Update all (recalc On Hand)';
            if (!data.success) {
                alert('Recalc failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.textContent = 'Update all (recalc On Hand)';
            console.error('Update daily on hand error', e);
            alert('Error recalculating. Please try again.');
        });
}

// Quick update on_hand quantity (for daily updates)
function quickUpdateOnHand(input) {
    const inventoryId = input.dataset.inventoryId;
    const onHand = parseFloat(input.value);
    const indicator = input.parentElement.querySelector('.save-indicator');
    
    if (!inventoryId || isNaN(onHand) || onHand < 0) {
        return;
    }
    
    // Show saving state
    if (indicator) {
        indicator.textContent = '...';
        indicator.style.display = 'inline';
        indicator.style.color = '#666';
    }
    
    const refDate = document.getElementById('entry-date');
    const snapshotDate = refDate ? refDate.value : '';
    
    const formData = new FormData();
    formData.append('quick_update_on_hand', '1');
    formData.append('inventory_id', inventoryId);
    formData.append('on_hand', onHand);
    if (snapshotDate) formData.append('snapshot_date', snapshotDate);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (indicator) {
                indicator.textContent = '✓';
                indicator.style.color = '#27ae60';
                indicator.style.display = 'inline';
                // Hide after 2 seconds
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 2000);
            }
            // Update status badge if needed
            updateInventoryRowStatus(input.closest('tr'), onHand);
        } else {
            if (indicator) {
                indicator.textContent = '✗';
                indicator.style.color = '#e74c3c';
                indicator.style.display = 'inline';
            }
            alert('Error updating: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (indicator) {
            indicator.textContent = '✗';
            indicator.style.color = '#e74c3c';
            indicator.style.display = 'inline';
        }
        alert('Error updating inventory. Please try again.');
    });
}

// Update status badge after on_hand change
function updateInventoryRowStatus(row, onHand) {
    const reorderPoint = parseFloat(row.dataset.reorderPoint || row.querySelector('[data-reorder-point]')?.dataset.reorderPoint || 0);
    const statusBadge = row.querySelector('.status-badge');
    
    if (!statusBadge) return;
    
    let status = 'OK';
    let statusClass = 'status-ok';
    
    if (onHand <= 0) {
        status = 'OUT';
        statusClass = 'status-out';
    } else if (reorderPoint > 0 && onHand <= reorderPoint) {
        status = 'LOW';
        statusClass = 'status-low';
    }
    
    statusBadge.textContent = status;
    statusBadge.className = 'status-badge ' + statusClass;
    row.className = 'status-' + status.toLowerCase();
}

// Store the legend click handler to prevent multiple attachments
let legendClickHandler = null;

function createCustomLegend() {
    if (!kpiChart) return;
    
    const legendContainer = document.getElementById('chart-legend');
    if (!legendContainer) return;
    
    // Remove old event listener if it exists
    if (legendClickHandler) {
        legendContainer.removeEventListener('click', legendClickHandler, true);
    }
    
    // Clear existing content
    legendContainer.innerHTML = '';
    
    // Create new event handler
    legendClickHandler = function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const legendItem = e.target.closest('.legend-item');
        if (!legendItem) return;
        
        const index = parseInt(legendItem.dataset.index);
        if (isNaN(index)) return;
        
        toggleDataset(index, legendItem);
        return false;
    };
    
    // Attach event listener using capture phase
    legendContainer.addEventListener('click', legendClickHandler, true);
    
    kpiChart.data.datasets.forEach((dataset, index) => {
        const legendItem = document.createElement('div');
        legendItem.className = 'legend-item' + (dataset.hidden ? ' inactive' : '');
        legendItem.style.cursor = 'pointer';
        legendItem.setAttribute('role', 'button');
        legendItem.setAttribute('tabindex', '0');
        legendItem.dataset.index = index;
        
        // Prevent text selection
        legendItem.style.userSelect = 'none';
        legendItem.style.webkitUserSelect = 'none';
        legendItem.style.mozUserSelect = 'none';
        
        legendItem.innerHTML = `
            <span class="legend-color" style="background-color: ${dataset.borderColor}"></span>
            <span class="legend-label">${dataset.label}</span>
        `;
        
        // Also add direct click handler as backup
        legendItem.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleDataset(index, this);
            return false;
        };
        
        legendContainer.appendChild(legendItem);
    });
}

function toggleDataset(index, legendItem) {
    var ch = getKpiChart();
    if (!ch) return;
    if (index < 0 || index >= (ch.data.datasets && ch.data.datasets.length)) return;
    var visible = getKpiDatasetVisible(ch, index);
    setKpiDatasetVisibility(ch, index, !visible);
    ch.update('none');
    updateLegendStates();
}
