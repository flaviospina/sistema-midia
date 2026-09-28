<?php
// app/Validator.php — validação simples com mensagens em pt-BR
declare(strict_types=1);

final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public function value(string $field): string
    {
        $v = $this->data[$field] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->add($field, "Informe {$label}.");
        }
        return $this;
    }

    public function email(string $field): self
    {
        $v = $this->value($field);
        if ($v !== '' && (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 191)) {
            $this->add($field, 'E-mail inválido.');
        }
        return $this;
    }

    public function max(string $field, int $max, string $label): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->add($field, "{$label} deve ter no máximo {$max} caracteres.");
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !in_array($v, $allowed, true)) {
            $this->add($field, "{$label}: opção inválida.");
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v === '') {
            return $this;
        }
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            $this->add($field, "{$label}: data inválida.");
        }
        return $this;
    }

    public function whatsapp(string $field): self
    {
        $v = $this->value($field);
        if ($v !== '' && self::normalizePhone($v) === null) {
            $this->add($field, 'WhatsApp inválido. Use DDD + número, ex.: (11) 98888-7777.');
        }
        return $this;
    }

    public function password(string $field, string $confirmField, ?string $email = null): self
    {
        $v = $this->data[$field] ?? '';
        $v = is_string($v) ? $v : '';
        if (mb_strlen($v) < 10) {
            $this->add($field, 'A senha deve ter pelo menos 10 caracteres.');
        } elseif (mb_strlen($v) > 200) {
            $this->add($field, 'Senha longa demais.');
        } elseif ($email && mb_strtolower($v) === mb_strtolower($email)) {
            $this->add($field, 'A senha não pode ser igual ao e-mail.');
        } elseif (!preg_match('/\pL/u', $v) || !preg_match('/\d/', $v)) {
            $this->add($field, 'Use letras e números na senha.');
        }
        if (($this->data[$confirmField] ?? null) !== $v) {
            $this->add($confirmField, 'A confirmação não confere com a senha.');
        }
        return $this;
    }

    public function add(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Normaliza telefone brasileiro para dígitos com DDI 55 (ex.: 5511988887777).
     * Retorna null se inválido.
     */
    public static function normalizePhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55' . $digits;
        }
        if (!preg_match('/^55[1-9]{2}\d{8,9}$/', $digits)) {
            return null;
        }
        return $digits;
    }
}
