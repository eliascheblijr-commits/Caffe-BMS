<?php
/**
 * @var array $user
 * @var array $menuCategories  from list_menu_by_category()
 * @var array $readyOrders     from list_ready_orders()
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier — Caffe BMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="staff-page">
    <?php require VIEWS_PATH . '/partials/staff_header.view.php'; ?>

    <main class="staff-main">
        <h1>Cashier</h1>

        <h2 class="section-title">New Order</h2>
        <section class="pos-layout">
            <div class="menu-panel">
                <?php if (empty($menuCategories)): ?>
                    <div class="staff-empty">No menu items yet — add some from the manager dashboard.</div>
                <?php else: ?>
                    <?php foreach ($menuCategories as $category): ?>
                        <div class="menu-category">
                            <h3 class="menu-category-title"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="menu-grid">
                                <?php foreach ($category['items'] as $item): ?>
                                    <button
                                        type="button"
                                        class="menu-item-btn"
                                        data-id="<?= (int) $item['id'] ?>"
                                        data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-price="<?= htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <span class="menu-item-name"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="menu-item-price">$<?= number_format((float) $item['price'], 2) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <aside class="cart-panel">
                <h2>Current Order</h2>
                <ul id="cart-list" class="cart-list">
                    <li class="cart-empty">No items yet — tap the menu to add.</li>
                </ul>
                <div class="cart-total-row">
                    <span>Total</span>
                    <span id="cart-total">$0.00</span>
                </div>

                <form method="post" action="/cashier_dashboard.php" id="order-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="create_order">
                    <input type="hidden" name="cart_data" id="cart-data" value="[]">

                    <label for="customer_name">Customer name (optional)</label>
                    <input type="text" id="customer_name" name="customer_name" maxlength="200">

                    <label for="customer_contact">Contact (optional)</label>
                    <input type="text" id="customer_contact" name="customer_contact" maxlength="100">

                    <button type="submit" class="btn-primary" id="place-order-btn" disabled>Place Order</button>
                </form>
            </aside>
        </section>

        <h2 class="section-title">Ready for Pickup</h2>
        <?php if (empty($readyOrders)): ?>
            <div class="staff-empty">Nothing waiting for pickup.</div>
        <?php else: ?>
            <div class="order-board">
                <?php foreach ($readyOrders as $order): ?>
                    <article class="order-card order-card--ready">
                        <div class="order-card-top">
                            <span class="order-card-id">Order #<?= (int) $order['id'] ?></span>
                            <span class="order-card-total">$<?= number_format((float) $order['total_amount'], 2) ?></span>
                        </div>
                        <?php if (!empty($order['customer_name'])): ?>
                            <div class="order-card-customer"><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <button type="button" class="btn-advance js-toggle-payment" data-target="payment-<?= (int) $order['id'] ?>">
                            Collect Payment
                        </button>

                        <form method="post" action="/cashier_dashboard.php" class="payment-form" id="payment-<?= (int) $order['id'] ?>" hidden>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="complete_payment">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">

                            <label for="method-<?= (int) $order['id'] ?>">Payment method</label>
                            <select id="method-<?= (int) $order['id'] ?>" name="method">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="other">Other</option>
                            </select>

                            <label for="payer-<?= (int) $order['id'] ?>">Payer name (optional)</label>
                            <input type="text" id="payer-<?= (int) $order['id'] ?>" name="payer_name" maxlength="100">

                            <button type="submit" class="btn-primary">Confirm $<?= number_format((float) $order['total_amount'], 2) ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="/js/main.js"></script>
</body>
</html>
