document.addEventListener('DOMContentLoaded', function() {
    // Load KPI chart data from hidden textarea (no script tag = no parse error)
    var kpiDataEl = document.getElementById('kpi-chart-data');
    if (kpiDataEl) {
        var j = (kpiDataEl.value || kpiDataEl.textContent || '').trim();
        try { window.chartData = j ? JSON.parse(j) : null; } catch (e) { window.chartData = null; }
    } else {
        window.chartData = null;
    }

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
            const storeId = this.dataset.storeId;
            if (!storeId) {
                // Let regular anchor navigation work when no data-store-id is provided.
                return;
            }
            e.preventDefault();
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
    
    // Initialize KPI chart after one frame so canvas has layout and non-zero size
    function runKpiChartInit() {
        if (getKpiChart()) {
            kpiChart = getKpiChart();
            if (typeof createCustomLegend === 'function') createCustomLegend();
            if (typeof setupChartControls === 'function') setupChartControls();
            return;
        }
        if (!initializeChart() && typeof Chart === 'undefined') {
            var attempts = 0;
            var t = setInterval(function() {
                if (initializeChart() || ++attempts > 40) clearInterval(t);
            }, 100);
        }
    }
    requestAnimationFrame(runKpiChartInit);

    // Inventory chart (data from #inventory-chart-data)
    initInventoryChartFromData();

    // Daily on-hand cell saves (legacy onhand-cell-input)
    document.querySelectorAll('.onhand-cell-input').forEach(function(el) {
        el.addEventListener('change', saveOnHandCell);
        el.addEventListener('blur', saveOnHandCell);
    });
    // V2: daily sales inputs
    document.querySelectorAll('.daily-sale-input').forEach(function(el) {
        // change already fires when tabbing out after edit; avoid duplicate blur submit
        el.addEventListener('change', saveDailySale);
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
    // Order button in Qty Ordered column: open same Add Order modal with this row's product/vendor pre-filled
    document.addEventListener('click', function(e) {
        var btn = e.target && e.target.closest ? e.target.closest('.order-row-btn') : null;
        if (!btn) return;
        e.preventDefault();
        var productId = btn.getAttribute('data-product-id') || '';
        var vendorId = btn.getAttribute('data-vendor-id') || '';
        var unitCost = btn.getAttribute('data-unit-cost');
        var suggestedQty = btn.getAttribute('data-suggested-qty');
        openOrderModal(productId ? parseInt(productId, 10) : null, vendorId ? parseInt(vendorId, 10) : null, unitCost !== null && unitCost !== '' ? parseFloat(unitCost) : 0, suggestedQty !== null && suggestedQty !== '' ? parseInt(suggestedQty, 10) : 0);
    });
    // Scroll to inventory chart when arriving via SKU link (#inventory-chart)
    if (window.location.hash === '#inventory-chart') {
        const chartEl = document.getElementById('inventory-chart');
        if (chartEl) {
            setTimeout(function() {
                chartEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        }
    }
    // Inventory form: ensure product_id is set (Add mode) and avoid browser validating hidden/visible select
    const inventoryForm = document.getElementById('inventoryForm');
    if (inventoryForm) {
        inventoryForm.addEventListener('submit', function(e) {
            var inventoryIdInput = document.getElementById('inventory_id');
            var productSkuInput = document.getElementById('product_sku');
            if (inventoryIdInput && inventoryIdInput.value && productSkuInput) {
                var original = (productSkuInput.getAttribute('data-original-sku') || '').trim();
                var current = (productSkuInput.value || '').trim();
                if (current !== original) {
                    if (!confirm('You are changing the product SKU/Barcode. The new value must be unique. Continue?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
            var productSelector = document.getElementById('inventory-product-selector');
            var productIdHidden = document.getElementById('inventory_product_id');
            var productIdSelect = document.getElementById('inventory_product_id_select');
            if (productSelector && productSelector.style.display !== 'none' && productIdSelect) {
                productIdHidden.value = productIdSelect.value || '';
            }
            if (!productIdHidden.value || productIdHidden.value === '') {
                e.preventDefault();
                alert('Please select a product.');
                return false;
            }
        });
    }
    // Receive order modal: submit via fetch
    const receiveForm = document.getElementById('receiveOrderForm');
    if (receiveForm) {
        receiveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const qtyInput = document.getElementById('receive_quantity');
            const qty = qtyInput ? Number(qtyInput.value) : 0;
            const maxQty = qtyInput ? Number(qtyInput.max || 0) : 0;
            if (!Number.isFinite(qty) || qty <= 0) {
                alert('Please enter a received quantity greater than 0.');
                return;
            }
            if (maxQty > 0 && qty > maxQty) {
                alert('Received qty cannot exceed ordered qty.');
                return;
            }
            const formData = new FormData(receiveForm);
            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeReceiveOrderModal();
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to mark order as received'));
                    }
                })
                .catch(function() { alert('Error marking order as received'); });
        });
    }

    // Close Edit vendor dropdown when clicking outside
    document.addEventListener('click', function(e) {
        var dropdown = document.querySelector('.vendor-edit-dropdown');
        if (dropdown && !dropdown.contains(e.target)) closeVendorEditDropdown();
    });
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
            return bound;
        }
    }
    return kpiChart || null;
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
        createCustomLegend();
        setupChartControls();
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
    return true;
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

    var ch = getKpiChart();
    if (ch && typeof ch.getDatasetMeta === 'function') updateKpiButtonStates();
}

function setKpiDatasetVisibility(ch, index, visible) {
    if (!ch || typeof ch.getDatasetMeta !== 'function') return;
    if (typeof ch.setDatasetVisibility === 'function') {
        ch.setDatasetVisibility(index, visible);
        return;
    }
    var meta = ch.getDatasetMeta(index);
    if (meta) meta.hidden = !visible;
    if (ch.data && ch.data.datasets && ch.data.datasets[index]) ch.data.datasets[index].hidden = !visible;
}

function getKpiDatasetVisible(ch, index) {
    if (!ch || typeof ch.getDatasetMeta !== 'function') return true;
    if (typeof ch.isDatasetVisible === 'function') return ch.isDatasetVisible(index);
    var meta = ch.getDatasetMeta(index);
    if (meta && typeof meta.hidden === 'boolean') return !meta.hidden;
    var ds = ch.data && ch.data.datasets && ch.data.datasets[index];
    if (ds && typeof ds.hidden === 'boolean') return !ds.hidden;
    return true;
}

function showAllDatasets() {
    var ch = getKpiChart();
    if (!ch || !ch.data || !ch.data.datasets) return;
    for (var i = 0; i < ch.data.datasets.length; i++) setKpiDatasetVisibility(ch, i, true);
    ch.update('none');
    updateLegendStates();
}

function hideAllDatasets() {
    var ch = getKpiChart();
    if (!ch || !ch.data || !ch.data.datasets) return;
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
    if (!ch || !ch.data || !ch.data.datasets || typeof ch.getDatasetMeta !== 'function') return;
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
            // Resize chart when section is shown so it draws correctly
            var ch = getKpiChart();
            if (ch && typeof ch.resize === 'function') {
                setTimeout(function() { ch.resize(); }, 50);
            }
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
                modal.style.display = 'block';
            })
            .catch(e => { console.error(e); alert('Could not load vendor.'); });
    } else {
        title.textContent = 'Add Vendor';
        form.reset();
        document.getElementById('vendor_id').value = '';
        document.getElementById('vendor_rating').value = '3';
        document.getElementById('is_active').checked = true;
        modal.style.display = 'block';
    }
}

function closeVendorModal() {
    document.getElementById('vendorModal').style.display = 'none';
}

function toggleVendorEditDropdown() {
    var el = document.querySelector('.vendor-edit-dropdown');
    if (!el) return;
    el.classList.toggle('open');
}

function selectVendorToEdit(vendorId) {
    closeVendorEditDropdown();
    showVendorModal(vendorId);
}

function closeVendorEditDropdown() {
    var el = document.querySelector('.vendor-edit-dropdown');
    if (el) el.classList.remove('open');
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
            document.getElementById('avg_daily_usage').value = inv.avg_daily_usage != null ? Math.round(parseFloat(inv.avg_daily_usage)) : '';
            document.getElementById('days_of_stock').value = inv.days_of_stock ?? 7;
            document.getElementById('reorder_point').value = inv.reorder_point != null ? Math.round(parseFloat(inv.reorder_point)) : '';
            document.getElementById('target_max').value = inv.target_max != null ? Math.round(parseFloat(inv.target_max)) : '';
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
            var skuRow = document.getElementById('inventory-edit-sku-row');
            var skuInput = document.getElementById('product_sku');
            if (skuRow && skuInput) {
                skuRow.style.display = 'block';
                var skuVal = (inv.sku || '').trim();
                skuInput.value = skuVal;
                skuInput.setAttribute('data-original-sku', skuVal);
            }
            modal.style.display = 'block';
        })
        .catch(e => { console.error(e); alert('Could not load inventory.'); });
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
    var skuRow = document.getElementById('inventory-edit-sku-row');
    if (skuRow) skuRow.style.display = 'none';
    var skuInput = document.getElementById('product_sku');
    if (skuInput) { skuInput.value = ''; skuInput.removeAttribute('data-original-sku'); }
    
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

function openOrderModal(productId, vendorId, unitCost, suggestedQty) {
    const modal = document.getElementById('orderModal');
    if (!modal) return;
    const productEl = document.getElementById('order_product_id');
    const vendorEl = document.getElementById('order_vendor_id');
    if (productEl) productEl.value = productId || '';
    if (vendorEl) vendorEl.value = vendorId || '';
    const qtyEl = document.getElementById('order_quantity');
    const costEl = document.getElementById('order_unit_cost');
    const dateEl = document.getElementById('order_date');
    if (qtyEl) qtyEl.value = suggestedQty != null && suggestedQty !== '' ? suggestedQty : 0;
    if (costEl) costEl.value = unitCost != null && unitCost !== '' ? unitCost : 0;
    if (dateEl) dateEl.value = new Date().toISOString().slice(0, 10);
    modal.style.display = 'block';
}

function createOrder(productId, vendorId, unitCost, suggestedQty) {
    openOrderModal(productId, vendorId, unitCost, suggestedQty);
}

function openReceiveOrderModal(orderId, productId, storeId, orderQty) {
    const modal = document.getElementById('receiveOrderModal');
    if (!modal) return;
    const qtyEl = document.getElementById('receive_quantity');
    const orderedQtyLabel = document.getElementById('receive_order_qty_label');
    const safeOrderQty = Number.isFinite(Number(orderQty)) ? Number(orderQty) : 0;
    document.getElementById('receive_order_id').value = orderId;
    document.getElementById('receive_product_id').value = productId;
    document.getElementById('receive_store_id').value = storeId;
    if (qtyEl) {
        qtyEl.value = safeOrderQty;
        qtyEl.max = String(safeOrderQty);
    }
    if (orderedQtyLabel) orderedQtyLabel.textContent = String(Math.round(safeOrderQty));
    document.getElementById('receive_date').value = new Date().toISOString().slice(0, 10);
    modal.style.display = 'block';
}

function closeReceiveOrderModal() {
    const modal = document.getElementById('receiveOrderModal');
    if (modal) modal.style.display = 'none';
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
    const modals = ['productModal', 'vendorModal', 'inventoryModal', 'orderModal', 'receiveOrderModal'];
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
    if (!url.searchParams.has('inventory_limit')) url.searchParams.set('inventory_limit', '10');
    window.location.href = url.toString();
}

function calculateInventoryTargets() {
    const avgDailyUsage = parseFloat(document.getElementById('avg_daily_usage')?.value || 0);
    const leadTimeDays = parseFloat(document.getElementById('lead_time_days')?.value || 0);
    const daysOfStock = parseFloat(document.getElementById('days_of_stock')?.value || 7);
    
    if (avgDailyUsage > 0) {
        // Calculate reorder point: (avg daily usage * lead time) + (avg daily usage * days of stock)
        const reorderPoint = (avgDailyUsage * leadTimeDays) + (avgDailyUsage * daysOfStock);
        document.getElementById('reorder_point').value = Math.round(reorderPoint);
        
        // Calculate target max: avg daily usage * days of stock
        const targetMax = avgDailyUsage * daysOfStock;
        document.getElementById('target_max').value = Math.round(targetMax);
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
    
    const kpiDateEl = document.getElementById('entry-date');
    const kpiDate = kpiDateEl ? kpiDateEl.value : '';

    const formData = new FormData();
    formData.append('save_snapshot', '1');
    formData.append('store_id', storeId);
    formData.append('product_id', productId);
    formData.append('snapshot_date', date);
    formData.append('on_hand', onHand);
    if (kpiDate) formData.append('entry_date', kpiDate);

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
    const reqToken = String(Date.now()) + Math.random().toString(36).slice(2);
    input.dataset.saleReqToken = reqToken;
    if (!input.dataset.originalTitle) input.dataset.originalTitle = input.title || '';
    input.classList.add('is-saving');
    input.title = 'Saving...';
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                console.warn('Save daily sale failed', data.message);
                return;
            }
            // Recalc On Hand from the edited day forward (faster than full-range recalc)
            return recalcOnHandThenReload(storeId, date);
        })
        .catch(function(e) { console.error('Save daily sale error', e); })
        .finally(function() {
            // Only clear if this is still the latest request for this input
            if (input.dataset.saleReqToken === reqToken) {
                input.classList.remove('is-saving');
                input.title = input.dataset.originalTitle || 'Sales';
                delete input.dataset.saleReqToken;
            }
        });
}

function getInventoryGridRangeMeta() {
    var inputs = Array.from(document.querySelectorAll('.daily-sale-input[data-date]'));
    if (!inputs.length) return null;
    var storeId = (inputs[0].dataset && inputs[0].dataset.storeId) ? inputs[0].dataset.storeId : null;
    var dates = inputs
        .map(function(el) { return el.dataset ? el.dataset.date : null; })
        .filter(function(d) { return !!d; });
    if (!dates.length) return null;
    dates.sort();
    return {
        storeId: storeId,
        startDate: dates[0],
        endDate: dates[dates.length - 1]
    };
}

// Call update_daily_on_hand for current grid range; then reload so On Hand always shows updated values
function recalcOnHandThenReload(storeId, startDateOverride) {
    const btn = document.getElementById('btn-update-daily-onhand');
    const gridMeta = getInventoryGridRangeMeta();
    const startDate = startDateOverride || ((btn && btn.dataset && btn.dataset.startDate) ? btn.dataset.startDate : (gridMeta ? gridMeta.startDate : null));
    const endDate = (btn && btn.dataset && btn.dataset.endDate) ? btn.dataset.endDate : (gridMeta ? gridMeta.endDate : null);
    const effectiveStoreId = storeId || (btn && btn.dataset && btn.dataset.storeId ? btn.dataset.storeId : (gridMeta ? gridMeta.storeId : null));
    if (!effectiveStoreId || !startDate || !endDate) return Promise.resolve(null);
    const formData = new FormData();
    formData.append('update_daily_on_hand', '1');
    formData.append('store_id', effectiveStoreId);
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    return fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (data.snapshots && typeof data.snapshots === 'object') {
                    updateOnHandCellsInPlace(data.snapshots);
                } else {
                    window.location.reload();
                }
            }
            return data;
        })
        .catch(function(e) { console.error('Recalc on hand error', e); return null; });
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
    if (!kpiChart || !kpiChart.data || !kpiChart.data.datasets) return;
    
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
    if (!ch || !ch.data || !ch.data.datasets) return;
    if (index < 0 || index >= ch.data.datasets.length) return;
    var visible = getKpiDatasetVisible(ch, index);
    setKpiDatasetVisibility(ch, index, !visible);
    ch.update('none');
    updateLegendStates();
}

// Inventory chart (data from #inventory-chart-data; no inline script in HTML)
function initInventoryChartFromData() {
    var dataEl = document.getElementById('inventory-chart-data');
    var jsonStr = dataEl ? (dataEl.value || dataEl.textContent || '').trim() : '';
    if (!jsonStr) return;
    var ctx = document.getElementById('inventoryChart');
    if (!ctx || typeof Chart === 'undefined') return;
    try {
        var raw = JSON.parse(jsonStr);
    } catch (e) { return; }
    var labels = raw.labels;
    var onHandData = raw.onHand;
    var salesData = raw.sales;
    var purchasesData = raw.purchases;
    var unitCost = raw.unitCost;
    var priceData = labels.map(function() { return unitCost; });
    if (window.inventoryChart) try { window.inventoryChart.destroy(); } catch (e) {}
    window.inventoryChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: 'On Hand', data: onHandData, borderColor: 'rgb(52, 152, 219)', backgroundColor: 'rgba(52, 152, 219, 0.1)', fill: true, tension: 0.4, yAxisID: 'y', hidden: false },
                { label: 'Sales', data: salesData, borderColor: 'rgb(231, 76, 60)', backgroundColor: 'rgba(231, 76, 60, 0.1)', fill: true, tension: 0.4, yAxisID: 'y', hidden: false },
                { label: 'Purchases', data: purchasesData, borderColor: 'rgb(46, 204, 113)', backgroundColor: 'rgba(46, 204, 113, 0.1)', fill: true, tension: 0.4, yAxisID: 'y', hidden: false },
                { label: 'Price (unit cost)', data: priceData, borderColor: 'rgb(155, 89, 182)', borderDash: [5, 5], fill: false, tension: 0, yAxisID: 'y1', hidden: false }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: true, onClick: function(e) { e.stopPropagation(); }, labels: { color: '#000' } }
            },
            scales: {
                y: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Qty', color: '#000' }, ticks: { color: '#000' } },
                y1: { type: 'linear', position: 'right', beginAtZero: true, title: { display: true, text: 'Price ($)', color: '#000' }, ticks: { color: '#000' }, grid: { drawOnChartArea: false } },
                x: { ticks: { color: '#000' } }
            }
        }
    });
    function updateInventoryToggleButtons() {
        if (!window.inventoryChart) return;
        var m0 = window.inventoryChart.getDatasetMeta(0), m1 = window.inventoryChart.getDatasetMeta(1), m2 = window.inventoryChart.getDatasetMeta(2), m3 = window.inventoryChart.getDatasetMeta(3);
        var el; el = document.getElementById('toggle-onhand'); if (el) el.textContent = m0.hidden ? 'Show On-Hand' : 'Hide On-Hand';
        el = document.getElementById('toggle-sales'); if (el) el.textContent = m1.hidden ? 'Show Sales' : 'Hide Sales';
        el = document.getElementById('toggle-purchases'); if (el) el.textContent = m2.hidden ? 'Show Purchases' : 'Hide Purchases';
        el = document.getElementById('toggle-price'); if (el) el.textContent = m3.hidden ? 'Show Price' : 'Hide Price';
    }
    window.updateInventoryToggleButtons = updateInventoryToggleButtons;
    updateInventoryToggleButtons();
}

function toggleInventoryChartCollapse() {
    var el = document.getElementById('inventory-chart-content');
    var icon = document.getElementById('inventory-chart-toggle-icon');
    if (!el) return;
    el.classList.toggle('collapsed');
    if (icon) icon.textContent = el.classList.contains('collapsed') ? '\u25B6' : '\u25BC';
}
function toggleInventoryChartSeries(seriesName) {
    if (!window.inventoryChart) return;
    var index = -1;
    switch (seriesName) { case 'onhand': index = 0; break; case 'sales': index = 1; break; case 'purchases': index = 2; break; case 'price': index = 3; break; }
    if (index >= 0) {
        var meta = window.inventoryChart.getDatasetMeta(index);
        meta.hidden = !meta.hidden;
        window.inventoryChart.update('none');
        if (window.updateInventoryToggleButtons) window.updateInventoryToggleButtons();
    }
}
function showAllInventorySeries() {
    if (!window.inventoryChart) return;
    window.inventoryChart.data.datasets.forEach(function(d, i) { var m = window.inventoryChart.getDatasetMeta(i); m.hidden = false; });
    window.inventoryChart.update('none');
    if (window.updateInventoryToggleButtons) window.updateInventoryToggleButtons();
}
