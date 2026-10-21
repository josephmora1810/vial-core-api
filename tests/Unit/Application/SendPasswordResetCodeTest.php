<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\SendPasswordResetCode;
use App\Domain\Auth\Entities\Role;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Infrastructure\Database\Redis;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryMailer;
use Tests\Doubles\InMemoryUserRepository;

class SendPasswordResetCodeTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryMailer $mailer;
    private Redis $redis;
    private SendPasswordResetCode $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->mailer = new InMemoryMailer();
        $this->redis = new Redis();
        $this->useCase = new SendPasswordResetCode(
            $this->userRepository,
            $this->mailer,
            $this->redis
        );

        $this->user = new User(
            id: null,
            uuid: Uuid::generate(),
            names: new Names('MARÍA GABRIELA'),
            surnames: new Surnames('CHÁVEZ LÓPEZ'),
            email: new Email('maria@ubikgo.com'),
            password: Password::fromPlainText('ClaveSecreta123!'),
            phone: new Phone('+584120001122'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V18999888'),
            birthDate: new BirthDate('1992-03-10'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'client', 'api')],
            permissions: []
        );

        $this->userRepository->save($this->user);
    }

    protected function tearDown(): void
    {
        // Limpiar la clave de prueba en Redis
        $redisKey = 'password_reset:' . md5('maria@ubikgo.com');
        $this->redis->execute('DEL', $redisKey);
    }

    public function test_sends_email_with_otp_and_stores_in_redis_when_user_exists(): void
    {
        $this->useCase->execute('maria@ubikgo.com');

        // 1. Debe haber enviado 1 correo
        $this->assertSame(1, $this->mailer->getSentCount());
        $sentMail = $this->mailer->getLastSent();
        $this->assertSame('maria@ubikgo.com', $sentMail['to']);
        $this->assertStringContainsString('Código de Recuperación', $sentMail['subject']);

        // 2. Debe haber guardado el OTP en Redis
        $redisKey = 'password_reset:' . md5('maria@ubikgo.com');
        $storedOtp = $this->redis->execute('GET', $redisKey);
        $this->assertNotNull($storedOtp);
        $this->assertSame(6, strlen($storedOtp));

        // 3. El OTP debe estar en el cuerpo del correo
        $this->assertStringContainsString($storedOtp, $sentMail['htmlBody']);
    }

    public function test_does_not_send_email_and_does_not_fail_when_user_does_not_exist(): void
    {
        // Anti-enumeración de usuarios (OWASP)
        $this->useCase->execute('inexistente@ubikgo.com');

        $this->assertSame(0, $this->mailer->getSentCount());
    }

    public function test_fails_with_invalid_email_format(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El formato del correo electrónico no es válido.");

        $this->useCase->execute('correo_invalido');
    }
}
