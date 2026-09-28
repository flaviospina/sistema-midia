<?php
// app/services/ArtWorkflow.php — transições de estado do pedido de arte, com permissões e histórico
declare(strict_types=1);

final class ArtWorkflow
{
    /** Ações disponíveis para o usuário logado num pedido. [acao => rótulo] */
    public static function actions(array $r): array
    {
        $out = [];
        $st = $r['status'];
        $requester = ArtRequest::isRequester($r);
        $manage = Auth::can('art.manage');
        $designer = Auth::can('art.produce') && ($r['designer_id'] === null || (int) $r['designer_id'] === Auth::id() || $manage);
        $hasVersion = (int) $r['versions_count'] > 0;

        if ($st === 'recebido' && Auth::can('art.produce')) {
            $out['assumir'] = 'Assumir produção';
        }
        if (in_array($st, ['em_producao', 'ajustes'], true) && $designer && $hasVersion) {
            $out['enviar_revisao'] = 'Enviar para revisão do solicitante';
        }
        if ($st === 'revisao_solicitante' && ($requester || $manage)) {
            $out['aprovar_solicitante'] = 'Aprovar (solicitante)';
            $out['pedir_ajustes'] = 'Pedir ajustes';
        }
        if ($st === 'aprovacao_midia' && Auth::can('art.approve_media')) {
            $out['aprovar_midia'] = 'Aprovar (mídia)';
            $out['pedir_ajustes'] = 'Pedir ajustes';
        }
        if ($st === 'aprovacao_pastoral' && Auth::can('art.approve_pastoral')) {
            $out['aprovar_pastoral'] = 'Aprovar (pastoral)';
            $out['pedir_ajustes'] = 'Pedir ajustes';
        }
        if ($st === 'aprovado' && Auth::can('publications.manage')) {
            $out['publicar'] = 'Marcar como publicado';
        }
        if ($st === 'ajustes' && $designer) {
            $out['retomar'] = 'Retomar produção';
        }
        if (!in_array($st, ['publicado', 'cancelado'], true) && ($manage || ($requester && in_array($st, ['recebido', 'em_producao', 'revisao_solicitante', 'ajustes'], true)))) {
            $out['cancelar'] = 'Cancelar pedido';
        }
        if ($st === 'cancelado' && $manage) {
            $out['reabrir'] = 'Reabrir';
        }
        return $out;
    }

    /**
     * Executa uma ação. Lança InvalidArgumentException com mensagem amigável quando não permitida.
     * @return string mensagem de sucesso
     */
    public static function apply(array $r, string $action, string $notes = ''): string
    {
        if (!isset(self::actions($r)[$action])) {
            throw new InvalidArgumentException('Esta ação não está disponível para o pedido no estado atual.');
        }
        $result = self::run($r, $action, $notes);
        $after = ArtRequest::find((int) $r['id']);
        if ($after && $after['status'] !== $r['status']) {
            Notifier::artStatus($after, $notes);
        }
        return $result;
    }

    private static function run(array $r, string $action, string $notes): string
    {
        $id = (int) $r['id'];
        $version = ArtRequest::currentVersion($id);
        $vid = $version ? (int) $version['id'] : null;
        $me = Auth::user();
        $notes = mb_substr(trim($notes), 0, 1000);

        switch ($action) {
            case 'assumir':
                ArtRequest::set($id, ['status' => 'em_producao', 'designer_id' => Auth::id()]);
                self::log($id, $me['name'] . ' assumiu a produção.');
                return 'Você assumiu este pedido.';

            case 'enviar_revisao':
                ArtRequest::set($id, ['status' => 'revisao_solicitante']);
                self::log($id, 'Versão ' . (int) $version['version_no'] . ' enviada para revisão do solicitante.' . ($notes ? ' ' . $notes : ''), $vid);
                return 'Enviado para revisão de ' . $r['requester_name'] . '.';

            case 'aprovar_solicitante':
                ArtRequest::approval($id, $vid, 'solicitante', 'aprovado', $notes ?: null);
                ArtRequest::set($id, ['status' => 'aprovacao_midia']);
                self::log($id, 'Solicitante aprovou a versão ' . (int) $version['version_no'] . '.' . ($notes ? ' ' . $notes : ''), $vid);
                return 'Aprovado. Agora a equipe de mídia confere o checklist de identidade visual.';

            case 'aprovar_midia':
                if (!ArtRequest::checklistComplete($id)) {
                    throw new InvalidArgumentException('Complete o checklist de identidade visual antes de aprovar.');
                }
                ArtRequest::approval($id, $vid, 'midia', 'aprovado', $notes ?: null);
                if ((int) $r['needs_pastoral'] === 1) {
                    ArtRequest::set($id, ['status' => 'aprovacao_pastoral']);
                    self::log($id, 'Mídia aprovou; aguardando aprovação pastoral.' . ($notes ? ' ' . $notes : ''), $vid);
                    return 'Aprovado pela mídia. Aguardando o pastor.';
                }
                return self::finalApprove($r, $vid, 'Mídia aprovou.' . ($notes ? ' ' . $notes : ''));

            case 'aprovar_pastoral':
                ArtRequest::approval($id, $vid, 'pastoral', 'aprovado', $notes ?: null);
                return self::finalApprove($r, $vid, 'Aprovação pastoral concedida.' . ($notes ? ' ' . $notes : ''));

            case 'pedir_ajustes':
                if ($notes === '') {
                    throw new InvalidArgumentException('Descreva os ajustes necessários.');
                }
                $stage = match ($r['status']) { 'aprovacao_midia' => 'midia', 'aprovacao_pastoral' => 'pastoral', default => 'solicitante' };
                ArtRequest::approval($id, $vid, $stage, 'ajustes', $notes);
                ArtRequest::set($id, ['status' => 'ajustes', 'ajustes_from' => $r['status']]);
                ArtRequest::comment($id, $notes, 'comentario', $vid);
                self::log($id, 'Ajustes solicitados (' . ArtRequest::STATUSES[$r['status']] . ').', $vid);
                return 'Ajustes solicitados. O designer será avisado.';

            case 'retomar':
                ArtRequest::set($id, ['status' => 'em_producao', 'designer_id' => $r['designer_id'] ?? Auth::id()]);
                self::log($id, 'Produção retomada.');
                return 'Produção retomada. Envie a nova versão quando pronta.';

            case 'publicar':
                ArtRequest::set($id, ['status' => 'publicado', 'published_at' => date('Y-m-d H:i:s')]);
                Database::run("UPDATE publications SET status = 'publicado', published_at = COALESCE(published_at, NOW()), published_by = COALESCE(published_by, :u) WHERE request_id = :r AND status = 'planejado'", ['u' => Auth::id(), 'r' => $id]);
                self::log($id, 'Marcado como publicado.' . ($notes ? ' ' . $notes : ''));
                return 'Pedido concluído e publicado.';

            case 'cancelar':
                if ($notes === '') {
                    throw new InvalidArgumentException('Informe o motivo do cancelamento.');
                }
                ArtRequest::set($id, ['status' => 'cancelado', 'cancelled_reason' => $notes]);
                Database::run("UPDATE publications SET status = 'cancelado' WHERE request_id = :r AND status = 'planejado'", ['r' => $id]);
                self::log($id, 'Pedido cancelado: ' . $notes);
                return 'Pedido cancelado.';

            case 'reabrir':
                $back = (int) $r['versions_count'] > 0 ? 'em_producao' : 'recebido';
                ArtRequest::set($id, ['status' => $back, 'cancelled_reason' => null]);
                self::log($id, 'Pedido reaberto.');
                return 'Pedido reaberto.';
        }
        throw new InvalidArgumentException('Ação desconhecida.');
    }

    private static function finalApprove(array $r, ?int $vid, string $logText): string
    {
        $id = (int) $r['id'];
        ArtRequest::set($id, ['status' => 'aprovado', 'approved_at' => date('Y-m-d H:i:s')]);
        $version = $vid ? Database::one('SELECT file_id FROM art_request_versions WHERE id = :id', ['id' => $vid]) : null;
        $n = Publication::createForRequest(ArtRequest::find($id), $version ? (int) $version['file_id'] : null);
        self::log($id, $logText . ($n ? " {$n} publicação(ões) agendada(s) para " . format_date($r['publish_on']) . '.' : ''), $vid);
        return 'Arte aprovada! ' . ($n ? "{$n} publicação(ões) entraram no calendário de comunicação." : '');
    }

    public static function log(int $id, string $text, ?int $vid = null): void
    {
        ArtRequest::comment($id, $text, 'historico', $vid);
        Logger::audit('arte_historico', 'art_requests', $id, null, ['texto' => $text]);
    }

    /** Quem pode enviar arquivos para o pedido (anexos: solicitante; versões: designer). */
    public static function canUpload(array $r, string $kind): bool
    {
        if (in_array($r['status'], ['publicado', 'cancelado'], true)) {
            return false;
        }
        if ($kind === 'versao') {
            return Auth::can('art.produce') && ($r['designer_id'] === null || (int) $r['designer_id'] === Auth::id() || Auth::can('art.manage'))
                && in_array($r['status'], ['recebido', 'em_producao', 'ajustes', 'revisao_solicitante'], true);
        }
        return ArtRequest::isRequester($r) || Auth::can('art.produce');
    }
}
