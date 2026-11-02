<?php
/**
 * Metatags para redes sociais (Open Graph + Twitter Card). Recebe $meta opcional:
 * title, description, url, image, image_width, image_height, type. Sem $meta usa a imagem padrão do sistema.
 */
$m = $meta ?? [];
$ogTitle = $m['title'] ?? (($title ?? '') !== '' ? $title . ' · ' . APP_NAME : APP_NAME);
$ogDesc = $m['description'] ?? 'Fotos dos cultos e eventos da Assembleia de Deus Ministério do Belém · Setor 124 Moema.';
$ogUrl = $m['url'] ?? absolute_url('/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(BASE_PATH)), '/'));
$ogImage = $m['image'] ?? absolute_url('/assets/img/og-default.jpg');
$ogW = $m['image_width'] ?? (isset($m['image']) ? null : 1200);
$ogH = $m['image_height'] ?? (isset($m['image']) ? null : 630);
?>
<meta name="description" content="<?= e($ogDesc) ?>">
<meta name="theme-color" content="#0b1f3a">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:locale" content="pt_BR">
<meta property="og:type" content="<?= e($m['type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDesc) ?>">
<meta property="og:url" content="<?= e($ogUrl) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:secure_url" content="<?= e($ogImage) ?>">
<meta property="og:image:type" content="image/jpeg">
<?php if ($ogW && $ogH): ?><meta property="og:image:width" content="<?= (int) $ogW ?>">
<meta property="og:image:height" content="<?= (int) $ogH ?>">
<?php endif; ?>
<meta property="og:image:alt" content="<?= e($ogTitle) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<link rel="canonical" href="<?= e($ogUrl) ?>">
