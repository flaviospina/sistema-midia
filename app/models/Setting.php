<?php
// app/models/Setting.php — configurações do administrador (chave/valor), com cache por requisição
declare(strict_types=1);

final class Setting
{
    public const DEFAULTS = [
        'watermark_enabled'  => '0',
        'watermark_position' => 'superior-direito',
        'watermark_width'    => '18',   // % da largura da foto
        'watermark_opacity'  => '85',   // %
        'watermark_margin'   => '2',    // % da largura
        'google_api_key'     => '',
    ];

    public const POSITIONS = ['superior-direito' => 'Superior direito', 'superior-esquerdo' => 'Superior esquerdo', 'inferior-direito' => 'Inferior direito', 'inferior-esquerdo' => 'Inferior esquerdo'];

    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (Database::all('SELECT setting_key, value FROM em_settings') as $r) {
                    self::$cache[$r['setting_key']] = (string) $r['value'];
                }
            } catch (Throwable) {
                // tabela ainda não criada: usa os padrões
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = self::load()[$key] ?? '';
        return $v === '' ? $default : $v;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run('INSERT INTO em_settings (setting_key, value, updated_by) VALUES (:k, :v, :u) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)', ['k' => $key, 'v' => $value, 'u' => Auth::id()]);
        self::$cache = null;
    }
}
