<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Niv;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NivTest extends TestCase
{
    public function test_can_create_valid_17_character_niv(): void
    {
        $niv = new Niv('1HGCR2F83HA123456');

        $this->assertSame('1HGCR2F83HA123456', $niv->getValue());
        $this->assertSame('1HG', $niv->getWmi());
        $this->assertSame('CR2F83', $niv->getVds());
        $this->assertSame('HA123456', $niv->getVis());
    }

    public function test_normalizes_spaces_and_hyphens(): void
    {
        $niv = new Niv('1HG-CR2F83-HA123456');

        $this->assertSame('1HGCR2F83HA123456', $niv->getValue());
    }

    public function test_throws_exception_if_length_is_not_17(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El N.I.V. "1HGCR2F8" no es válido. Debe tener exactamente 17 caracteres');

        new Niv('1HGCR2F8');
    }
}
