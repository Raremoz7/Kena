<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    public function test_formula_prefixes_become_text(): void
    {
        foreach (['=1+1', '+55 61', '-2', '@SUM(A1)', "\tx", "\rx", "\nx"] as $value) {
            $this->assertSame("'".$value, Csv::cell($value));
        }
    }

    public function test_plain_values_are_untouched(): void
    {
        $this->assertSame('Maria Silva', Csv::cell('Maria Silva'));
        $this->assertSame('', Csv::cell(null));
        $this->assertSame('3', Csv::cell(3));
        $this->assertSame('a=b', Csv::cell('a=b'));
    }
}
