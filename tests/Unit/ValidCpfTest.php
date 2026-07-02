<?php

namespace Tests\Unit;

use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidCpfTest extends TestCase
{
    private function passes(string $cpf): bool
    {
        return Validator::make(['cpf' => $cpf], ['cpf' => [new ValidCpf]])->passes();
    }

    public function test_accepts_valid_cpf_with_and_without_mask(): void
    {
        $this->assertTrue($this->passes('529.982.247-25'));
        $this->assertTrue($this->passes('52998224725'));
    }

    public function test_rejects_invalid_check_digits(): void
    {
        $this->assertFalse($this->passes('529.982.247-24'));
    }

    public function test_rejects_repeated_sequences(): void
    {
        $this->assertFalse($this->passes('111.111.111-11'));
    }

    public function test_rejects_wrong_length(): void
    {
        $this->assertFalse($this->passes('1234'));
    }
}
