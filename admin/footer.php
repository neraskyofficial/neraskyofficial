<?php declare(strict_types=1); ?>
</main>
<div class="confirm-backdrop hidden" id="confirmBackdrop" role="dialog" aria-modal="true" aria-labelledby="confirmTitle"><div class="confirm-dialog"><h3 id="confirmTitle">Are you sure?</h3><p id="confirmText">This action cannot be undone.</p><div class="confirm-actions"><button class="btn btn-ghost" id="confirmCancel" type="button">Cancel</button><button class="btn btn-primary" id="confirmOk" type="button">Continue</button></div></div></div>
<script src="<?= e(base_url('assets/js/select-ui.js?v=' . (is_file(ADMIN_APP_ROOT . '/assets/js/select-ui.js') ? filemtime(ADMIN_APP_ROOT . '/assets/js/select-ui.js') : '1'))) ?>"></script>
<script src="<?= e(base_url('assets/js/file-ui.js?v=' . (is_file(ADMIN_APP_ROOT . '/assets/js/file-ui.js') ? filemtime(ADMIN_APP_ROOT . '/assets/js/file-ui.js') : '1'))) ?>"></script>
<script src="<?= e(base_url('assets/js/main.js?v=' . (is_file(ADMIN_APP_ROOT . '/assets/js/main.js') ? filemtime(ADMIN_APP_ROOT . '/assets/js/main.js') : '1'))) ?>"></script>
</body></html>
