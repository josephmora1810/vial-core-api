<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\RatingResponseDto;
use App\Domain\Shared\Services\RedisClientInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\{
    Entities\Rating,
    Enums\TowServiceStatus,
    Repositories\RatingRepositoryInterface,
    Repositories\TowServiceRepositoryInterface
};
use DomainException;
use Exception;
use InvalidArgumentException;
use Throwable;

class RateTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private RatingRepositoryInterface $ratingRepository,
        private ?RedisClientInterface $redis = null
    ) {}

    /**
     * Califica un servicio culminado con 1 a 5 estrellas y un comentario opcional (FR-27).
     *
     * @param string|int $serviceIdentifier ID o UUID del servicio
     * @param int $userId ID del usuario que califica (cliente o chofer)
     * @param int $stars Calificación numérica entre 1 y 5
     * @param string|null $comment Comentario u opinión opcional
     * @return array<string, mixed>
     */
    public function execute(
        string|int $serviceIdentifier,
        int $userId,
        int $stars,
        ?string $comment = null
    ): array {
        $service = is_numeric($serviceIdentifier)
            ? $this->towServiceRepository->findById((int)$serviceIdentifier)
            : $this->towServiceRepository->findByUuid(Uuid::fromString((string)$serviceIdentifier));

        if ($service === null) {
            throw new Exception('El servicio de auxilio solicitado no existe.', 404);
        }

        if ($service->getStatus() !== TowServiceStatus::Completed) {
            throw new DomainException('Solo es posible calificar servicios que hayan sido culminados exitosamente.', 400);
        }

        // Determinar a quién se está calificando
        if ($service->getClientId() === $userId) {
            $ratedUserId = $service->getDriverId();
            if ($ratedUserId === null) {
                throw new DomainException('El servicio no tiene un conductor asignado para calificar.', 400);
            }
        } elseif ($service->getDriverId() === $userId) {
            $ratedUserId = $service->getClientId();
        } else {
            throw new DomainException('No eres parte interesada (cliente ni chofer) en este servicio.', 403);
        }

        // Evitar doble calificación
        $existing = $this->ratingRepository->findByServiceAndUser((int)$service->getId(), $userId);
        if ($existing !== null) {
            throw new DomainException('Ya has emitido una calificación para este servicio.', 400);
        }

        try {
            $rating = Rating::create(
                towServiceId: (int)$service->getId(),
                userId: $userId,
                ratedUserId: (int)$ratedUserId,
                rating: $stars,
                comment: $comment !== null ? trim($comment) : null
            );
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage(), 422);
        }

        $this->ratingRepository->save($rating);

        // Invalidar caché de promedio en Redis si está disponible
        if ($this->redis !== null) {
            try {
                $this->redis->execute('DEL', "user:rating:avg:{$ratedUserId}");
            } catch (Throwable) {
                // Falla silenciosa si Redis no está activo
            }
        }

        return RatingResponseDto::fromEntity($rating, $service->getUuid()->getValue())->toArray();
    }
}
