<?php

namespace App\Repository;

use App\Entity\PromoCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCode>
 */
class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    public function findByCode(string $code): ?PromoCode
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    /**
     * Retourne un tuple [PromoCode, errorMessage] :
     *  - Si OK : [PromoCode, null]
     *  - Si KO : [null, "message d'erreur affichable au client"]
     */
    public function findValidByCode(string $code, float $cartTotal): array
    {
        $promo = $this->findByCode($code);

        if (!$promo) {
            return [null, 'Ce code promo n\'existe pas.'];
        }

        if (!$promo->isActive()) {
            return [null, 'Ce code promo n\'est plus actif.'];
        }

        $now = new \DateTime();

        if ($promo->getValidFrom() && $now < $promo->getValidFrom()) {
            return [null, 'Ce code promo n\'est pas encore actif.'];
        }

        if ($promo->getValidUntil() && $now > $promo->getValidUntil()) {
            return [null, 'Ce code promo a expiré.'];
        }

        if ($promo->getUsageLimit() !== null && $promo->getUsageCount() >= $promo->getUsageLimit()) {
            return [null, 'Ce code promo a atteint sa limite d\'utilisation.'];
        }

        if ($promo->getMinCartAmount() !== null && $cartTotal < $promo->getMinCartAmount()) {
            return [null, sprintf(
                'Ce code promo nécessite un panier minimum de %s €.',
                number_format($promo->getMinCartAmount(), 2, ',', ' ')
            )];
        }

        return [$promo, null];
    }
}
