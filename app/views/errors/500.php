<div class="text-center py-5">
  <div class="display-4 text-muted"><?= (int) http_response_code() ?></div>
  <h1 class="h4"><?= e($title) ?></h1>
  <p class="text-muted"><?= e($message ?: 'Não foi possível concluir a solicitação.') ?></p>
  <a href="<?= url('/') ?>" class="btn btn-outline-primary">Voltar ao início</a>
</div>
