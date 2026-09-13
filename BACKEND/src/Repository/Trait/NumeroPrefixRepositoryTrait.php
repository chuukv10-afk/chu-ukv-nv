<?php

namespace App\Repository\Trait;

use App\Util\DocumentNumero;

trait NumeroPrefixRepositoryTrait
{
    public function nextNumeroForPrefix(string $prefix, int $pad = 4): string
    {
        $numeros = $this->createQueryBuilder('doc')
            ->select('doc.numero')
            ->andWhere('doc.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleColumnResult();

        return DocumentNumero::next($prefix, $numeros, $pad);
    }
}
