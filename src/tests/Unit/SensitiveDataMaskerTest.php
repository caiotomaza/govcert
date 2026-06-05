<?php

namespace Tests\Unit;

use App\Services\SensitiveDataMasker;
use Tests\TestCase;

class SensitiveDataMaskerTest extends TestCase
{
    private SensitiveDataMasker $masker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masker = new SensitiveDataMasker;
    }

    public function test_mascara_cpf_formatado(): void
    {
        $result = $this->masker->mask('Meu CPF é 123.456.789-00 e preciso de ajuda.');
        $this->assertStringContainsString('[CPF OCULTO]', $result);
        $this->assertStringNotContainsString('123.456.789-00', $result);
    }

    public function test_mascara_cpf_sem_formatacao(): void
    {
        $result = $this->masker->mask('CPF: 12345678900');
        $this->assertStringContainsString('[CPF OCULTO]', $result);
        $this->assertStringNotContainsString('12345678900', $result);
    }

    public function test_mascara_cnpj_formatado(): void
    {
        $result = $this->masker->mask('Empresa com CNPJ 12.345.678/0001-90');
        $this->assertStringContainsString('[CNPJ OCULTO]', $result);
        $this->assertStringNotContainsString('12.345.678/0001-90', $result);
    }

    public function test_mascara_email(): void
    {
        $result = $this->masker->mask('Entre em contato por joao@orgao.gov.br');
        $this->assertStringContainsString('[E-MAIL OCULTO]', $result);
        $this->assertStringNotContainsString('joao@orgao.gov.br', $result);
    }

    public function test_mascara_telefone_brasileiro(): void
    {
        $result = $this->masker->mask('Ligue para (61) 98765-4321.');
        $this->assertStringContainsString('[TELEFONE OCULTO]', $result);
        $this->assertStringNotContainsString('98765-4321', $result);
    }

    public function test_mascara_senha_em_atribuicao(): void
    {
        $result = $this->masker->mask('password=MinhaSenh@123');
        $this->assertStringContainsString('[CREDENCIAL OCULTA]', $result);
        $this->assertStringNotContainsString('MinhaSenh@123', $result);
    }

    public function test_mascara_jwt(): void
    {
        $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $result = $this->masker->mask("Token: {$jwt}");
        $this->assertStringContainsString('[TOKEN OCULTO]', $result);
        $this->assertStringNotContainsString($jwt, $result);
    }

    public function test_mascara_bearer_token(): void
    {
        $result = $this->masker->mask('Authorization: Bearer abc123xyz456');
        $this->assertStringContainsString('[TOKEN OCULTO]', $result);
        $this->assertStringNotContainsString('abc123xyz456', $result);
    }

    public function test_mascara_chave_aws(): void
    {
        $result = $this->masker->mask('aws_key = AKIAIOSFODNN7EXAMPLE1');
        $this->assertStringContainsString('[CHAVE/API KEY OCULTA]', $result);
        $this->assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE1', $result);
    }

    public function test_preserva_texto_nao_sensivel(): void
    {
        $text = 'Qual a diferença entre debounce e throttle em JavaScript?';
        $result = $this->masker->mask($text);
        $this->assertSame($text, $result);
    }

    public function test_mascara_multiplos_dados_no_mesmo_texto(): void
    {
        $text = 'CPF 111.222.333-44, e-mail usuario@dominio.com, senha: SecretPass1!';
        $result = $this->masker->mask($text);
        $this->assertStringContainsString('[CPF OCULTO]', $result);
        $this->assertStringContainsString('[E-MAIL OCULTO]', $result);
        $this->assertStringContainsString('[CREDENCIAL OCULTA]', $result);
    }

    public function test_detect_retorna_categorias_corretas(): void
    {
        $found = $this->masker->detect('CPF 123.456.789-00 e email test@test.com');
        $this->assertContains('CPF', $found);
        $this->assertContains('E-mail', $found);
    }

    public function test_detect_retorna_array_vazio_para_texto_limpo(): void
    {
        $found = $this->masker->detect('Como criar um loop em Python?');
        $this->assertEmpty($found);
    }
}
