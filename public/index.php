<?php

/**
 * Public menu — no login required. Reachable at /index.php?cafe=<slug>.
 * See resolve_public_cafe() for how the café is chosen when no slug is
 * given (single-café fallback), and list_all_active_cafes() for the
 * "choose your café" picker shown when there's more than one to pick from.
 */

declare(strict_types=1);

require_once __DIR__ . '/../private/includes/bootstrap.php';
require_once CONTROLLERS_PATH . '/CafeController.php';
require_once CONTROLLERS_PATH . '/MenuController.php';

$slug = trim((string) ($_GET['cafe'] ?? ''));
$cafe = resolve_public_cafe(db(), $slug);
$menuCategories = $cafe !== null ? list_menu_by_category(db(), (int) $cafe['id']) : [];
$cafeDirectory = ($cafe === null && $slug === '') ? list_all_active_cafes(db()) : [];

require VIEWS_PATH . '/public_menu.view.php';
