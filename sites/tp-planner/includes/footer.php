    <footer class="mt-auto py-3 bg-light border-top">
        <div class="container text-center text-muted small">
            &copy; <?= date('Y') ?> <?= escape(APP_NAME) ?>. <?= escape(t('footer.app')) ?>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
