<?php /** @var array $u  @var int $size */ $size = in_array($size ?? 40, [28, 40, 64, 96], true) ? $size : 40; ?>
<span class="avatar avatar-initials avatar-<?= $size ?> rounded-circle"><?= e(initials($u['name'])) ?></span>
