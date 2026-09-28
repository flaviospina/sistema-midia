<?php
// app/models/Setting.php — configurações editáveis pela tela (tabela settings) e preferências por usuário
declare(strict_types=1);

final class Setting
{
    /** Eventos de notificação: chave => [rótulo, ligado por padrão]. */
    public const EVENTS = [
        'escala.escalado'         => ['Escalado(a) em um evento (para a pessoa)', true],
        'escala.lembrete'         => ['Lembrete no dia anterior ao evento (para os escalados)', true],
        'escala.recusada'         => ['Recusa de escala (para o coordenador)', true],
        'escala.troca'            => ['Pedidos e aprovações de troca (para colega e coordenador)', true],
        'evento.cancelado'        => ['Evento cancelado (para os escalados)', true],
        'arquivo.quarentena'      => ['Novos arquivos na quarentena (para coordenadores, agrupado)', true],
        'arte.status'             => ['Mudança de status de pedido de arte (solicitante, designer, pastor, coordenação)', true],
        'comunicacao.hoje'        => ['Publicações agendadas para hoje (para o responsável)', true],
        'ocorrencia.alta'         => ['Ocorrência de gravidade alta (para coordenadores e admin)', true],
        'capacitacao.apto'        => ['Trilha concluída: pessoa promovida a apto (para a pessoa)', true],
    ];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT setting_key, value FROM settings') as $r) {
                self::$cache[$r['setting_key']] = $r['value'];
            }
        }
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO settings (setting_key, value, updated_by) VALUES (:k, :v, :u) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
            ['k' => $key, 'v' => $value, 'u' => Auth::id()]
        );
        self::$cache = null;
    }

    public static function eventEnabled(string $event): bool
    {
        $default = self::EVENTS[$event][1] ?? true;
        return self::get('notify.' . $event, $default ? '1' : '0') === '1';
    }

    // ---- Preferências por usuário --------------------------------------

    public static function userPref(int $userId, string $key, ?string $default = null): ?string
    {
        $v = Database::value('SELECT value FROM user_preferences WHERE user_id = :u AND pref_key = :k', ['u' => $userId, 'k' => $key]);
        return $v === null ? $default : (string) $v;
    }

    public static function setUserPref(int $userId, string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO user_preferences (user_id, pref_key, value) VALUES (:u, :k, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            ['u' => $userId, 'k' => $key, 'v' => $value]
        );
    }

    /** IDs de quem desligou o WhatsApp em "Meus dados". */
    public static function whatsappOptOutIds(): array
    {
        return array_map('intval', array_column(Database::all("SELECT user_id FROM user_preferences WHERE pref_key = 'whatsapp' AND value = '0'"), 'user_id'));
    }
}
