<?php
/**
 * @var array $user
 * @var array $orders
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen — Caffe BMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="staff-page">
    <?php require VIEWS_PATH . '/partials/staff_header.view.php'; ?>

    <main class="staff-main">
        <h1>Kitchen Queue</h1>

        <?php if (empty($orders)): ?>
            <div class="staff-empty">No orders in the queue right now.</div>
        <?php else: ?>
            <div class="order-board">
                <?php foreach ($orders as $order): ?>
                    <article class="order-card order-card--<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="order-card-top">
                            <span class="order-card-id">Order #<?= (int) $order['id'] ?></span>
                            <span class="order-card-status"><?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <?php if (!empty($order['customer_name'])): ?>
                            <div class="order-card-customer"><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <ul class="order-item-list">
                            <?php foreach ($order['items'] as $item): ?>
                                <li>
                                    <span><span class="order-item-qty">&times;<?= (int) $item['quantity'] ?></span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                                <?php if (!empty($item['notes'])): ?>
                                    <li><span class="order-item-note"><?= htmlspecialchars($item['notes'], ENT_QUOTES, 'UTF-8') ?></span></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>

                        <form method="post" action="/Barista_dashboard.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <?php if ($order['status'] === 'queued'): ?>
                                <input type="hidden" name="new_status" value="preparing">
                                <button type="submit" class="btn-advance">Start Preparing</button>
                            <?php else: ?>
                                <input type="hidden" name="new_status" value="ready">
                                <button type="submit" class="btn-advance">Mark Ready</button>
                            <?php endif; ?>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="/js/main.js"></script>
</body>
</html>
