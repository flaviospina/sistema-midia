<?php
// app/controllers/SocialController.php — compartilhar uma foto nas redes sociais da própria pessoa
declare(strict_types=1);

final class SocialController
{
    public function form(int $id): never
    {
        $p = Photo::find($id) ?? abort(404);
        if ((int) $p['album_hidden'] === 1 && !Auth::can('albums.hidden')) {
            abort(403);
        }
        $defaultCaption = $p['caption'] ?: ($p['album_name'] . ' · ' . format_date($p['event_date']) . "\n\nAD Moema #ADMoema #igreja");
        view('photos/share', ['title' => 'Compartilhar · ' . $p['file_name'], 'p' => $p, 'accounts' => SocialAccount::forUser((int) Auth::id()), 'posts' => SocialAccount::postsFor($id), 'defaultCaption' => $defaultCaption, 'cryptoOk' => Crypto::ready()]);
    }

    public function publish(int $id): never
    {
        $p = Photo::find($id) ?? abort(404);
        if ((int) $p['album_hidden'] === 1 && !Auth::can('albums.hidden')) {
            abort(403);
        }
        $networks = array_values(array_intersect((array) ($_POST['networks'] ?? []), array_keys(SocialAccount::NETWORKS)));
        $caption = mb_substr(input('caption'), 0, 2200);
        if (!$networks) {
            flash('warning', 'Escolha ao menos uma rede.');
            redirect('/fotos/' . $id . '/compartilhar');
        }
        if (RateLimit::count('social', (string) Auth::id(), 60) >= 20) {
            flash('danger', 'Muitas publicações na última hora. Aguarde um pouco.');
            redirect('/fotos/' . $id . '/compartilhar');
        }
        RateLimit::hit('social', (string) Auth::id());
        $imageUrl = absolute_url('/p/' . PublicLink::issue($id) . '.jpg');
        foreach ($networks as $n) {
            $acc = SocialAccount::get((int) Auth::id(), $n);
            $token = $acc ? SocialAccount::token($acc) : null;
            if (!$acc || !$token) {
                flash('warning', SocialAccount::NETWORKS[$n] . ': conta não configurada em Meus dados → Redes sociais.');
                continue;
            }
            try {
                $r = $n === 'instagram' ? Social::publishInstagram($acc['account_id'], $token, $imageUrl, $caption) : Social::publishFacebook($acc['account_id'], $token, $imageUrl, $caption);
                SocialAccount::logPost($id, $n, $caption, true, $r['id'] ?: null, $r['permalink'], null);
                Logger::audit('foto_publicada', 'photos', $id, null, ['rede' => $n, 'external_id' => $r['id']]);
                flash('success', SocialAccount::NETWORKS[$n] . ': publicado!' . ($r['permalink'] ? ' Abra a publicação pelo histórico abaixo.' : ''));
            } catch (Throwable $e) {
                SocialAccount::logPost($id, $n, $caption, false, null, null, $e->getMessage());
                flash('danger', SocialAccount::NETWORKS[$n] . ': ' . $e->getMessage());
            }
        }
        redirect('/fotos/' . $id . '/compartilhar');
    }
}
