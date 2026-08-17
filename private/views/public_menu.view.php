<?php
/**
 * @var array|null $cafe
 * @var string $slug
 * @var array $menuCategories
 * @var array $cafeDirectory  only populated when $cafe is null and $slug is empty
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $cafe !== null ? htmlspecialchars($cafe['name'], ENT_QUOTES, 'UTF-8') . ' — Menu' : 'Caffe BMS' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="public-page">
    <?php if ($cafe === null && $slug !== ''): ?>
        <main class="auth-page">
            <div class="auth-card auth-card--center">
                <div class="auth-brand">
                    <span class="auth-brand-word">Caffe BMS</span>
                    <svg class="auth-brand-swash" viewBox="0 0 140 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M2 6.5C22 2 40 10 60 6C80 2 98 10 118 5.5C126 4 132 6 138 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <p class="auth-subtitle">We couldn't find a café at that link.</p>
                </div>
            </div>
        </main>
    <?php elseif ($cafe === null): ?>
        <main class="public-menu">
            <header class="public-menu-hero">
                <span class="public-menu-hero-word">Caffe BMS</span>
                <h1>Choose your café</h1>
            </header>

            <?php if (empty($cafeDirectory)): ?>
                <div class="staff-empty">No cafés are published yet — check back soon.</div>
            <?php else: ?>
                <ul class="cafe-directory">
                    <?php foreach ($cafeDirectory as $listing): ?>
                        <li>
                            <a href="/index.php?cafe=<?= urlencode($listing['slug']) ?>" class="cafe-directory-link">
                                <?= htmlspecialchars($listing['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </main>
    <?php else: ?>
        <main class="public-menu">
            <header class="public-menu-hero">
                <span class="public-menu-hero-word">Caffe BMS</span>
                <h1><?= htmlspecialchars($cafe['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if (!empty($cafe['address'])): ?>
                    <p class="public-menu-address"><?= htmlspecialchars($cafe['address'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </header>

            <?php if (empty($menuCategories)): ?>
                <div class="staff-empty">The menu isn't published yet — check back soon.</div>
            <?php else: ?>
                <?php foreach ($menuCategories as $category): ?>
                    <section class="public-menu-category">
                        <h2><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="public-menu-grid">
                            <?php foreach ($category['items'] as $item): ?>
                                <article class="public-menu-item">
                                    <div class="public-menu-item-top">
                                        <h3><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <span class="public-menu-item-price">$<?= number_format((float) $item['price'], 2) ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="public-menu-item-desc"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    <?php endif; ?>
</body>
</html>
