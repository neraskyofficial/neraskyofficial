<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$me = require_user();
$id = (int) ($_GET['id'] ?? 0);

$sv = row('SELECT * FROM services WHERE id = ?', [$id]);
if (!$sv || (int) $sv['seller_id'] !== (int) $me['id']) {
    flash('warn', 'That service doesn\'t exist or isn\'t yours to edit.');
    redirect('dashboard.php');
}

$errors = [];
$uploadedImages = [];
$serviceImages = rows('SELECT id, image_path FROM service_images WHERE service_id = ? ORDER BY sort_order, id', [$id]);
$old = [
    'category'      => (string) $sv['category'],
    'title'         => (string) $sv['title'],
    'description'   => (string) $sv['description'],
    'price'         => (string) (float) $sv['price'],
    'delivery_days' => (int) $sv['delivery_days'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $imagePaths = array_column($serviceImages, 'image_path');
        q('DELETE FROM services WHERE id = ? AND seller_id = ?', [$id, $me['id']]);
        foreach ($imagePaths as $imagePath) {
            delete_service_image_file((string) $imagePath);
        }
        flash('info', 'Service removed.');
        redirect('dashboard.php');
    }

    $old['category']      = (string) ($_POST['category'] ?? '');
    $old['title']         = trim((string) ($_POST['title'] ?? ''));
    $old['description']   = trim((string) ($_POST['description'] ?? ''));
    $old['price']         = (string) ($_POST['price'] ?? '');
    $old['delivery_days'] = max(1, min(30, (int) ($_POST['delivery_days'] ?? 3)));

    $price = clean_price($old['price']);
    if (!in_array($old['category'], CATEGORIES, true)) {
        $errors[] = 'Please choose a category.';
    }
    if (mb_strlen($old['title']) < 8 || mb_strlen($old['title']) > 140) {
        $errors[] = 'Title must be between 8 and 140 characters.';
    }
    if (mb_strlen($old['description']) < 40) {
        $errors[] = 'Please add a bit more detail to the description.';
    }
    if ($price < 50) {
        $errors[] = 'Minimum price is ₱50.';
    }

    $existingImageIds = array_map(static fn(array $image): int => (int) $image['id'], $serviceImages);
    $removeImageIds = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['remove_images'] ?? [])),
        static fn(int $imageId): bool => in_array($imageId, $existingImageIds, true)
    )));
    $imagesToRemove = array_values(array_filter(
        $serviceImages,
        static fn(array $image): bool => in_array((int) $image['id'], $removeImageIds, true)
    ));
    $remainingImages = count($serviceImages) - count($imagesToRemove);
    $imageUploads = normalize_multi_uploads($_FILES['showcase_images'] ?? []);
    if ($remainingImages + count($imageUploads) > MAX_SERVICE_IMAGES) {
        $errors[] = 'Keep up to ' . MAX_SERVICE_IMAGES . ' showcase images. Remove an image before adding more.';
    }

    if ($errors === []) {
        foreach ($imageUploads as $imageUpload) {
            [$uploaded, $path] = upload_service_image($imageUpload);
            if (!$uploaded) {
                $errors[] = $path;
                break;
            }
            $uploadedImages[] = $path;
        }
    }

    if ($errors === []) {
        $db = db();
        $db->beginTransaction();
        try {
            q('UPDATE services SET category = ?, title = ?, description = ?, price = ?, delivery_days = ?, status = "pending", updated_at = NOW()
               WHERE id = ? AND seller_id = ?',
                [$old['category'], $old['title'], $old['description'], $price, $old['delivery_days'], $id, $me['id']]);
            foreach ($removeImageIds as $imageId) {
                q('DELETE FROM service_images WHERE id = ? AND service_id = ?', [$imageId, $id]);
            }
            foreach ($uploadedImages as $index => $imagePath) {
                q('INSERT INTO service_images (service_id, image_path, sort_order) VALUES (?,?,?)', [$id, $imagePath, $remainingImages + $index]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($uploadedImages as $imagePath) {
                delete_service_image_file($imagePath);
            }
            throw $e;
        }
        foreach ($imagesToRemove as $image) {
            delete_service_image_file((string) $image['image_path']);
        }
        flash('ok', 'Service updated — it goes back to a moderator for a quick re-check.');
        redirect('dashboard.php');
    }

    foreach ($uploadedImages as $imagePath) {
        delete_service_image_file($imagePath);
    }
    $uploadedImages = [];
}

$pageTitle  = 'Edit service';
$currentNav = 'sell';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Seller tools</span>
    <h1>Edit service</h1>
    <p class="muted"><?= sb((string) $sv['status']) ?> <span class="faint">· Any change sends it back for a quick re-review.</span></p>
  </div>
</section>

<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <?php foreach ($errors as $err): ?>
      <div class="banner banner-bad mb-2"><span class="b-ic">✕</span><span><?= e($err) ?></span></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" action="<?= e(base_url('service-edit.php?id=' . $id)) ?>" class="card card-pad" style="max-width:760px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <div class="grid-2 gap-2">
        <div class="field">
          <label for="category">Category</label>
          <select class="input" id="category" name="category" required>
            <?php foreach (CATEGORIES as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $old['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="delivery_days">Delivery in (days)</label>
          <input class="input" type="number" id="delivery_days" name="delivery_days" min="1" max="30" value="<?= (int) $old['delivery_days'] ?>" required>
        </div>
      </div>
      <div class="field">
        <label for="title">Title</label>
        <input class="input" id="title" name="title" maxlength="140" required value="<?= e($old['title']) ?>">
      </div>
      <div class="field">
        <label for="description">Description</label>
        <textarea class="input" id="description" name="description" rows="7" required><?= e($old['description']) ?></textarea>
      </div>
      <div class="field">
        <label for="showcase_images">Showcase photos <span class="faint" style="font-weight:500">(optional)</span></label>
        <?php if ($serviceImages): ?>
          <div class="seller-image-manage-grid">
            <?php foreach ($serviceImages as $image): ?>
              <label class="seller-image-manage">
                <img src="<?= e(asset_url((string) $image['image_path'])) ?>" alt="Current showcase photo">
                <span><input type="checkbox" name="remove_images[]" value="<?= (int) $image['id'] ?>"> Remove photo</span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <input class="input" id="showcase_images" name="showcase_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
        <span class="hint">Up to <?= MAX_SERVICE_IMAGES ?> photos total; JPG, PNG, or WebP, <?= MAX_UPLOAD_MB ?> MB max each. Select a current photo to remove it.</span>
      </div>
      <div class="field">
        <label for="price">Price</label>
        <div class="price-wrap">
          <span class="peso">₱</span>
          <input type="number" step="0.01" min="50" id="price" name="price" required value="<?= e($old['price']) ?>">
        </div>
      </div>
      <div class="flex-between">
        <a class="btn btn-ghost" href="<?= e(base_url('service.php?id=' . $id)) ?>">Preview</a>
        <div class="quick-actions">
          <button class="btn btn-primary" type="submit">Save changes</button>
          <button class="btn btn-danger" type="submit" name="action" value="delete" data-confirm="Delete this service permanently?">Delete</button>
        </div>
      </div>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>