let finalTotal = 0;
let activeCoupon = null;

const PRODUCTS = window.PRODUCTS || {};

// 1. ZENTRALE HILFSFUNKTIONEN (DRY)
function getCleanPrice(priceRaw) {
    if (!priceRaw) return 0;
    let safePrice = parseFloat(priceRaw.toString().replace(/[€\s]/g, '').replace(',', '.'));
    return isNaN(safePrice) ? 0 : safePrice;
}

function resolveProductBaseId(key, productsObj) {
    let possibleIds = Object.keys(productsObj).filter(k => {
        let realId = productsObj[k].id || k;
        return key === realId.toString() || key.startsWith(realId + '_');
    });
    
    if (possibleIds.length > 0) {
        possibleIds.sort((a, b) => {
            let idA = productsObj[a].id || a;
            let idB = productsObj[b].id || b;
            return idB.toString().length - idA.toString().length;
        });
        return possibleIds[0];
    }
    return null;
}

/**
 * 3-stufige Staffel-Logik für Versandkosten
 * @param {number} packsCount - Anzahl der bestellten Sticker-Packungen
 * @param {string} country - Lieferland ("Deutschland", etc.)
 * @returns {{price: number, label: string, formatted: string}}
 */
function calculateShipping(packsCount, country) {
    const packs = parseInt(packsCount, 10) || 0;
    if (packs <= 0) {
        return { price: 0, label: '', formatted: '0,00 €' };
    }

    const c = (country || '').trim().toLowerCase();
    const isDomestic = (c === 'deutschland' || c === 'de' || c === 'deu');

    let price = 0;
    let label = '';

    if (packs <= 4) {
        price = isDomestic ? 1.80 : 3.30;
        label = isDomestic ? 'Großbrief (1–4 Packungen)' : 'Großbrief International (1–4 Packungen)';
    } else if (packs <= 9) {
        price = isDomestic ? 2.90 : 6.50;
        label = isDomestic ? 'Maxibrief (5–9 Packungen)' : 'Maxibrief International (5–9 Packungen)';
    } else {
        price = isDomestic ? 4.19 : 10.49;
        label = isDomestic ? '2 kg Päckchen (ab 10 Packungen)' : 'Päckchen International (ab 10 Packungen)';
    }

    return {
        price: price,
        label: label,
        formatted: price.toFixed(2).replace('.', ',') + ' €'
    };
}

// 2. CHECKOUT LOGIK
function applyCoupon() {
    const input = document.getElementById('coupon-input');
    const msg = document.getElementById('coupon-message');
    const hiddenInput = document.getElementById('hidden-coupon-code');
    if (!input || !msg || !hiddenInput) return;
    
    const code = input.value.trim().toUpperCase();
    
    if (code === '') {
        activeCoupon = null;
        hiddenInput.value = '';
        msg.style.display = 'none';
        updatePricing();
        return;
    }
    
    msg.style.color = '#666';
    msg.innerText = 'WIRD GEPRÜFT...';
    msg.style.display = 'block';
    
    fetch('coupon_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code })
    })
    .then(res => res.json())
    .then(data => {
        if (data.valid) {
            activeCoupon = { type: data.type, value: data.value, code: code };
            hiddenInput.value = code;
            msg.style.color = 'green';
            if (data.type === 'percent') {
                msg.innerText = `CODE AKTIV: ${data.value}% RABATT`;
            } else if (data.type === 'fixed') {
                msg.innerText = `CODE AKTIV: ${data.value.toFixed(2).replace('.', ',')} € RABATT`;
            } else if (data.type === 'free') {
                msg.innerText = 'CODE AKTIV: BESTELLUNG IST KOSTENLOS';
            } else if (data.type === 'items_free') {
                msg.innerText = 'CODE AKTIV: NUR VERSANDKOSTEN FÄLLIG';
            } else if (data.type === 'free_shipping') {
                msg.innerText = 'CODE AKTIV: KOSTENLOSER VERSAND';
            } else if (data.type === 'percent_free_shipping') {
                msg.innerText = `CODE AKTIV: ${data.value}% RABATT + KOSTENLOSER VERSAND`;
            }
        } else {
            activeCoupon = null;
            hiddenInput.value = '';
            msg.style.color = '#e2001a';
            msg.innerText = 'CODE UNGÜLTIG ODER BEREITS EINGELÖST';
        }
        updatePricing();
    })
    .catch(error => {
        activeCoupon = null;
        hiddenInput.value = '';
        msg.style.color = '#e2001a';
        msg.innerText = 'FEHLER BEI DER ÜBERPRÜFUNG';
        updatePricing();
    });
}

function updatePricing() {
    const countrySelect = document.getElementById('country-select');
    const country = countrySelect ? countrySelect.value : 'Deutschland';

    let subtotal = 0;
    let totalPacks = 0;

    // Primär: Mengen aus den Checkout-Dropdowns lesen (immer aktuell)
    const qtySelects = document.querySelectorAll('.checkout-qty-select');
    if (qtySelects.length > 0) {
        qtySelects.forEach(sel => {
            const qty = parseInt(sel.value, 10) || 0;
            if (qty <= 0) return;
            totalPacks += qty;

            // Preis direkt aus data-price des Dropdowns lesen
            const price = parseFloat(sel.getAttribute('data-price') || '0') || 0;
            subtotal += qty * price;
        });
    } else {
        // Fallback: hidden inputs (für den Fall ohne Dropdowns)
        document.querySelectorAll('input[id^="qty-"], input[name^="Menge_"]').forEach(input => {
            const qty = parseInt(input.value, 10) || 0;
            if (qty <= 0) return;
            totalPacks += qty;
            let price = getCleanPrice(input.getAttribute('data-price') || '0');
            if (price === 0) {
                const prodId = input.id.replace('qty-', '');
                price = getCleanPrice((PRODUCTS[prodId] && PRODUCTS[prodId].price ? PRODUCTS[prodId].price : '0').toString());
            }
            subtotal += qty * price;
        });
    }

    // 3-stufige Staffel-Logik basierend auf Packungsanzahl und Land
    const shippingData = calculateShipping(totalPacks, country);
    let shipping = shippingData.price;

    let discount = 0;
    if (activeCoupon) {
        if (activeCoupon.type === 'percent') discount = subtotal * (activeCoupon.value / 100);
        else if (activeCoupon.type === 'fixed') { discount = activeCoupon.value; if (discount > subtotal) discount = subtotal; }
        else if (activeCoupon.type === 'free') discount = subtotal + shipping;
        else if (activeCoupon.type === 'items_free') discount = subtotal;
        else if (activeCoupon.type === 'free_shipping') discount = shipping;
        else if (activeCoupon.type === 'percent_free_shipping') discount = (subtotal * (activeCoupon.value / 100)) + shipping;
    }

    finalTotal = subtotal + shipping - discount;
    if (finalTotal < 0) finalTotal = 0;

    if (document.getElementById('display-subtotal')) document.getElementById('display-subtotal').innerText = subtotal.toFixed(2).replace('.', ',');
    if (document.getElementById('display-shipping')) document.getElementById('display-shipping').innerText = shipping.toFixed(2).replace('.', ',');
    if (document.getElementById('display-total')) document.getElementById('display-total').innerText = finalTotal.toFixed(2).replace('.', ',');

    const shippingLabel = document.getElementById('shipping-label');
    if (shippingLabel) shippingLabel.innerText = 'Versand:';

    const shippingTierBadge = document.getElementById('shipping-tier-badge');
    if (shippingTierBadge) shippingTierBadge.innerText = shippingData.label || '';
    
    const rabattRow = document.getElementById('rabatt-row');
    if (rabattRow) {
        if (discount > 0) {
            rabattRow.style.display = 'flex';
            const discountEl = document.getElementById('display-discount');
            if (discountEl) discountEl.innerText = discount.toFixed(2).replace('.', ',');
        } else {
            rabattRow.style.display = 'none';
        }
    }

    const paypalContainer = document.getElementById('paypal-button-container');
    const freeBtn = document.getElementById('free-checkout-btn');
    if (finalTotal === 0 && totalPacks > 0) {
        if (paypalContainer) paypalContainer.style.display = 'none';
        if (freeBtn) freeBtn.style.display = 'block';
    } else {
        if (paypalContainer) paypalContainer.style.display = 'block';
        if (freeBtn) freeBtn.style.display = 'none';
    }
}

function changeQtyLocal(inputId, amount) {
    const input = document.getElementById(inputId);
    if (input) {
        let val = (parseInt(input.value) || 0) + amount;
        if (val < 0) val = 0;
        input.value = val;
        if (typeof updatePricing === 'function') updatePricing();
    }
}

function updateCheckoutQty(cartKey, baseId, newQty, unitPrice) {
    const qty = parseInt(newQty, 10) || 1;
    
    // 1. Zeilenpreis im Checkout aktualisieren
    const linePriceEl = document.getElementById('line-price-' + cartKey);
    if (linePriceEl) {
        linePriceEl.innerText = (qty * unitPrice).toFixed(2).replace('.', ',') + ' €';
    }

    // 2. Hidden-Input aggregiert aktualisieren:
    //    Alle Dropdowns mit demselben baseId summieren und in den hidden input schreiben
    const hiddenInput = document.getElementById('qty-' + baseId)
        || document.querySelector(`input[name="Menge_${baseId}"]`);
    if (hiddenInput) {
        // Alle Selects mit Keys die zu baseId gehören summieren
        let aggregated = 0;
        document.querySelectorAll('.checkout-qty-select').forEach(sel => {
            const key = sel.getAttribute('data-key') || '';
            // Prüfen ob dieser Select zum selben baseId gehört
            if (key === baseId || key.startsWith(baseId + '_') || key === cartKey) {
                aggregated += parseInt(sel.value, 10) || 0;
            }
        });
        // Wenn nur dieser cartKey geändert wurde und der aktuelle Select noch nicht aktualisiert ist,
        // addieren wir den neuen Wert direkt
        if (aggregated === 0) aggregated = qty;
        hiddenInput.value = aggregated;
    }

    // 3. Preise & 3-stufigen Versand im Checkout sofort neu berechnen
    if (typeof updatePricing === 'function') {
        updatePricing();
    }

    // 4. Session über cart_api.php synchronisieren
    fetch('cart_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'set_qty', key: cartKey, id: cartKey, qty: qty })
    })
    .then(res => res.json())
    .catch(err => console.error('Cart sync error:', err));
}


// 3. WARENKORB LOGIK (SHOP.PHP)
function toggleCartDrawer() {
    const drawer = document.getElementById('cart-drawer');
    if(!drawer) return;
    drawer.classList.toggle('open');
    if (drawer.classList.contains('open')) {
        document.body.style.overflow = 'hidden'; 
        fetchCartData();
    } else {
        document.body.style.overflow = '';
    }
}

function fetchCartData() {
    fetch('cart_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get' })
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.stocks) applyStockToShopUI(data.stocks);
        updateCartUI(data);
    }).catch(err => console.error('Fetch error:', err));
}

function addToCart(productId, requiresSize, btnEl = null) {
    const sizeSelect = document.getElementById('size_' + productId);
    const selectedSize = sizeSelect ? sizeSelect.value : '-'; 
    const qtySelect = document.getElementById('qty_' + productId);
    const selectedQty = qtySelect ? (parseInt(qtySelect.value, 10) || 1) : 1; 

    if (requiresSize && (!selectedSize || selectedSize === '-' || selectedSize === '')) {
        sizeSelect.focus(); 
        sizeSelect.style.borderColor = "var(--accent)";
        setTimeout(() => { sizeSelect.style.borderColor = "var(--black)"; }, 1500); 
        return;
    }

    const btn = btnEl || (window.event ? window.event.target : null); 
    let originalText = "";
    if(btn) {
        originalText = btn.innerText; 
        btn.innerText = "LÄDT...";
    }
    
    fetch('cart_api.php', {
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', id: productId, size: selectedSize, qty: selectedQty })
    })
    .then(async res => {
        let data;
        try {
            data = await res.json();
        } catch(e) {
            throw new Error("Server antwortet nicht korrekt (HTTP " + res.status + ")");
        }
        return data;
    })
    .then(data => {
        if(data && data.status === 'ok') {
            if(btn) {
                btn.innerText = "DRIN!"; 
                btn.style.background = "var(--accent)"; 
                btn.style.color = "#fff";
            }
            
            const countEl = document.getElementById('cart-count');
            if (countEl) countEl.innerText = data.totalItems; 
            
            if (window.innerWidth > 767) {
                const drawer = document.getElementById('cart-drawer');
                if(drawer && !drawer.classList.contains('open')) {
                    drawer.classList.add('open');
                    document.body.style.overflow = 'hidden'; 
                }
            }
            
            if (data.stocks) applyStockToShopUI(data.stocks);
            updateCartUI(data);
            if(btn) {
                setTimeout(() => { btn.innerText = originalText; btn.style.background = ""; btn.style.color = ""; }, 1000);
            }
        } else {
            if (data && data.stocks) applyStockToShopUI(data.stocks);
            if(btn) {
                btn.innerText = (data && data.code === 'sold_out') ? "AUSVERKAUFT" : "LIMIT ERREICHT!";
                btn.style.background = "var(--accent)";
                btn.style.color = "#fff";
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.style.background = "";
                    btn.style.color = "";
                }, 2000);
            }
            alert((data && data.message) ? data.message : 'Dieser Artikel kann nicht hinzugefügt werden.');
        }
    })
    .catch(err => { 
        console.error('Warenkorb API Fehler:', err);
        if(btn) {
            btn.innerText = "FEHLER!"; 
            setTimeout(() => { btn.innerText = originalText; }, 1500); 
        }
        alert(err.message || 'Verbindung fehlgeschlagen beim Hinzufügen zum Warenkorb.');
    });
}

function removeFromCart(cartKey) {
    fetch('cart_api.php', {
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', key: cartKey, id: cartKey })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'ok') {
            if (data.stocks) applyStockToShopUI(data.stocks);
            updateCartUI(data);
        }
    });
}

function setCartItemQty(cartKey, newQty, oldQty) {
    let changeAmount = parseInt(newQty, 10) - parseInt(oldQty, 10);
    fetch('cart_api.php', {
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_qty', id: cartKey, key: cartKey, change: changeAmount })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'ok') {
            if (data.stocks) applyStockToShopUI(data.stocks);
            updateCartUI(data);
        }
    })
    .catch(err => console.error('API Error:', err));
}

function applyStockToShopUI(stocks) {
    if (!stocks) return;
    window.PRODUCT_STOCKS = stocks;
    for (const [pId, s] of Object.entries(stocks)) {
        const btn = document.getElementById('btn-add-' + pId);
        const qtySel = document.getElementById('qty_' + pId);
        const avail = parseInt(s.available_packs, 10) || 0;

        if (avail <= 0) {
            if (btn) {
                btn.disabled = true;
                btn.classList.add('sold-out');
                btn.innerText = "AUSVERKAUFT";
            }
            if (qtySel) {
                qtySel.disabled = true;
                qtySel.innerHTML = '<option value="0">0 Pack (Ausverkauft)</option>';
            }
        } else {
            if (btn && btn.classList.contains('sold-out') && btn.disabled) {
                btn.disabled = false;
                btn.classList.remove('sold-out');
                btn.innerText = "HINZUFÜGEN";
            }
            if (qtySel && !qtySel.disabled) {
                const maxVal = Math.min(10, avail);
                const currVal = parseInt(qtySel.value, 10) || 1;
                const qtyStep = s.qty_step || 50;
                let opts = '';
                for (let q = 1; q <= maxVal; q++) {
                    const sel = (q === Math.min(currVal, maxVal)) ? 'selected' : '';
                    opts += `<option value="${q}" ${sel}>${qtyStep > 1 ? `${q} Pack (${q * qtyStep} Stk.)` : `${q} Stück`}</option>`;
                }
                qtySel.innerHTML = opts;
            }
        }
    }
}

function updateCartUI(data) {
    const countBadge = document.getElementById('cart-count');
    if (countBadge) countBadge.innerText = data.totalItems || 0;
    
    const container = document.getElementById('drawer-items-container');
    if (!container) return; // In checkout it might not exist
    
    container.innerHTML = '';

    let items = data.items || {};

    if (Object.keys(items).length === 0) {
        container.innerHTML = '<div style="font-weight: 800; text-align: center; margin-top: 40px; opacity: 0.6;">DEIN WARENKORB IST LEER</div>';
        if(document.getElementById('drawer-total-price')) document.getElementById('drawer-total-price').innerText = '0,00';
        return;
    }

    let subtotal = 0;
    
    for (const [key, itemData] of Object.entries(items)) {
        let qty = (typeof itemData === 'object' && itemData !== null) ? parseInt(itemData.qty, 10) : parseInt(itemData, 10);
        
        let baseId = resolveProductBaseId(key, PRODUCTS);
        if (!baseId) continue; 
        
        let realId = PRODUCTS[baseId].id || baseId;
        let sizeText = key === realId.toString() ? '' : key.substring(realId.toString().length + 1);
        if (sizeText === '-') sizeText = '';

        let prod = PRODUCTS[baseId];
        
        let safePrice = getCleanPrice(prod.price);
        
        let linePrice = qty * safePrice;
        subtotal += linePrice;

        let displaySize = sizeText ? ` (${sizeText})` : '';
        let qtyStep = prod.qty_step ? parseInt(prod.qty_step, 10) : 50;
        
        // Stock-Limit ermitteln
        let stockInfo = (data && data.stocks && data.stocks[baseId])
            ? data.stocks[baseId]
            : ((window.PRODUCT_STOCKS && window.PRODUCT_STOCKS[baseId]) ? window.PRODUCT_STOCKS[baseId] : null);
        
        let maxPacks = 10;
        if (stockInfo && typeof stockInfo.available_packs !== 'undefined') {
            maxPacks = Math.max(1, Math.min(10, parseInt(stockInfo.available_packs, 10)));
        }
        if (qty > maxPacks) maxPacks = qty;

        let selectOptions = '';
        for(let i=1; i<=maxPacks; i++) {
            let isSelected = (i === qty) ? 'selected' : '';
            if(qtyStep > 1) {
                selectOptions += `<option value="${i}" ${isSelected}>${i} Pack (${i * qtyStep} Stk.)</option>`;
            } else {
                selectOptions += `<option value="${i}" ${isSelected}>${i} Stück</option>`;
            }
        }

        let li = document.createElement('div');
        li.className = 'drawer-item';
        li.innerHTML = `
            <div class="drawer-item-header">
                <div class="drawer-item-info">
                    <strong>${prod.title}</strong><strong>${displaySize}</strong>
                    <span>${linePrice.toFixed(2).replace('.', ',')}<span class="euro">€</span></span>
                </div>
                <button class="remove-cart-item" onclick="removeFromCart('${key}')">[X]</button>
            </div>
            <select class="drawer-qty-select" onchange="setCartItemQty('${key}', this.value, ${qty})">
                ${selectOptions}
            </select>
        `;
        container.appendChild(li);
    }
    
    if(document.getElementById('drawer-total-price')) {
        document.getElementById('drawer-total-price').innerText = subtotal.toFixed(2).replace('.', ',');
    }

    const drawerShippingCost = document.getElementById('drawer-shipping-cost');
    const drawerShippingSubtext = document.getElementById('drawer-shipping-subtext');
    const drawerShippingTierName = document.getElementById('drawer-shipping-tier-name');
    if (drawerShippingCost && drawerShippingSubtext) {
        if (data && data.shipping && (data.totalPacks > 0 || subtotal > 0)) {
            drawerShippingCost.innerText = data.shipping.price.toFixed(2).replace('.', ',');
            drawerShippingSubtext.innerText = `${data.shipping.label} • Ausland an der Kasse`;
            if (drawerShippingTierName) drawerShippingTierName.innerText = 'Versand (DE):';
        } else {
            drawerShippingCost.innerText = '1,80';
            drawerShippingSubtext.innerText = 'Staffelpreis ab 1,80 € (DE) • Ausland an der Kasse';
            if (drawerShippingTierName) drawerShippingTierName.innerText = 'Versand (ab):';
        }
    }
}

// 4. ON LOAD EVENTS
document.addEventListener('DOMContentLoaded', () => {
    // Checkout: Mengenänderungen
    document.querySelectorAll('input[id^="qty-"], input[name^="Menge_"]').forEach(input => {
        input.addEventListener('input', updatePricing);
    });

    // Checkout: Länderauswahl
    const countrySelect = document.getElementById('country-select');
    if (countrySelect) {
        countrySelect.addEventListener('change', () => {
            const otherGroup = document.getElementById('other-country-group');
            const otherInput = document.getElementById('other-country-input');
            const isOther = (countrySelect.value === 'Anderes EU-Land');
            if (otherGroup) {
                otherGroup.style.display = isOther ? 'block' : 'none';
            }
            if (otherInput) {
                otherInput.required = isOther;
                if (!isOther) {
                    otherInput.value = '';
                    otherInput.style.borderColor = '';
                }
            }
            updatePricing();
        });
    }

    if (document.getElementById('display-total')) {
        updatePricing();
    }
});

// 5. LIGHTBOX MODAL LOGIK
let currentLightboxImages = [];
let currentLightboxIndex = 0;
let touchStartX = 0;
let touchEndX = 0;

function getScrollbarWidth() {
    return window.innerWidth - document.documentElement.clientWidth;
}

function openLightbox(productId) {
    let prod = null;
    if (PRODUCTS[productId]) {
        prod = PRODUCTS[productId];
    } else {
        // Fallback for resolving by ID property instead of key
        const baseId = resolveProductBaseId(productId, PRODUCTS);
        if (baseId) prod = PRODUCTS[baseId];
    }
    
    if (!prod) return;
    
    let images = prod.gallery && Array.isArray(prod.gallery) && prod.gallery.length > 0 
                    ? prod.gallery 
                    : [prod.image];
    
    currentLightboxImages = images;
    currentLightboxIndex = 0;
    
    updateLightboxUI();
    
    const scrollbarWidth = getScrollbarWidth();
    document.documentElement.style.setProperty('--scrollbar-compensation', `${scrollbarWidth}px`);
    document.body.classList.add('lightbox-open');
    const nav = document.getElementById('shop-nav');
    if (nav) nav.classList.add('lightbox-open');
    
    document.getElementById('lightbox-modal').classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox-modal').classList.remove('active');
    document.body.classList.remove('lightbox-open');
    const nav = document.getElementById('shop-nav');
    if (nav) nav.classList.remove('lightbox-open');
}

function changeLightboxImage(direction, event) {
    if (event) {
        event.stopPropagation();
    }
    if (currentLightboxImages.length <= 1) return;
    
    currentLightboxIndex += direction;
    
    if (currentLightboxIndex < 0) {
        currentLightboxIndex = currentLightboxImages.length - 1;
    } else if (currentLightboxIndex >= currentLightboxImages.length) {
        currentLightboxIndex = 0;
    }
    
    updateLightboxUI();
}

function updateLightboxUI() {
    const imgEl = document.getElementById('lightbox-img');
    const currEl = document.getElementById('lightbox-curr');
    const totEl = document.getElementById('lightbox-tot');
    const prevBtn = document.querySelector('.lightbox-prev');
    const nextBtn = document.querySelector('.lightbox-next');
    const counterDiv = document.querySelector('.lightbox-counter');
    
    if (!imgEl) return;
    
    imgEl.src = currentLightboxImages[currentLightboxIndex];
    if (currEl) currEl.innerText = currentLightboxIndex + 1;
    if (totEl) totEl.innerText = currentLightboxImages.length;
    
    if (currentLightboxImages.length <= 1) {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
        if (counterDiv) counterDiv.style.display = 'none';
    } else {
        if (prevBtn) prevBtn.style.display = 'flex';
        if (nextBtn) nextBtn.style.display = 'flex';
        if (counterDiv) counterDiv.style.display = 'block';
    }
}

// Touch swipe gestures
document.addEventListener('DOMContentLoaded', () => {
    const swipeArea = document.getElementById('lightbox-swipe-area');
    if (swipeArea) {
        swipeArea.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, {passive: true});
        
        swipeArea.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, {passive: true});
    }
});

function handleSwipe() {
    const swipeThreshold = 50; 
    if (touchEndX < touchStartX - swipeThreshold) {
        changeLightboxImage(1); // Swipe left
    }
    if (touchEndX > touchStartX + swipeThreshold) {
        changeLightboxImage(-1); // Swipe right
    }
}

// Keyboard navigation
document.addEventListener('keydown', (e) => {
    const modal = document.getElementById('lightbox-modal');
    if (modal && modal.classList.contains('active')) {
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowRight') {
            changeLightboxImage(1);
        } else if (e.key === 'ArrowLeft') {
            changeLightboxImage(-1);
        }
    }
});