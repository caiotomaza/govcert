<?php

namespace Tests\Unit;

use App\Services\TokenCounter;
use Tests\TestCase;

class TokenCounterTest extends TestCase
{
    private TokenCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->counter = new TokenCounter;
    }

    public function test_estimate_retorna_inteiro_positivo_para_texto_simples(): void
    {
        $tokens = $this->counter->estimate('Hello world');
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function test_estimate_texto_vazio_retorna_zero(): void
    {
        $this->assertSame(0, $this->counter->estimate(''));
    }

    public function test_estimate_usa_aproximacao_de_4_chars_por_token(): void
    {
        // 8 chars → ceil(8/4) = 2 tokens
        $this->assertSame(2, $this->counter->estimate('12345678'));
        // 9 chars → ceil(9/4) = 3 tokens
        $this->assertSame(3, $this->counter->estimate('123456789'));
    }

    public function test_count_pair_retorna_input_output_e_total(): void
    {
        $result = $this->counter->countPair('abcd', 'abcdefgh');
        $this->assertSame(1, $result['input']);
        $this->assertSame(2, $result['output']);
        $this->assertSame(3, $result['total']);
        $this->assertSame('estimated', $result['method']);
    }

    public function test_total_e_soma_de_input_e_output(): void
    {
        $result = $this->counter->countPair('texto de entrada', 'texto de saída mais longo aqui');
        $this->assertSame($result['input'] + $result['output'], $result['total']);
    }

    public function test_from_gemini_metadata_usa_valores_da_api_quando_disponiveis(): void
    {
        $metadata = ['promptTokenCount' => 150, 'candidatesTokenCount' => 300, 'totalTokenCount' => 500];
        $result = $this->counter->fromGeminiMetadata($metadata, 'qualquer', 'texto');
        $this->assertSame(150, $result['input']);
        $this->assertSame(300, $result['output']);
        $this->assertSame(500, $result['total']);
        $this->assertSame('api', $result['method']);
    }

    public function test_from_gemini_metadata_faz_fallback_quando_metadata_e_null(): void
    {
        $input = 'texto';
        $output = 'resposta';
        $result = $this->counter->fromGeminiMetadata(null, $input, $output);
        $this->assertSame('estimated', $result['method']);
        $this->assertSame($this->counter->estimate($input), $result['input']);
    }

    public function test_from_gemini_metadata_faz_fallback_quando_campos_ausentes(): void
    {
        $result = $this->counter->fromGeminiMetadata(['some' => 'data'], 'i', 'o');
        $this->assertSame('estimated', $result['method']);
    }
}
