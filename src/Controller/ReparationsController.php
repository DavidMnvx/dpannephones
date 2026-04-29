<?php
namespace App\Controller;

use App\Entity\Marque;
use App\Entity\Model;
use App\Entity\Reparation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReparationsController extends AbstractController
{
    #[Route('/reparations', name: 'reparations_home')]
    #[Route('/reparations/type', name: 'choix_type')]
    public function choisirType(): Response
    {
        // Retourne la vue avec les choix (téléphone, tablette)
        return $this->render('reparations/choisir_type.html.twig');
    }

    #[Route('/marques/{type}', name: 'choix_marque')]
    public function choisirMarque(string $type, EntityManagerInterface $entityManager): Response
    {
        $marqueRepository = $entityManager->getRepository(Marque::class);

        // Vérifie la valeur de $type et effectue une requête en conséquence
        if ($type === 'phone') {
            $marques = $marqueRepository->findBy(['hasPhone' => true], ['name' => 'ASC']);
        } elseif ($type === 'tablet') {
            $marques = $marqueRepository->findBy(['hasTablet' => true], ['name' => 'ASC']);
        } else {
            $marques = $marqueRepository->findBy([], ['name' => 'ASC']);
        }

        return $this->render('reparations/choisir_marque.html.twig', [
            'marques' => $marques,
            'type' => $type,
        ]);
    }

    #[Route('/modeles/{marqueId}/{type}', name: 'choix_modele')]
    public function choisirModele(int $marqueId, string $type, EntityManagerInterface $entityManager): Response
    {
        $marque = $entityManager->getRepository(Marque::class)->find($marqueId);
        if (!$marque) {
            throw $this->createNotFoundException('Aucune marque trouvée pour l\'ID fourni : ' . $marqueId);
        }

        $modeleRepository = $entityManager->getRepository(Model::class);

        if ($type === 'phone') {
            $modeles = $modeleRepository->findBy(['marque' => $marque, 'hasPhone' => true], ['name' => 'ASC']);
        } elseif ($type === 'tablet') {
            $modeles = $modeleRepository->findBy(['marque' => $marque, 'hasTablet' => true], ['name' => 'ASC']);
        } else {
            $modeles = $modeleRepository->findBy(['marque' => $marque], ['name' => 'ASC']);
        }

        // ─── Regroupement par famille (sous-catégories) ───
        // Si au moins un modèle a une famille définie, on affiche en sous-onglets.
        // Sinon, affichage plat (comportement historique).
        $modelesByFamille = [];
        $hasFamilles = false;
        foreach ($modeles as $m) {
            $f = $m->getFamille();
            if ($f) {
                $hasFamilles = true;
            }
            $key = $f ?: 'Autres';
            $modelesByFamille[$key][] = $m;
        }
        // Tri alphabétique des familles, "Autres" en dernier
        if ($hasFamilles) {
            uksort($modelesByFamille, function ($a, $b) {
                if ($a === 'Autres') return 1;
                if ($b === 'Autres') return -1;
                return strcasecmp($a, $b);
            });
        }

        return $this->render('reparations/choisir_modele.html.twig', [
            'modeles'          => $modeles,
            'modelesByFamille' => $modelesByFamille,
            'hasFamilles'      => $hasFamilles,
            'marque'           => $marque,
            'type'             => $type,
        ]);
    }

    /**
     * API d'autocomplete pour la barre de recherche modèles (homepage).
     * Cherche dans le NOM du modèle ET le NOM de la marque (insensible à la casse).
     * Retourne max 8 résultats, classés par pertinence.
     *
     * Exemple : /api/models/search?q=s22 → [{"name":"Galaxy S22 Ultra", "marque":"Samsung", ...}]
     */
    #[Route('/api/models/search', name: 'api_models_search', methods: ['GET'])]
    public function apiModelsSearch(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        // Garde-fou : minimum 2 caractères pour éviter de scanner toute la base
        if (mb_strlen($q) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $type = (string) $request->query->get('type', 'phone'); // phone | tablet

        $qb = $em->getRepository(Model::class)->createQueryBuilder('m')
            ->leftJoin('m.marque', 'mq')
            ->addSelect('mq')
            ->where('LOWER(m.name) LIKE :q OR LOWER(mq.name) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->orderBy('m.name', 'ASC')
            ->setMaxResults(20);

        if ($type === 'phone') {
            $qb->andWhere('m.hasPhone = :t')->setParameter('t', true);
        } elseif ($type === 'tablet') {
            $qb->andWhere('m.hasTablet = :t')->setParameter('t', true);
        }

        $models = $qb->getQuery()->getResult();

        // Classement par pertinence : exact > débute par > contient
        $qLower = mb_strtolower($q);
        usort($models, function ($a, $b) use ($qLower) {
            $score = function (Model $m) use ($qLower) {
                $name = mb_strtolower($m->getName() ?? '');
                if ($name === $qLower) return 0;
                if (str_starts_with($name, $qLower)) return 1;
                return 2;
            };
            return $score($a) <=> $score($b);
        });

        $results = [];
        foreach (array_slice($models, 0, 8) as $m) {
            $results[] = [
                'id'      => $m->getId(),
                'name'    => $m->getName(),
                'marque'  => $m->getMarque() ? $m->getMarque()->getName() : null,
                'famille' => $m->getFamille(),
                'image'   => $m->getImage(),
                'url'     => $this->generateUrl('liste_reparations', [
                    'modeleName' => $m->getName(),
                    'type'       => $m->getHasPhone() ? 'phone' : 'tablet',
                ]),
            ];
        }

        return new JsonResponse(['query' => $q, 'results' => $results]);
    }

    #[Route('/reparations/{modeleName}/{type}', name: 'liste_reparations')]
    public function listeReparations(string $modeleName, string $type, EntityManagerInterface $entityManager): Response
    {
        $modeleRepository = $entityManager->getRepository(Model::class);
        $modele = $modeleRepository->findOneBy(['name' => $modeleName]);

        if (!$modele) {
            throw $this->createNotFoundException('Le modèle n\'a pas été trouvé.');
        }

        $reparationRepository = $entityManager->getRepository(Reparation::class);

        // Réparations spécifiques au modèle, en excluant celles marquées "universelles"
        // (les universelles n'ont normalement pas de modèle, mais on filtre pour être safe).
        $qb = $reparationRepository->createQueryBuilder('r')
            ->where('r.model = :model')
            ->andWhere('r.isUniversal = :false')
            ->setParameter('model', $modele)
            ->setParameter('false', false)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.name', 'ASC');

        if ($type === 'phone') {
            $qb->andWhere('r.hasPhone = :t')->setParameter('t', true);
        } elseif ($type === 'tablet') {
            $qb->andWhere('r.hasTablet = :t')->setParameter('t', true);
        }

        $reparations = $qb->getQuery()->getResult();

        // Réparations universelles / services communs (Tiroir SIM, Désoxydation…)
        $reparationsUniverselles = $reparationRepository->findUniversalReparations($type);

        return $this->render('reparations/liste_reparations.html.twig', [
            'reparations'             => $reparations,
            'reparationsUniverselles' => $reparationsUniverselles,
            'modele'                  => $modele,
            'type'                    => $type,
        ]);
    }
}
