<?php

namespace App\Twig;

use App\Entity\Horaire;
use App\Repository\HoraireRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HoraireExtension extends AbstractExtension
{
    public function __construct(private HoraireRepository $horaireRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('horaires_semaine', [$this, 'getHorairesSemaine']),
            new TwigFunction('horaires_resume', [$this, 'getHorairesResume']),
        ];
    }

    /**
     * @return Horaire[]
     */
    public function getHorairesSemaine(): array
    {
        return $this->horaireRepository->findAllOrderedByJour();
    }

    /**
     * Compact string like "du lundi au vendredi de 9h à 12h et 14h à 18h, samedi de 9h à 12h, dimanche fermé".
     */
    public function getHorairesResume(): string
    {
        $horaires = $this->getHorairesSemaine();
        if (!$horaires) {
            return '';
        }

        $groups = [];
        $current = null;

        foreach ($horaires as $h) {
            $signature = $this->signature($h);
            if ($current !== null && $current['signature'] === $signature) {
                $current['end'] = $h;
            } else {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = ['start' => $h, 'end' => $h, 'signature' => $signature];
            }
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        $parts = [];
        foreach ($groups as $group) {
            $parts[] = $this->formatGroup($group);
        }

        return implode(', ', $parts);
    }

    private function signature(Horaire $h): string
    {
        if ($h->isFerme()) {
            return 'ferme';
        }
        $fmt = fn(?\DateTimeInterface $t): string => $t?->format('H:i') ?? '-';

        return sprintf(
            '%s|%s|%s|%s',
            $fmt($h->getMatinOuverture()),
            $fmt($h->getMatinFermeture()),
            $fmt($h->getApresmidiOuverture()),
            $fmt($h->getApresmidiFermeture())
        );
    }

    private function formatGroup(array $group): string
    {
        $start = $group['start'];
        $end   = $group['end'];

        if ($start === $end) {
            $label = strtolower($start->getJourNom());
        } else {
            $label = 'du ' . strtolower($start->getJourNom()) . ' au ' . strtolower($end->getJourNom());
        }

        if ($start->isFerme()) {
            return $label . ' fermé';
        }

        $plages = [];
        if ($start->getMatinOuverture() && $start->getMatinFermeture()) {
            $plages[] = $this->formatHeure($start->getMatinOuverture()) . ' à ' . $this->formatHeure($start->getMatinFermeture());
        }
        if ($start->getApresmidiOuverture() && $start->getApresmidiFermeture()) {
            $plages[] = $this->formatHeure($start->getApresmidiOuverture()) . ' à ' . $this->formatHeure($start->getApresmidiFermeture());
        }
        if (!$plages) {
            return $label . ' fermé';
        }

        return $label . ' de ' . implode(' et ', $plages);
    }

    private function formatHeure(\DateTimeInterface $t): string
    {
        $minutes = (int) $t->format('i');
        return $minutes === 0
            ? $t->format('G') . 'h'
            : $t->format('G\hi');
    }
}
