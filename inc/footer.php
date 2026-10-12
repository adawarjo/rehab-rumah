</main>

<nav class="bottomnav d-lg-none d-print-none">
  <?php foreach ($bottom as $k): [$label, $icon] = $menu[$k]; ?>
    <a href="<?= $k ?>.php" class="<?= $active === $k ? 'active' : '' ?>">
      <i class="bi bi-<?= $icon ?>"></i><span><?= $k === 'index' ? 'Dashboard' : explode(' ', $label)[0] ?></span>
    </a>
  <?php endforeach; ?>
  <a href="#" data-bs-toggle="offcanvas" data-bs-target="#sidemenu" class="<?= in_array($active, $bottom, true) ? '' : 'active' ?>">
    <i class="bi bi-grid"></i><span>Menu</span>
  </a>
</nav>

<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/sweetalert2.all.min.js"></script>
<script src="assets/app.js"></script>
<?= $extra_js ?? '' ?>
</body>
</html>
