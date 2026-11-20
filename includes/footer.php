<?php
declare(strict_types=1);
?>
            </div>

            <footer class="app-footer">
                <p>© <?= date('Y') ?> <?= e(APP_INSTITUTION) ?> · <?= e(APP_NAME) ?> <?= e(APP_VERSION) ?></p>
                <p class="app-footer__meta"><?= e(APP_FULL_NAME) ?></p>
            </footer>
        </main>
    </div>
</div>

<div class="confirm-overlay" data-confirm-overlay hidden>
    <div class="modal modal--sm" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message" tabindex="-1">
        <div class="modal__header">
            <h2 class="modal__title" id="confirm-title">Are you sure?</h2>
        </div>
        <div class="modal__body">
            <p id="confirm-message">This action cannot be undone.</p>
        </div>
        <div class="modal__footer">
            <button class="btn btn--secondary" type="button" data-confirm-cancel>Cancel</button>
            <button class="btn btn--danger" type="button" data-confirm-accept>Delete</button>
        </div>
    </div>
</div>

<script src="<?= e(asset('assets/js/theme.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/sidebar.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
