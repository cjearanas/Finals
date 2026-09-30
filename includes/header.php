<?php
/**
 * Expects before include:
 *   $pageTitle  (string)  e.g. "Dashboard"
 *   $activePage (string)  'dashboard' | 'report' | 'log'
 */
require_once __DIR__ . '/data.php';

$nav = [
    'dashboard' => ['href' => 'index.php',        'icon' => 'bi-house-door',          'label' => 'Dashboard'],
    'report'    => ['href' => 'report-issue.php',     'icon' => 'bi-exclamation-circle',  'label' => 'Report an Issue'],
    'log'       => ['href' => 'maintenance-log.php',  'icon' => 'bi-clipboard-check',     'label' => 'Maintenance Log'],
    'account'   => ['href' => 'account.php',          'icon' => 'bi-person-circle',       'label' => 'Account'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FixTrack | <?= e($pageTitle) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    .fixtrack-logo { width: 45px; height: 45px; object-fit: contain; }
    .dashboard-box { min-height: 177px; }
    .fixtrack-footer { background-color: #212529; color: white; text-align: center; padding: 5px; }
    .footer-logo { width: 40px; height: 40px; object-fit: contain; }

    /* Scrollable report lists (shows a few rows, scroll for the rest) */
    .report-scroll { max-height: 22rem; overflow-y: auto; }
    .report-scroll::-webkit-scrollbar { width: 14px; }
    .report-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
    .report-scroll::-webkit-scrollbar-thumb { background: #555; border-radius: 10px; border: 3px solid #f1f1f1; }
    .report-scroll::-webkit-scrollbar-thumb:hover { background: #333; }
    .report-scroll::-webkit-scrollbar-button:single-button {
      display: block; height: 14px; background-color: #f1f1f1; background-repeat: no-repeat;
      background-position: center; background-size: 9px;
    }
    .report-scroll::-webkit-scrollbar-button:single-button:vertical:decrement {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10'%3E%3Cpath d='M5 2 L9 8 H1 Z' fill='%23999'/%3E%3C/svg%3E");
    }
    .report-scroll::-webkit-scrollbar-button:single-button:vertical:increment {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10'%3E%3Cpath d='M5 8 L9 2 H1 Z' fill='%23999'/%3E%3C/svg%3E");
    }
    @supports (-moz-appearance: none) {            /* Firefox */
      .report-scroll { scrollbar-color: #555 #f1f1f1; }
    }
  </style>
  <?= $extraHead ?? '' ?>
</head>

<body>
<div class="container-fluid p-0">
  <div class="row g-0 min-vh-100">

    <!-- ================= SIDEBAR ================= -->
    <aside class="col-12 col-md-3 col-xl-2 bg-dark text-white d-flex flex-column">

      <div class="p-3 border-bottom border-secondary">
        <a href="index.php" class="text-decoration-none text-white d-flex align-items-center justify-content-center">
          <img src="Images/fixtrack.png" alt="FIXTRACK Logo" class="fixtrack-logo me-2">
          <span class="fw-bold fs-5">FIXTRACK</span>
        </a>
      </div>

      <nav class="nav flex-column mt-2">
        <?php foreach ($nav as $key => $item): ?>
          <a class="nav-link rounded-0 <?= $key === $activePage ? 'active bg-primary text-white' : 'text-white-50' ?>"
             href="<?= e($item['href']) ?>">
            <i class="bi <?= e($item['icon']) ?> me-2"></i>
            <?= e($item['label']) ?>
          </a>
        <?php endforeach; ?>
      </nav>

    </aside>

    <!-- ================= CONTENT ================= -->
    <div class="col-12 col-md-9 col-xl-10 d-flex flex-column">

      <header class="bg-dark text-white px-4 py-3 d-flex justify-content-between align-items-center">
        <strong><?= e($pageTitle) ?></strong>

        <div class="dropdown">
          <a href="#" class="text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
            <?php if ($currentUser['avatar']): ?>
              <img src="<?= e(avatar_url($currentUser['avatar'])) ?>" alt="" class="rounded-circle me-2 align-middle"
                   style="width:30px;height:30px;object-fit:cover">
            <?php else: ?>
              <i class="bi bi-person-circle fs-5 me-2 align-middle"></i>
            <?php endif; ?>
            <?= e($currentUser['name']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="account.php">Account</a></li>
            <li><a class="dropdown-item" href="#">Log out</a></li>
          </ul>
        </div>
      </header>

      <main class="flex-grow-1 bg-body-secondary p-4">

        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
          </ol>
        </nav>