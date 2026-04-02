<?php
require_once __DIR__ . '/../config/db.php';

auth_check();
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to create quotations.');
    redirect('index.php');
}

$pageTitle = 'Create Quotation';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Create Quotation</span>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-white text-xl font-light">Manual Quotation Builder</h2>
                <p class="text-mb-subtle text-sm mt-1">Add vehicles, rental types, and charges. Generate a printable quotation.</p>
            </div>
            <button type="button" id="addVehicleBtn"
                class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">+ Add Vehicle</button>
        </div>

        <div id="vehicleList" class="space-y-6"></div>

        <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-5 space-y-4">
            <h3 class="text-white font-light">Charges</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Delivery Charge</label>
                    <input type="number" step="0.01" min="0" id="deliveryCharge"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Return Charge</label>
                    <input type="number" step="0.01" min="0" id="returnCharge"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0.00">
                </div>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-mb-silver">
                        <input type="checkbox" id="additionalToggle"
                            class="h-4 w-4 rounded border-mb-subtle/40 text-mb-accent focus:ring-mb-accent">
                        Additional Charge
                    </label>
                    <input type="number" step="0.01" min="0" id="additionalCharge" disabled
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white/70 focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0.00">
                </div>
            </div>
        </div>

        <!-- Quote Meta (optional customer/quote info) -->
        <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-5 space-y-4">
            <h3 class="text-white font-light">Quote Details <span class="text-mb-subtle text-xs font-normal">(optional — shown on invoice)</span></h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Customer Name</label>
                    <input type="text" id="customerName"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="Full name">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">
                        Quote Reference
                        <span class="ml-1 text-xs text-mb-accent/70">(auto-generated)</span>
                    </label>
                    <div class="flex gap-2 items-center">
                        <input type="text" id="quoteRef" readonly
                            class="w-full bg-mb-black/60 border border-mb-subtle/20 rounded-lg px-4 py-3 text-mb-accent font-mono tracking-wider focus:outline-none text-sm cursor-default"
                            placeholder="Generating...">
                        <button type="button" id="regenQuoteRef" title="Generate new number"
                            class="shrink-0 border border-mb-subtle/20 rounded-lg px-3 py-3 text-mb-subtle hover:text-white hover:border-white/30 transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Valid Until</label>
                    <input type="date" id="validUntil"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button type="button" id="generateQuoteBtn"
                class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Generate Quotation</button>
        </div>
    </div>

    <div id="quotePreview" class="hidden bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-3 no-print">
            <h3 class="text-white font-light text-lg">Quotation Preview</h3>
            <button type="button" id="printQuoteBtn"
                class="border border-mb-subtle/30 text-mb-silver px-4 py-2 rounded-full hover:border-white/30 hover:text-white transition-colors text-sm">Print / Save PDF</button>
        </div>
        <div id="quotePreviewContent"></div>
    </div>
</div>

<template id="vehicleTemplate">
    <div class="vehicle-block bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-5 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div class="text-white font-light">Vehicle</div>
            <button type="button" class="remove-vehicle text-xs text-red-400 hover:underline">Remove</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm text-mb-silver mb-2">Car Name</label>
                <input type="text" class="vehicle-name w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                    placeholder="Brand or Name">
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Model</label>
                <input type="text" class="vehicle-model w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                    placeholder="Model">
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Year</label>
                <input type="number" min="1900" max="2100" class="vehicle-year w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                    placeholder="2026">
            </div>
        </div>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-sm text-mb-silver">Rental Types & Prices</p>
                <button type="button" class="add-rate text-xs text-mb-accent hover:underline">+ Add Type</button>
            </div>
            <div class="rate-list space-y-2"></div>
        </div>
        
        <!-- Discount Controls -->
        <div class="discount-controls mt-4 border-t border-mb-subtle/10 pt-4">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-3">Vehicle Discount</p>
            <div class="flex gap-2">
                <select class="discount-type bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent w-28 flex-shrink-0">
                    <option value="">None</option>
                    <option value="percent">Percent %</option>
                    <option value="amount">Fixed $</option>
                </select>
                <div class="discount-value-wrap flex-1 hidden">
                    <input type="number" class="discount-value w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent" 
                           step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            <div class="discount-preview hidden mt-2 text-xs space-y-1">
                <p class="text-green-400 font-medium"><span class="vehicle-subtotal"></span></p>
                <span class="vehicle-discount hidden"></span>
                <span class="vehicle-total hidden"></span>
            </div>
        </div>
    </div>
</template>

<template id="rateTemplate">
    <div class="rate-row flex flex-wrap items-center gap-2">
        <input type="text" class="rate-type flex-1 min-w-[180px] bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
            placeholder="Daily / Weekly / Monthly">
        <input type="number" step="0.01" min="0" class="rate-price w-32 bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
            placeholder="0.00">
        <button type="button" class="remove-rate text-xs text-red-400 hover:underline">Remove</button>
    </div>
</template>

<script>
(function () {
    // Discount state management
    const vehicleDiscounts = new Map();
    
    const vehicleList   = document.getElementById('vehicleList');
    const vehicleTemplate = document.getElementById('vehicleTemplate');
    const rateTemplate  = document.getElementById('rateTemplate');
    const addVehicleBtn = document.getElementById('addVehicleBtn');
    const generateBtn   = document.getElementById('generateQuoteBtn');
    const previewWrap   = document.getElementById('quotePreview');
    const previewContent= document.getElementById('quotePreviewContent');
    const printBtn      = document.getElementById('printQuoteBtn');
    const additionalToggle = document.getElementById('additionalToggle');
    const additionalCharge = document.getElementById('additionalCharge');

    function addRateRow(rateList) {
        const node = rateTemplate.content.cloneNode(true);
        const row  = node.querySelector('.rate-row');
        row.querySelector('.remove-rate').addEventListener('click', () => row.remove());
        rateList.appendChild(node);
    }

    function addVehicle() {
        const node  = vehicleTemplate.content.cloneNode(true);
        const block = node.querySelector('.vehicle-block');
        const rateList = block.querySelector('.rate-list');
        
        // Existing bindings
        block.querySelector('.add-rate').addEventListener('click', () => addRateRow(rateList));
        block.querySelector('.remove-vehicle').addEventListener('click', () => {
            const vehicleId = getVehicleId(block);
            vehicleDiscounts.delete(vehicleId); // Clean up state
            block.remove();
        });
        
        // Discount bindings
        const typeSelect = block.querySelector('.discount-type');
        const valueInput = block.querySelector('.discount-value');
        
        typeSelect.addEventListener('change', () => updateVehicleDiscount(block));
        valueInput.addEventListener('input', () => updateVehicleDiscount(block));
        
        // Recalculate discount when rates change
        rateList.addEventListener('input', () => {
            if (vehicleDiscounts.has(getVehicleId(block))) {
                updateVehicleDiscount(block);
            }
        });
        
        addRateRow(rateList);
        vehicleList.appendChild(node);
    }

    function fmt(val) {
        const num = Number(val);
        if (!isFinite(num) || num < 0) return null;
        return num.toFixed(2);
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function getVehicleId(vehicleBlock) {
        // Generate unique ID based on timestamp and random string
        if (!vehicleBlock.dataset.vehicleId) {
            vehicleBlock.dataset.vehicleId = 'v-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        }
        return vehicleBlock.dataset.vehicleId;
    }

    function calculateDiscountForRate(price, type, value) {
        if (!type || value <= 0 || price <= 0) return 0;
        if (type === 'percent') {
            const percent = Math.min(value, 100);
            return Math.round((price * percent / 100) * 100) / 100;
        } else if (type === 'amount') {
            return Math.round(Math.min(value, price) * 100) / 100;
        }
        return 0;
    }

    function updateVehicleDiscount(vehicleBlock) {
        const vehicleId = getVehicleId(vehicleBlock);
        const typeSelect = vehicleBlock.querySelector('.discount-type');
        const valueInput = vehicleBlock.querySelector('.discount-value');
        const valueWrap = vehicleBlock.querySelector('.discount-value-wrap');
        const previewDiv = vehicleBlock.querySelector('.discount-preview');
        
        const type = typeSelect.value || null;
        let value = parseFloat(valueInput.value || 0);
        
        // Show/hide value input and set constraints
        if (type) {
            valueWrap.classList.remove('hidden');
            if (type === 'percent') {
                valueInput.max = 100;
                valueInput.placeholder = '0 - 100';
                if (value > 100) {
                    value = 100;
                    valueInput.value = 100;
                }
            } else {
                valueInput.removeAttribute('max');
                valueInput.placeholder = '0.00';
            }
        } else {
            valueWrap.classList.add('hidden');
            valueInput.value = '';
        }
        
        // Store in state (discount applies per-rate, not as a sum)
        vehicleDiscounts.set(vehicleId, { type, value });
        
        // Update preview display
        if (type && value > 0) {
            previewDiv.classList.remove('hidden');
            const label = type === 'percent' ? value + '% off each rate' : '$' + value.toFixed(2) + ' off each rate';
            vehicleBlock.querySelector('.vehicle-subtotal').textContent = label;
        } else {
            previewDiv.classList.add('hidden');
        }
    }

    function buildPreview() {
        const vehicles = Array.from(vehicleList.querySelectorAll('.vehicle-block'));

        /* ── meta ── */
        const customerName = document.getElementById('customerName').value.trim();
        const quoteRef     = document.getElementById('quoteRef').value.trim()  || 'Q-' + Date.now().toString().slice(-6);
        const validUntil   = document.getElementById('validUntil').value;
        const now          = new Date();
        const dateStr      = now.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }).toUpperCase();

        /* ── charges ── */
        const deliveryVal  = fmt(document.getElementById('deliveryCharge').value) || '0.00';
        const returnVal    = fmt(document.getElementById('returnCharge').value)   || '0.00';
        const extraEnabled = additionalToggle.checked;
        const extraVal     = extraEnabled ? (fmt(additionalCharge.value) || '0.00') : null;

        /* collect vehicles data (no grand total sum — rates are alternatives, not additive) */
        const vehicleData = vehicles.map((block, idx) => {
            const vehicleId = getVehicleId(block);
            const name  = block.querySelector('.vehicle-name')?.value.trim() || 'Vehicle ' + (idx + 1);
            const model = block.querySelector('.vehicle-model')?.value.trim() || '';
            const year  = block.querySelector('.vehicle-year')?.value.trim()  || '';
            const rates = Array.from(block.querySelectorAll('.rate-row')).map(row => {
                const type  = row.querySelector('.rate-type')?.value.trim()  || '';
                const price = row.querySelector('.rate-price')?.value.trim() || '';
                const f     = fmt(price);
                return { type: type || 'Rental', price: f };
            }).filter(r => r.price);
            
            // Get discount data
            const discountData = vehicleDiscounts.get(vehicleId) || null;
            
            return { name, model, year, rates, discount: discountData };
        });

        /* ══════════════════════════════════════════════════════════
           INVOICE HTML
        ══════════════════════════════════════════════════════════ */
        let h = `
        <div id="invoiceDoc" style="
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            color: #111;
            background: #fff;
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
            padding: 32px 36px;
            box-sizing: border-box;
            border: 1px solid #ccc;
        ">

        <!-- ░░ HEADER ░░ -->
        <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom: 2px solid #111; padding-bottom: 14px; margin-bottom: 14px;">
            <tr>
                <td style="width:50%; vertical-align:middle;">
                    <div style="font-size:26px; font-weight:bold; letter-spacing:-0.5px; line-height:1.1;">
                        OrentinCars<br>
                        <span style="font-size:13px; font-weight:normal; color:#444;">Orentin Cars Pvt. Ltd.</span>
                    </div>
                    <div style="margin-top:6px; font-size:11px; color:#444; line-height:1.7;">
                        Kerala, India<br>
                        Phone: 7591955531 &nbsp;|&nbsp; 7591955532<br>
                        Orentincarspvtltd@gmail.com &nbsp;|&nbsp; orentincars.com
                    </div>
                </td>
                <td style="width:50%; text-align:right; vertical-align:top;">
                    <div style="font-size:22px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#111;">
                        QUOTATION
                    </div>
                    <div style="margin-top:8px; font-size:11px; color:#444; line-height:1.8;">
                        <table cellpadding="0" cellspacing="0" style="margin-left:auto;">
                            <tr>
                                <td style="padding-right:10px; color:#666;">Quote No.</td>
                                <td style="font-weight:bold;">${esc(quoteRef)}</td>
                            </tr>
                            <tr>
                                <td style="padding-right:10px; color:#666;">Date</td>
                                <td>${dateStr}</td>
                            </tr>
                            ${validUntil ? `<tr>
                                <td style="padding-right:10px; color:#666;">Valid Until</td>
                                <td>${esc(new Date(validUntil).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}).toUpperCase())}</td>
                            </tr>` : ''}
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- ░░ SOLD TO / INFO BAR ░░ -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
            <tr>
                <td style="width:50%; vertical-align:top;">
                    <div style="font-size:10px; text-transform:uppercase; color:#666; letter-spacing:1px; margin-bottom:4px;">Prepared For</div>
                    <div style="font-size:13px; font-weight:bold;">${customerName ? esc(customerName) : '&nbsp;'}</div>
                </td>
                <td style="width:50%; vertical-align:top;">
                    <table width="100%" cellpadding="3" cellspacing="0" style="border:1px solid #ccc; font-size:11px;">
                        <thead>
                            <tr style="background:#f0f0f0;">
                                <th style="text-align:left; padding:5px 8px; border-bottom:1px solid #ccc; font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Date Issued</th>
                                <th style="text-align:left; padding:5px 8px; border-bottom:1px solid #ccc; font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Quote No.</th>
                                <th style="text-align:left; padding:5px 8px; border-bottom:1px solid #ccc; font-size:10px; text-transform:uppercase; letter-spacing:.5px;">Page</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:5px 8px;">${dateStr}</td>
                                <td style="padding:5px 8px; font-weight:bold;">${esc(quoteRef)}</td>
                                <td style="padding:5px 8px;">1 OF 1</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <!-- ░░ NOTICE BAR ░░ -->
        <div style="background:#f7f7f7; border:1px solid #ddd; padding:7px 12px; font-size:10px; color:#555; text-align:center; margin-bottom:18px; line-height:1.6;">
            THIS IS A QUOTATION ONLY — NOT AN INVOICE. PRICES ARE SUBJECT TO CHANGE WITHOUT NOTICE.<br>
            QUOTATION IS VALID FOR 30 DAYS FROM DATE OF ISSUE UNLESS STATED OTHERWISE.
        </div>
        `;

        /* ── per-vehicle tables ── */
        vehicleData.forEach((v, idx) => {
            const heading = [v.name, v.model, v.year ? '(' + v.year + ')' : ''].filter(Boolean).join(' ');
            h += `
            <!-- ░░ VEHICLE ${idx+1} ░░ -->
            <div style="margin-bottom:18px;">
                <div style="background:#111; color:#fff; padding:5px 10px; font-size:11px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; margin-bottom:0;">
                    ${esc(heading)}
                </div>
                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ccc; border-top:none; font-size:11px;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="text-align:left; padding:6px 10px; border-bottom:1px solid #ccc; width:60%;">RENTAL TYPE / DESCRIPTION</th>
                            <th style="text-align:right; padding:6px 10px; border-bottom:1px solid #ccc; width:20%;">UNIT PRICE</th>
                            <th style="text-align:right; padding:6px 10px; border-bottom:1px solid #ccc; width:20%;">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            if (v.rates.length) {
                const hasDiscount = v.discount && v.discount.type && v.discount.value > 0;
                v.rates.forEach((r, ri) => {
                    const bg = ri % 2 === 0 ? '#fff' : '#fafafa';
                    const originalPrice = parseFloat(r.price);
                    if (hasDiscount) {
                        const discAmt = calculateDiscountForRate(originalPrice, v.discount.type, v.discount.value);
                        const discounted = Math.max(0, originalPrice - discAmt);
                        h += `<tr style="background:${bg};">
                            <td style="padding:6px 10px; border-bottom:1px solid #eee;">${esc(r.type)}</td>
                            <td style="padding:6px 10px; border-bottom:1px solid #eee; text-align:right; text-decoration:line-through; color:#999;">$${r.price}</td>
                            <td style="padding:6px 10px; border-bottom:1px solid #eee; text-align:right; font-weight:bold; color:#16a34a;">$${discounted.toFixed(2)}</td>
                        </tr>`;
                    } else {
                        h += `<tr style="background:${bg};">
                            <td style="padding:6px 10px; border-bottom:1px solid #eee;">${esc(r.type)}</td>
                            <td style="padding:6px 10px; border-bottom:1px solid #eee; text-align:right;">$${r.price}</td>
                            <td style="padding:6px 10px; border-bottom:1px solid #eee; text-align:right; font-weight:bold;">$${r.price}</td>
                        </tr>`;
                    }
                });
                
                // Show discount note row if applicable
                if (hasDiscount) {
                    const discLabel = v.discount.type === 'percent'
                        ? v.discount.value + '% Discount Applied'
                        : '$' + v.discount.value.toFixed(2) + ' Discount Applied';
                    h += `<tr style="background:#f0fff0;">
                        <td colspan="3" style="padding:5px 10px; text-align:center; color:#16a34a; font-size:10px; font-style:italic; border-bottom:1px solid #eee;">${esc(discLabel)}</td>
                    </tr>`;
                }
            } else {
                h += `<tr><td colspan="3" style="padding:10px; color:#999; text-align:center; font-style:italic;">No rental rates specified.</td></tr>`;
            }

            h += `</tbody></table></div>`;
        });

        /* ── charges + totals footer (no grand total — rates are options) ── */
        const hasAnyCharges = parseFloat(deliveryVal) > 0 || parseFloat(returnVal) > 0 || (extraEnabled && parseFloat(extraVal) > 0);
        h += `
        <!-- ░░ FOOTER / TOTALS ░░ -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">
            <tr>
                <!-- Left: note -->
                <td style="width:50%; vertical-align:top; padding-right:20px;">
                    <div style="font-size:10px; color:#555; line-height:1.7; border:1px solid #ddd; padding:10px 12px; background:#fafafa;">
                        <strong>Terms &amp; Conditions</strong><br>
                        All rates are subject to availability and may change.<br>
                        Delivery and return charges apply per booking.<br>
                        Additional charges may apply as agreed.<br><br>
                        <span style="font-style:italic;">We appreciate your business. Have a great day!</span>
                    </div>
                </td>

                <!-- Right: charges table -->
                ${hasAnyCharges ? `<td style="width:50%; vertical-align:top;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ccc; font-size:11px;">
                        <tr>
                            <td colspan="2" style="padding:5px 10px; background:#f0f0f0; font-size:10px; text-transform:uppercase; letter-spacing:.5px; font-weight:bold; border-bottom:1px solid #ccc;">Additional Charges (per booking)</td>
                        </tr>
                        ${parseFloat(deliveryVal) > 0 ? '<tr><td style="padding:6px 10px; border-bottom:1px solid #eee; background:#f7f7f7;">Delivery Charge</td><td style="padding:6px 10px; border-bottom:1px solid #eee; text-align:right; background:#f7f7f7;">$' + deliveryVal + '</td></tr>' : ''}
                        ${parseFloat(returnVal) > 0 ? '<tr><td style="padding:6px 10px; border-bottom:1px solid #eee;">Return Charge</td><td style="padding:6px 10px; border-bottom:1px solid #eee; text-align:right;">$' + returnVal + '</td></tr>' : ''}
                        ${extraEnabled && parseFloat(extraVal) > 0 ? '<tr><td style="padding:6px 10px; border-bottom:1px solid #eee; background:#f7f7f7;">Additional Charge</td><td style="padding:6px 10px; border-bottom:1px solid #eee; text-align:right; background:#f7f7f7;">$' + extraVal + '</td></tr>' : ''}
                    </table>
                </td>` : '<td style="width:50%;"></td>'}
            </tr>
        </table>

        </div><!-- /invoiceDoc -->
        `;

        previewContent.innerHTML = h;
        previewWrap.classList.remove('hidden');
        previewWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ── Auto-generate quote number: Q-YYYYMMDD-XXXX ── */
    function generateQuoteNo() {
        const now  = new Date();
        const ymd  = now.getFullYear().toString()
                   + String(now.getMonth() + 1).padStart(2, '0')
                   + String(now.getDate()).padStart(2, '0');
        const rand = Math.floor(1000 + Math.random() * 9000);
        return 'Q-' + ymd + '-' + rand;
    }

    const quoteRefInput = document.getElementById('quoteRef');
    quoteRefInput.value = generateQuoteNo();

    document.getElementById('regenQuoteRef').addEventListener('click', () => {
        quoteRefInput.value = generateQuoteNo();
    });

    let originalTitle = document.title;
    window.addEventListener('beforeprint', () => {
        originalTitle = document.title;
        document.title = '';
    });
    window.addEventListener('afterprint', () => {
        document.title = originalTitle;
    });

    addVehicleBtn.addEventListener('click', addVehicle);
    generateBtn.addEventListener('click', buildPreview);
    printBtn.addEventListener('click', () => {
        document.title = '';
        window.print();
    });
    additionalToggle.addEventListener('change', () => {
        additionalCharge.disabled = !additionalToggle.checked;
        additionalCharge.classList.toggle('text-white/70', !additionalToggle.checked);
        if (!additionalToggle.checked) additionalCharge.value = '';
    });

    addVehicle();
})();
</script>

<style>
@media print {
    /* Hide everything except the invoice doc */
    body * { visibility: hidden; }
    #invoiceDoc, #invoiceDoc * { visibility: visible; }
    #invoiceDoc {
        position: fixed;
        top: 0; left: 0;
        width: 100%;
        margin: 0 !important;
        padding: 28px 36px !important;
        border: none !important;
        font-family: 'Times New Roman', Times, serif;
        font-size: 12px;
        color: #000;
        background: #fff;
    }
    .no-print { display: none !important; }
    @page {
        size: A4;
        margin: 15mm 12mm;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
