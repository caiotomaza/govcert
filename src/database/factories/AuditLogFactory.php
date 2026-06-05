<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Services\TokenCounter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    // ── Cenários realistas com dados sensíveis fictícios ──────────────────────

    public static function scenarios(): array
    {
        return [
            // 1 — CPF em análise de cadastro
            [
                'input_text' => 'Preciso analisar o cadastro do cidadão João Silva, CPF 123.456.789-00, para verificar pendências no sistema tributário.',
                'output_text' => 'Analisando o cadastro: o CPF 123.456.789-00 possui duas pendências de IPTU em aberto referentes aos exercícios 2022 e 2023. Recomendo notificação formal ao contribuinte.',
                'url_source' => 'https://chatgpt.com/c/cpf-analise',
                'leak_type' => 'Dados Pessoais',
                'risk_level' => 'high',
                'has_sensitive' => true,
                'justification' => 'CPF do contribuinte exposto em ferramenta de IA pública. Dados de identificação pessoal (art. 5º, II da LGPD) exigem tratamento baseado em finalidade legítima e proporcionalidade.',
            ],

            // 2 — CNPJ em análise de contrato
            [
                'input_text' => 'Analise o contrato da empresa Exemplo Tecnologia LTDA, CNPJ 12.345.678/0001-90, para verificar conformidade com a Lei 14.133/2021.',
                'output_text' => 'O contrato da empresa CNPJ 12.345.678/0001-90 apresenta cláusulas de prazo adequadas à nova legislação. Há, porém, ausência de cláusula de garantia prevista no art. 96. Recomendo ajuste antes da assinatura.',
                'url_source' => 'https://claude.ai/chat/contrato-licitacao',
                'leak_type' => 'Dados Pessoais',
                'risk_level' => 'medium',
                'has_sensitive' => true,
                'justification' => 'CNPJ de empresa licitante exposto em ferramenta pública de IA. Mesmo sendo dado de pessoa jurídica, o contexto de processo licitatório exige sigilo.',
            ],

            // 3 — E-mail de servidor público
            [
                'input_text' => 'O servidor informou o e-mail maria.silva@orgao.gov.br para contato urgente sobre o processo nº 0012/2024.',
                'output_text' => 'Registrado. O servidor cujo e-mail é maria.silva@orgao.gov.br será notificado sobre o processo 0012/2024 via ofício eletrônico conforme determina a IN 09/2023.',
                'url_source' => 'https://gemini.google.com/app/notificacao-servidor',
                'leak_type' => 'Dados Pessoais',
                'risk_level' => 'medium',
                'has_sensitive' => true,
                'justification' => 'E-mail funcional de servidor vinculado a processo administrativo exposto em IA pública. Dado pessoal de agente público em contexto de identificação direta.',
            ],

            // 4 — Telefone de cidadão
            [
                'input_text' => 'Entre em contato com o cidadão Carlos Mendes pelo telefone (85) 98888-7777 para confirmar agendamento de perícia médica.',
                'output_text' => 'Agendamento confirmado. O cidadão deve ser contatado no número (85) 98888-7777 até sexta-feira. Caso não haja resposta, enviar SMS pelo sistema.',
                'url_source' => 'https://chatgpt.com/c/agendamento-pericia',
                'leak_type' => 'Dados Pessoais',
                'risk_level' => 'high',
                'has_sensitive' => true,
                'justification' => 'Telefone pessoal de cidadão vinculado a dado de saúde (perícia médica) exposto em ferramenta de IA pública. Combinação de dados que permite identificação indireta e viola arts. 6º e 11 da LGPD.',
            ],

            // 5 — Senha em texto claro
            [
                'input_text' => 'A senha temporária do sistema SIGEPE para o novo servidor é: senha: Gov@123456. Pode ajudar a redefinir?',
                'output_text' => "Entendido. Para redefinir a senha Gov@123456, acesse o portal do SIGEPE e utilize a opção 'Primeiro Acesso'. Recomendo fortemente trocar essa senha imediatamente após o primeiro login.",
                'url_source' => 'https://claude.ai/chat/senha-sigepe',
                'leak_type' => 'Credenciais',
                'risk_level' => 'critical',
                'has_sensitive' => true,
                'justification' => 'Senha temporária de sistema governamental (SIGEPE) exposta em ferramenta de IA pública. Credencial de acesso a dados funcionais do servidor — risco crítico de comprometimento de conta.',
            ],

            // 6 — Token Bearer
            [
                'input_text' => 'Use o token Authorization: Bearer abcdef1234567890secrettoken1234 para testar a API do sistema de pagamentos do TCU.',
                'output_text' => "Para testar com esse Bearer token, use: curl -H 'Authorization: Bearer abcdef1234567890secrettoken1234' https://api.tcu.gov.br/v2/pagamentos. Atenção: tokens de produção não devem ser compartilhados.",
                'url_source' => 'https://gemini.google.com/app/api-tcu-test',
                'leak_type' => 'Credenciais',
                'risk_level' => 'critical',
                'has_sensitive' => true,
                'justification' => 'Token Bearer de API governamental (TCU) exposto em ferramenta pública de IA. Credencial que concede acesso direto a endpoint de pagamentos — risco crítico imediato.',
            ],

            // 7 — JWT em URL
            [
                'input_text' => 'Meu JWT é eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMifQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c e não consigo autenticar.',
                'output_text' => 'O JWT parece válido. O payload decodificado mostra sub: 123. Verifique se o token não expirou (campo exp) e se a assinatura corresponde à secret configurada no servidor. Nunca compartilhe JWTs em canais não seguros.',
                'url_source' => 'https://chatgpt.com/c/jwt-debug',
                'leak_type' => 'Credenciais',
                'risk_level' => 'high',
                'has_sensitive' => true,
                'justification' => 'Token JWT de autenticação exposto em ferramenta de IA pública. Token pode estar ativo e permitir acesso não autorizado ao sistema.',
            ],

            // 8 — API Key em código
            [
                'input_text' => 'A chave da API do sistema de consulta de dados é sk-test-123456789abcdef. Como integrar com Python?',
                'output_text' => "Para integrar com Python: import requests; headers = {'Authorization': 'Bearer sk-test-123456789abcdef'}; response = requests.get('https://api.sistema.gov.br/dados', headers=headers). Importante: mova a chave para variável de ambiente.",
                'url_source' => 'https://claude.ai/chat/api-integration',
                'leak_type' => 'Credenciais',
                'risk_level' => 'critical',
                'has_sensitive' => true,
                'justification' => 'Chave de API de sistema governamental exposta em ferramenta de IA pública. Credencial que permite acesso direto a dados sensíveis do governo.',
            ],

            // 9 — Código-fonte com credenciais
            [
                'input_text' => "Revise este trecho de configuração:\nDB_PASSWORD=GovSec@2024Root\nAPI_SECRET=abc123govbrasil\nJWT_SECRET=segredo-jwt-producao\npublic function login() {\n  return Auth::attempt(\$credentials);\n}",
                'output_text' => 'Identificados problemas críticos: DB_PASSWORD, API_SECRET e JWT_SECRET estão hardcoded. Mova todas para .env e nunca versione esse arquivo. Adicione .env ao .gitignore imediatamente.',
                'url_source' => 'https://gemini.google.com/app/code-review-creds',
                'leak_type' => 'Código Fonte',
                'risk_level' => 'critical',
                'has_sensitive' => true,
                'justification' => 'Código-fonte com credenciais em texto claro (senha de banco, segredo de JWT e API) enviado para IA pública. Múltiplas credenciais de produção comprometidas simultaneamente.',
            ],

            // 10 — Dados sigilosos de processo administrativo
            [
                'input_text' => 'Resumo de processo administrativo sigiloso: servidor de matrícula 20231130033 investigado por irregularidades nos contratos nos 003/2023 e 047/2023. CPF do investigado: 555.444.333-22.',
                'output_text' => 'Com base nos dados fornecidos, o processo envolve dois contratos. Recomendo verificar os procedimentos do TCU e CGU para esse tipo de investigação. O servidor deve ser notificado conforme art. 143 da Lei 8.112/90.',
                'url_source' => 'https://chatgpt.com/c/processo-sigiloso',
                'leak_type' => 'Dados Pessoais',
                'risk_level' => 'critical',
                'has_sensitive' => true,
                'justification' => 'Dados de processo administrativo sigiloso com matrícula, CPF e número de contratos enviados a IA pública. Violação grave de sigilo funcional e dados pessoais do investigado.',
            ],

            // 11 — Sem dado sensível (técnico)
            [
                'input_text' => 'Explique o conceito de governança de TI usando o framework ITIL 4 de forma resumida para gestores públicos.',
                'output_text' => 'ITIL 4 organiza a gestão de TI em quatro dimensões: pessoas e organizações, informação e tecnologia, parceiros e fornecedores, e fluxos de valor. Para o setor público, o principal benefício é a padronização de processos e maior eficiência no uso de recursos.',
                'url_source' => 'https://gemini.google.com/app/itil-governanca',
                'leak_type' => 'Nenhum',
                'risk_level' => 'low',
                'has_sensitive' => false,
                'justification' => 'Consulta técnica sobre framework de governança de TI. Nenhum dado pessoal, credencial ou informação sigilosa identificada. Uso adequado e eficiente da ferramenta de IA.',
            ],

            // 12 — Uso comum sem dados sensíveis
            [
                'input_text' => 'Crie um resumo sobre boas práticas de segurança da informação em órgãos públicos para apresentação em treinamento.',
                'output_text' => 'Boas práticas de segurança da informação no setor público: 1) Autenticação multifator para todos os sistemas críticos; 2) Política de senhas forte (mínimo 12 caracteres); 3) Backups regulares; 4) Treinamento contínuo de servidores; 5) Conformidade com a LGPD.',
                'url_source' => 'https://claude.ai/chat/seguranca-treinamento',
                'leak_type' => 'Nenhum',
                'risk_level' => 'low',
                'has_sensitive' => false,
                'justification' => 'Solicitação de conteúdo educacional sobre segurança da informação. Sem dados pessoais ou informações sigilosas. Uso positivo e exemplar da ferramenta de IA generativa.',
            ],

            // 13 — Credenciais AWS em código
            [
                'input_text' => "Pode revisar meu script de deploy? Não está subindo o relatório pro S3:\n```python\nAWS_ACCESS_KEY_ID = 'AKIA5T9QZ8XK2J4LMNOP'\nAWS_SECRET_ACCESS_KEY = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'\ns3 = boto3.client('s3', ...)\n```",
                'output_text' => "Encontrei o problema: faltou definir a região do bucket. Adicione `region_name='sa-east-1'`. Importante: nunca deixe as chaves AWS fixas no código — mova para variáveis de ambiente ou use IAM Roles.",
                'url_source' => 'https://chatgpt.com/c/deploy-s3',
                'leak_type' => 'Credenciais',
                'risk_level' => 'critical',
                'has_sensitive' => true,
                'justification' => 'Access Key ID e Secret Access Key da AWS expostos em ferramenta de IA pública. Credenciais dão acesso a bucket de produção governamental — rotação imediata necessária.',
            ],

            // 14 — Prontuário médico
            [
                'input_text' => 'Resume este prontuário: Paciente Maria Aparecida de Souza, CPF 287.654.123-09, e-mail paciente@sus.gov.br. Diagnóstico: Diabetes tipo 2, HbA1c 9,4%. Em uso de metformina 850mg.',
                'output_text' => 'A paciente tem diabetes fora de controle. O exame indica glicose elevada nos últimos 3 meses. O tratamento envolve medicação oral. É importante manter acompanhamento regular com endocrinologista.',
                'url_source' => 'https://claude.ai/chat/prontuario',
                'leak_type' => 'Dados Pessoais',
                'risk_level' => 'critical',
                'has_sensitive' => true,
                'justification' => 'Prontuário com CPF e dados de saúde enviado a IA pública. Dados de saúde são categoria especial (art. 11 da LGPD) — tratamento irregular.',
            ],
        ];
    }

    // ── Definition padrão ─────────────────────────────────────────────────────

    public function definition(): array
    {
        $scenario = Arr::random(self::scenarios());
        $counter = new TokenCounter;
        $tokens = $counter->countPair($scenario['input_text'], $scenario['output_text']);

        return [
            'user_id' => null,
            'user_identifier' => fake()->userName(),
            'input_text' => $scenario['input_text'],
            'output_text' => $scenario['output_text'],
            'input_tokens' => $tokens['input'],
            'output_tokens' => $tokens['output'],
            'total_tokens' => $tokens['total'],
            'token_count_method' => 'estimated',
            'url_source' => $scenario['url_source'],
            'captured_at' => now(),
            'status' => 'pending',
            'has_sensitive_data' => null,
            'risk_level' => null,
            'leak_type' => null,
            'gemini_justification' => null,
            'gemini_raw_response' => null,
            'processed_at' => null,
        ];
    }

    // ── Estados auxiliares ────────────────────────────────────────────────────

    public function completed(string $risk = 'high', bool $sensitive = true): static
    {
        $leak = $sensitive
            ? match ($risk) {
                'critical' => 'Credenciais',
                'high' => 'Dados Pessoais',
                'medium' => 'Código Fonte',
                default => 'Dados Pessoais',
            }
        : 'Nenhum';

        return $this->state(fn () => [
            'status' => 'completed',
            'has_sensitive_data' => $sensitive,
            'risk_level' => $risk,
            'leak_type' => $leak,
            'gemini_justification' => 'Parecer técnico do auditor para o cenário de risco '.$risk.'.',
            'gemini_raw_response' => ['risk_level' => $risk, 'has_sensitive_data' => $sensitive, 'leak_type' => $leak],
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_reason' => 'Falha simulada no processamento.',
        ]);
    }

    public function withTokens(int $input, int $output): static
    {
        return $this->state(fn () => [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $input + $output,
            'token_count_method' => 'estimated',
        ]);
    }

    // ── Perfis de consumo para seeds ─────────────────────────────────────────

    /** 100–800 tokens por log. */
    public function lowConsumption(): static
    {
        return $this->state(function () {
            $input = fake()->numberBetween(30, 400);
            $output = fake()->numberBetween(70, 400);

            return [
                'input_tokens' => $input,
                'output_tokens' => $output,
                'total_tokens' => $input + $output,
                'token_count_method' => 'estimated',
            ];
        });
    }

    /** 800–3000 tokens por log. */
    public function mediumConsumption(): static
    {
        return $this->state(function () {
            $input = fake()->numberBetween(200, 1500);
            $output = fake()->numberBetween(600, 1500);

            return [
                'input_tokens' => $input,
                'output_tokens' => $output,
                'total_tokens' => $input + $output,
                'token_count_method' => 'estimated',
            ];
        });
    }

    /** 3000–8000 tokens por log. */
    public function highConsumption(): static
    {
        return $this->state(function () {
            $input = fake()->numberBetween(1000, 4000);
            $output = fake()->numberBetween(2000, 4000);

            return [
                'input_tokens' => $input,
                'output_tokens' => $output,
                'total_tokens' => $input + $output,
                'token_count_method' => 'estimated',
            ];
        });
    }

    /** 8000–20000 tokens por log. */
    public function criticalConsumption(): static
    {
        return $this->state(function () {
            $input = fake()->numberBetween(3000, 10000);
            $output = fake()->numberBetween(5000, 10000);

            return [
                'input_tokens' => $input,
                'output_tokens' => $output,
                'total_tokens' => $input + $output,
                'token_count_method' => fake()->randomElement(['estimated', 'api']),
            ];
        });
    }

    // ── Estado composto realista para seed principal ──────────────────────────

    /**
     * Sorteia cenário, aplica tokens coerentes e distribui datas nos últimos 60 dias.
     * Usado pelo seeder principal.
     *
     * @param  string  $consumption  low|medium|high|critical
     * @param  int  $daysBack  janela de distribuição das datas
     */
    public function scenario(string $consumption = 'medium', int $daysBack = 60): static
    {
        return $this->state(function () use ($consumption, $daysBack) {
            $s = Arr::random(self::scenarios());

            $createdAt = now()
                ->subDays(fake()->numberBetween(0, $daysBack))
                ->subMinutes(fake()->numberBetween(0, 1439));

            [$inputTokens, $outputTokens] = match ($consumption) {
                'low' => [fake()->numberBetween(30, 400),  fake()->numberBetween(70, 400)],
                'medium' => [fake()->numberBetween(200, 1500), fake()->numberBetween(600, 1500)],
                'high' => [fake()->numberBetween(1000, 4000), fake()->numberBetween(2000, 4000)],
                'critical' => [fake()->numberBetween(3000, 10000), fake()->numberBetween(5000, 10000)],
                default => [fake()->numberBetween(200, 1500), fake()->numberBetween(600, 1500)],
            };

            return [
                'input_text' => $s['input_text'],
                'output_text' => $s['output_text'],
                'url_source' => $s['url_source'],
                'leak_type' => $s['leak_type'],
                'risk_level' => $s['risk_level'],
                'has_sensitive_data' => $s['has_sensitive'],
                'status' => 'completed',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
                'token_count_method' => fake()->randomElement(['estimated', 'api']),
                'gemini_justification' => $s['justification'],
                'gemini_raw_response' => [
                    'has_sensitive_data' => $s['has_sensitive'],
                    'risk_level' => $s['risk_level'],
                    'leak_type' => $s['leak_type'],
                    'justification' => $s['justification'],
                ],
                'captured_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'processed_at' => (clone $createdAt)->addMinutes(fake()->numberBetween(1, 25)),
            ];
        });
    }
}
