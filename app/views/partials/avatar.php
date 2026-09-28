<?php
/** @var array $u  @var int $size */
$size = in_array($size ?? 40, [28, 40, 64, 96, 128], true) ? $size : 40;
$canSee = Auth::id() === (int) $u['id'] || Auth::isMedia() || Auth::is('pastor');
?>
<?php if (!empty($u['photo_path']) && $canSee): ?>
  <img src="<?= url('/usuarios/' . (int) $u['id'] . '/foto') ?>" alt="" class="avatar avatar-<?= $size ?> rounded-circle">
<?php else: ?>
  <span class="avatar avatar-initials avatar-<?= $size ?> rounded-circle"><?= e(initials($u['name'])) ?></span>
<?php endif; ?>
