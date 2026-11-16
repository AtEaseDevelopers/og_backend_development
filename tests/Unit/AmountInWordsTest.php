<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_the_sen_are_spelled_and_the_ringgit_are_not_rounded(): void
    {
        $this->assertSame('RINGGIT MALAYSIA : SEVEN HUNDRED FIFTY FOUR RINGGIT AND SEVENTY TWO SEN', AmountInWords::ringgit(754.72));
        $this->assertSame('RINGGIT MALAYSIA : ONE THOUSAND FIVE HUNDRED RINGGIT', AmountInWords::ringgit(1500.00));
        $this->assertSame('RINGGIT MALAYSIA : ZERO RINGGIT AND FIVE SEN', AmountInWords::ringgit(0.05));
        $this->assertSame('RINGGIT MALAYSIA : TWENTY RINGGIT', AmountInWords::ringgit(19.999));
    }
}
