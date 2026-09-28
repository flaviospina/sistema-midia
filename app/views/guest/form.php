<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-1">Enviar fotos e vídeos para a mídia</h2>
    <p class="text-muted small">Participou de um culto ou evento e registrou algo? Envie por aqui. A equipe de mídia revisa antes de publicar.</p>
    <form method="post" action="<?= url('/enviar') ?>" novalidate>
      <?= Csrf::field() ?>
      <div class="honeypot" aria-hidden="true"><label>Site<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="mb-3"><label class="form-label required" for="guest_name">Seu nome</label><input type="text" class="form-control<?= invalid('guest_name') ?>" id="guest_name" name="guest_name" value="<?= e(old('guest_name')) ?>" maxlength="150" required><?= field_error('guest_name') ?></div>
      <div class="mb-3"><label class="form-label required" for="whatsapp">WhatsApp</label><input type="tel" class="form-control<?= invalid('whatsapp') ?>" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp')) ?>" data-mask="phone" placeholder="(11) 98888-7777" required><?= field_error('whatsapp') ?></div>
      <div class="mb-3">
        <label class="form-label" for="ministry_id">Ministério</label>
        <select class="form-select" id="ministry_id" name="ministry_id"><option value="">— Nenhum / não sei —</option>
          <?php foreach ($ministries as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], old('ministry_id')) ?>><?= e($m['name']) ?></option><?php endforeach; ?>
          <option value="__outro"<?= selected('__outro', old('ministry_id')) ?>>Outro…</option>
        </select>
        <div id="ministry_other_wrap" class="mt-2 d-none"><input type="text" class="form-control" name="ministry_other" value="<?= e(old('ministry_other')) ?>" maxlength="120" placeholder="Qual ministério?"></div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="event">Evento</label>
        <select class="form-select" id="event" name="event"><option value="">— Selecione —</option>
          <?php foreach ($events as $ev): ?><option value="<?= e($ev) ?>"<?= selected($ev, old('event')) ?>><?= e($ev) ?></option><?php endforeach; ?>
          <option value="__outro"<?= selected('__outro', old('event')) ?>>Outro…</option>
        </select>
        <div id="event_other_wrap" class="mt-2 d-none"><input type="text" class="form-control<?= invalid('event_other') ?>" name="event_other" value="<?= e(old('event_other')) ?>" maxlength="150" placeholder="Nome do evento e data"><?= field_error('event_other') ?></div>
      </div>
      <div class="mb-3"><label class="form-label" for="description">Descrição</label><textarea class="form-control" id="description" name="description" rows="2" maxlength="2000" placeholder="O que são os arquivos? Quem aparece?"><?= e(old('description')) ?></textarea></div>
      <?php if ($captcha): ?>
        <div class="mb-3"><div class="h-captcha" data-sitekey="<?= e(HCAPTCHA_SITE_KEY) ?>"></div><?= field_error('captcha') ?></div>
      <?php endif; ?>
      <div class="form-check mb-3">
        <input class="form-check-input<?= invalid('accept') ?>" type="checkbox" id="accept" name="accept" value="1" required>
        <label class="form-check-label small" for="accept">
          <strong>Declaração de uso de imagem:</strong> declaro que tenho autorização das pessoas que aparecem nos arquivos e autorizo a Mídia ADMoema a guardar e usar este material nas comunicações da igreja (telão, redes sociais, boletim), respeitando o <a href="<?= url('/termo-de-uso') ?>" target="_blank">termo de privacidade</a>. Meus dados (nome, WhatsApp, IP) serão guardados como registro deste envio.
        </label>
        <?= field_error('accept') ?>
      </div>
      <button class="btn btn-primary w-100">Continuar para o envio</button>
    </form>
  </div>
</div>
<?php if ($captcha): ?><script src="https://js.hcaptcha.com/1/api.js" async defer></script><?php endif; ?>
