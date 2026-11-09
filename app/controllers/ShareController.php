<?php
// app/controllers/ShareController.php — links públicos com pré-visualização (Open Graph) para WhatsApp, Instagram, Facebook etc.
declare(strict_types=1);

final class ShareController
{
    private function days(): int
    {
        return isset(ShareLink::VALIDITY[input('days')]) ? (int) input('days') : 30;
    }

    public function createForPhoto(int $id): never
    {
        $p = Photo::find($id) ?? abort(404);
        if ((int) $p['album_hidden'] === 1 && !Auth::can('albums.hidden')) {
            abort(403);
        }
        $token = ShareLink::create($id, null, $this->days());
        Logger::audit('link_publico_criado', 'photos', $id, null, ['dias' => $this->days()]);
        $_SESSION['_share_link'] = ['url' => absolute_url('/s/' . $token), 'label' => $p['file_name']];
        redirect('/albuns/' . $p['album_id'], ['foto' => $id]);
    }

    public function createForAlbum(int $id): never
    {
        $a = Album::find($id) ?? abort(404);
        if (!Album::canView($a)) {
            abort(403);
        }
        $token = ShareLink::create(null, $id, $this->days());
        Logger::audit('link_publico_criado', 'photo_albums', $id, null, ['dias' => $this->days()]);
        $_SESSION['_share_link'] = ['url' => absolute_url('/s/' . $token), 'label' => $a['name']];
        redirect('/albuns/' . $id);
    }

    public function revoke(int $id): never
    {
        $l = Database::one('SELECT l.*, p.album_id AS photo_album FROM em_share_links l LEFT JOIN em_photos p ON p.id = l.photo_id WHERE l.id = :id', ['id' => $id]) ?? abort(404);
        ShareLink::revoke($id);
        Logger::audit('link_publico_revogado', 'share_links', $id);
        flash('success', 'Link desativado. Quem o tiver não consegue mais abrir.');
        redirect('/albuns/' . ($l['album_id'] ?: $l['photo_album']));
    }

    /** Página pública (sem login) com as metatags Open Graph/Twitter. */
    public function view(string $token): never
    {
        $l = ShareLink::resolve($token) ?? abort(404, 'Este link não existe, expirou ou foi desativado.');
        ShareLink::hit((int) $l['id']);
        $base = absolute_url('/s/' . $token);
        if ($l['photo_id']) {
            $p = Photo::find((int) $l['photo_id']) ?? abort(404);
            $meta = [
                'title'       => ($p['caption'] ?: $p['album_name']) . ' · ' . format_date($p['event_date']),
                'description' => 'Foto do ' . $p['album_name'] . ' (' . weekday_pt($p['event_date']) . ', ' . format_date($p['event_date']) . ') — AD Moema.',
                'url'         => $base,
                'image'       => $base . '/cartao.jpg',
                'image_width' => SocialCard::WIDTH,
                'image_height'=> SocialCard::HEIGHT,
                'image_alt'   => ($p['caption'] ?: $p['album_name']) . ' — foto do ' . $p['album_name'] . ', AD Moema',
                'type'        => 'article',
            ];
            view('share/photo', ['title' => $meta['title'], 'meta' => $meta, 'p' => $p, 'l' => $l, 'token' => $token, 'canonical' => $base], 'layout_public');
        }
        $photos = self::albumPhotos($l);
        $cover = self::cover($l, $photos);
        $meta = [
            'title'       => $l['album_name'] . ' · ' . format_date($l['event_date']),
            'description' => count($photos) . ' foto(s) do ' . $l['album_name'] . ' (' . weekday_pt($l['event_date']) . ', ' . format_date($l['event_date']) . ')' . ($l['album_description'] ? ' — ' . $l['album_description'] : '') . ' — AD Moema.',
            'url'         => $base,
            'image'       => $base . '/cartao.jpg',
            'image_width' => SocialCard::WIDTH,
            'image_height'=> SocialCard::HEIGHT,
            'image_alt'   => 'Capa do álbum ' . $l['album_name'] . ' (' . format_date($l['event_date']) . ') — AD Moema',
            'type'        => 'website',
        ];
        view('share/album', ['title' => $meta['title'], 'meta' => $meta, 'l' => $l, 'photos' => $photos, 'token' => $token, 'canonical' => $base], 'layout_public');
    }

    /** @return list<array> fotos visíveis (originais com miniatura) do álbum do link */
    private static function albumPhotos(array $l): array
    {
        return array_values(array_filter(Photo::forAlbum((int) $l['album_id']), static fn($x) => $x['parent_id'] === null && $x['has_thumb']));
    }

    private static function cover(array $l, array $photos): ?array
    {
        foreach ($photos as $x) {
            if ((int) $x['id'] === (int) $l['cover_photo_id']) {
                return $x;
            }
        }
        return $photos[0] ?? null;
    }

    /** Imagem de prévia 1200×630 (Open Graph) do link: capa + nome do culto + data + logo. */
    public function card(string $token): never
    {
        $l = ShareLink::resolve($token) ?? abort(404);
        if ($l['photo_id']) {
            $path = SocialCard::forPhoto(Photo::find((int) $l['photo_id']) ?? abort(404));
        } else {
            $photos = self::albumPhotos($l);
            $path = SocialCard::forAlbum($l, self::cover($l, $photos), count($photos));
        }
        header('Cross-Origin-Resource-Policy: cross-origin');
        Delivery::send($path, 'image/jpeg', 'previa.jpg', true, 3600);
    }

    /** Imagem pública da foto compartilhada (versão de exibição, sem EXIF). */
    public function image(string $token): never
    {
        $l = ShareLink::resolve($token) ?? abort(404);
        if (!$l['photo_id']) {
            abort(404);
        }
        $this->sendDisplay(Photo::find((int) $l['photo_id']) ?? abort(404));
    }

    /** Imagem (ou miniatura com ?t=1) de uma foto de um álbum compartilhado. */
    public function albumImage(string $token, int $photoId): never
    {
        $l = ShareLink::resolve($token) ?? abort(404);
        $p = Photo::find($photoId) ?? abort(404);
        if (!$l['album_id'] || (int) $p['album_id'] !== (int) $l['album_id']) {
            abort(404);
        }
        if (query('t') === '1' && is_file(Photo::thumbPath($photoId))) {
            Delivery::send(Photo::thumbPath($photoId), 'image/jpeg', 'miniatura.jpg', true, 86400);
        }
        $this->sendDisplay($p);
    }

    private function sendDisplay(array $p): never
    {
        $path = is_file(Photo::displayPath((int) $p['id'])) ? Photo::displayPath((int) $p['id']) : Photo::path($p);
        $mime = is_file(Photo::displayPath((int) $p['id'])) ? 'image/jpeg' : $p['mime'];
        header('Cross-Origin-Resource-Policy: cross-origin');
        Delivery::send($path, $mime, pathinfo($p['file_name'], PATHINFO_FILENAME) . '.jpg', true, 86400);
    }
}
