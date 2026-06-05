<?php

namespace App\Services;

class SensitiveDataMasker
{
    /**
     * Cada entrada define um padrão regex e o marcador substituto.
     * A ordem importa: padrões mais específicos devem vir primeiro.
     *
     * @var array<int, array{pattern: string, replacement: string, flags: string}>
     */
    private array $rules = [
        // JWT (eyJ...) — antes de Bearer para não colidir
        [
            'pattern' => 'eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+',
            'replacement' => '[TOKEN OCULTO]',
            'flags' => '',
        ],
        // Bearer token
        [
            'pattern' => 'Bearer\s+[A-Za-z0-9\-._~+\/]+=*',
            'replacement' => 'Bearer [TOKEN OCULTO]',
            'flags' => 'i',
        ],
        // AWS Access Key ID
        [
            'pattern' => 'AKIA[0-9A-Z]{16}',
            'replacement' => '[CHAVE/API KEY OCULTA]',
            'flags' => '',
        ],
        // Chaves de API em atribuições: api_key = "valor" | api_key: valor
        [
            'pattern' => '(?:api[_\-]?key|api[_\-]?secret|access[_\-]?key|secret[_\-]?key|private[_\-]?key|client[_\-]?secret)\s*[=:]\s*["\']?[A-Za-z0-9\-._+\/]{8,}["\']?',
            'replacement' => '[CHAVE/API KEY OCULTA]',
            'flags' => 'i',
        ],
        // Senhas em atribuições: password=xxx | senha: xxx | passwd="xxx"
        [
            'pattern' => '(?:senha|password|passwd|pwd)\s*[=:]\s*["\']?\S+["\']?',
            'replacement' => '[CREDENCIAL OCULTA]',
            'flags' => 'i',
        ],
        // URLs com parâmetros sensíveis: ?token=xxx&key=yyy
        [
            'pattern' => '(?<=[\?&])(?:token|key|password|secret|api_key|apikey|access_token)[^&\s#"\']*',
            'replacement' => '[DADO SENSÍVEL OCULTO]',
            'flags' => 'i',
        ],
        // CNPJ (deve vir antes do CPF para evitar match parcial)
        [
            'pattern' => '\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b',
            'replacement' => '[CNPJ OCULTO]',
            'flags' => '',
        ],
        // CPF
        [
            'pattern' => '\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b',
            'replacement' => '[CPF OCULTO]',
            'flags' => '',
        ],
        // Cartão de crédito (13-16 dígitos com separadores opcionais)
        [
            'pattern' => '\b(?:\d[ \-]?){13,16}\b',
            'replacement' => '[DADO SENSÍVEL OCULTO]',
            'flags' => '',
        ],
        // E-mail
        [
            'pattern' => '[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}',
            'replacement' => '[E-MAIL OCULTO]',
            'flags' => '',
        ],
        // Telefone brasileiro: (XX) XXXXX-XXXX | +55 XX XXXXX-XXXX | variações
        [
            'pattern' => '(?:\+55\s?)?(?:\(\d{2}\)|\d{2})[\s\-]?\d{4,5}[\s\-]?\d{4}\b',
            'replacement' => '[TELEFONE OCULTO]',
            'flags' => '',
        ],
    ];

    /**
     * Mascara dados sensíveis no texto.
     *
     * @param  array<string, mixed>|null  $aiFindings  Dados estruturados do Gemini (gemini_raw_response)
     */
    public function mask(string $text, ?array $aiFindings = null): string
    {
        $masked = $text;

        foreach ($this->rules as $rule) {
            $regex = '/'.$rule['pattern'].'/'.$rule['flags'].'u';
            $masked = preg_replace($regex, $rule['replacement'], $masked) ?? $masked;
        }

        return $masked;
    }

    /**
     * Detecta quais categorias de dados sensíveis estão presentes no texto.
     *
     * @return array<string>
     */
    public function detect(string $text): array
    {
        $found = [];

        $checks = [
            'CPF' => '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/u',
            'CNPJ' => '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/u',
            'E-mail' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
            'Telefone' => '/(?:\+55\s?)?(?:\(\d{2}\)|\d{2})[\s\-]?\d{4,5}[\s\-]?\d{4}\b/u',
            'Senha/Credencial' => '/(?:senha|password|passwd|pwd)\s*[=:]\s*\S+/iu',
            'Token/JWT' => '/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/u',
            'Bearer Token' => '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/iu',
            'Chave de API' => '/(?:api[_\-]?key|api[_\-]?secret|access[_\-]?key|secret[_\-]?key)\s*[=:]\s*\S+/iu',
            'AWS Key' => '/AKIA[0-9A-Z]{16}/u',
        ];

        foreach ($checks as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                $found[] = $label;
            }
        }

        return $found;
    }
}
