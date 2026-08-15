(function () {
  'use strict';

  /**
   * Cashier order-entry cart. Lives entirely client-side until "Place Order"
   * is submitted — the server re-validates every item/price against the
   * live menu regardless of what's sent (see OrderController::create_order).
   */
  var cart = []; // [{id, name, price, qty}]

  function formatCurrency(amount) {
    return '$' + amount.toFixed(2);
  }

  function findCartItem(id) {
    return cart.find(function (item) { return item.id === id; });
  }

  function addToCart(id, name, price) {
    var existing = findCartItem(id);
    if (existing) {
      existing.qty += 1;
    } else {
      cart.push({ id: id, name: name, price: price, qty: 1 });
    }
    renderCart();
  }

  function changeQty(id, delta) {
    var item = findCartItem(id);
    if (!item) {
      return;
    }
    item.qty += delta;
    if (item.qty <= 0) {
      cart = cart.filter(function (i) { return i.id !== id; });
    }
    renderCart();
  }

  function removeFromCart(id) {
    cart = cart.filter(function (i) { return i.id !== id; });
    renderCart();
  }

  function renderCart() {
    var list = document.getElementById('cart-list');
    var totalEl = document.getElementById('cart-total');
    var cartDataEl = document.getElementById('cart-data');
    var placeOrderBtn = document.getElementById('place-order-btn');

    if (!list) {
      return;
    }

    list.innerHTML = '';

    if (cart.length === 0) {
      var emptyLi = document.createElement('li');
      emptyLi.className = 'cart-empty';
      emptyLi.textContent = 'No items yet — tap the menu to add.';
      list.appendChild(emptyLi);
    }

    var total = 0;

    cart.forEach(function (item) {
      var lineTotal = item.price * item.qty;
      total += lineTotal;

      var li = document.createElement('li');
      li.className = 'cart-item';

      var nameSpan = document.createElement('span');
      nameSpan.className = 'cart-item-name';
      nameSpan.textContent = item.name;

      var controls = document.createElement('div');
      controls.className = 'cart-item-controls';

      var minusBtn = document.createElement('button');
      minusBtn.type = 'button';
      minusBtn.className = 'cart-qty-btn';
      minusBtn.setAttribute('aria-label', 'Decrease quantity');
      minusBtn.textContent = '\u2212';
      minusBtn.addEventListener('click', function () { changeQty(item.id, -1); });

      var qtySpan = document.createElement('span');
      qtySpan.className = 'cart-item-qty';
      qtySpan.textContent = String(item.qty);

      var plusBtn = document.createElement('button');
      plusBtn.type = 'button';
      plusBtn.className = 'cart-qty-btn';
      plusBtn.setAttribute('aria-label', 'Increase quantity');
      plusBtn.textContent = '+';
      plusBtn.addEventListener('click', function () { changeQty(item.id, 1); });

      controls.appendChild(minusBtn);
      controls.appendChild(qtySpan);
      controls.appendChild(plusBtn);

      var priceSpan = document.createElement('span');
      priceSpan.className = 'cart-item-price';
      priceSpan.textContent = formatCurrency(lineTotal);

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'cart-remove-btn';
      removeBtn.setAttribute('aria-label', 'Remove ' + item.name);
      removeBtn.textContent = '\u00d7';
      removeBtn.addEventListener('click', function () { removeFromCart(item.id); });

      li.appendChild(nameSpan);
      li.appendChild(controls);
      li.appendChild(priceSpan);
      li.appendChild(removeBtn);
      list.appendChild(li);
    });

    if (totalEl) {
      totalEl.textContent = formatCurrency(total);
    }

    if (cartDataEl) {
      cartDataEl.value = JSON.stringify(cart.map(function (i) {
        return { menu_item_id: i.id, quantity: i.qty };
      }));
    }

    if (placeOrderBtn) {
      placeOrderBtn.disabled = cart.length === 0;
    }
  }

  function initMenuButtons() {
    var buttons = document.querySelectorAll('.menu-item-btn');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var name = btn.getAttribute('data-name');
        var price = parseFloat(btn.getAttribute('data-price'));
        addToCart(id, name, price);
      });
    });
  }

  /**
   * Generic reveal-on-click toggle, used by both the cashier's "Collect
   * Payment" button and the manager's "Reset Password" button. Uses the
   * native `hidden` attribute rather than a CSS class — no inline styles
   * either way.
   */
  function initToggles() {
    var toggles = document.querySelectorAll('.js-toggle');
    toggles.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-target');
        var target = document.getElementById(targetId);
        if (target) {
          target.hidden = !target.hidden;
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initMenuButtons();
    initToggles();
    renderCart();
  });
})();
