<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\RegistrationEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Mot de passe oublié — sans stockage de token en base :
 * on réutilise la mécanique de lien signé + expiration de VerifyEmailBundle,
 * déjà en place pour la confirmation d'email à l'inscription.
 */
class ResetPasswordController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        RegistrationEmailService $emailService,
        LoggerInterface $logger,
        #[Autowire(service: 'limiter.password_reset')]
        RateLimiterFactory $passwordResetLimiter
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('account_index');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'La session a expiré. Réessayez, ça ne prendra qu\'un instant.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $limiter = $passwordResetLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Trop de demandes d\'un coup. Patientez un moment avant de réessayer — ou appelez-nous au 07 83 74 83 11.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $email = trim((string) $request->request->get('email'));
            $user = $email !== '' ? $userRepository->findOneBy(['email' => $email]) : null;

            if ($user) {
                try {
                    $emailService->sendPasswordResetEmail($user);
                } catch (\Exception $e) {
                    $logger->error('[ResetPassword] Échec envoi email : ' . $e->getMessage(), ['email' => $email]);
                }
            }

            // Réponse identique que le compte existe ou non : on ne confirme
            // jamais à un inconnu qu'une adresse est cliente chez nous.
            $this->addFlash('success', 'Si un compte existe avec cette adresse, l\'email de réinitialisation vient de partir. Pensez à vérifier vos spams — le lien est valable 1 heure.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UserRepository $userRepository,
        VerifyEmailHelperInterface $verifyEmailHelper,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        $id = $request->query->getInt('id');
        $user = $id > 0 ? $userRepository->find($id) : null;

        if (!$user) {
            $this->addFlash('warning', 'Ce lien n\'est plus valide. Redemandez-en un ci-dessous, ça prend dix secondes.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // La signature (et son expiration d'une heure) est vérifiée à l'affichage
        // ET à la soumission : le formulaire poste sur la même URL signée.
        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('warning', 'Ce lien a expiré ou a déjà servi. Redemandez-en un ci-dessous, ça prend dix secondes.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $erreur = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', (string) $request->request->get('_csrf_token'))) {
                $erreur = 'La session a expiré. Réessayez, ça ne prendra qu\'un instant.';
            } else {
                $password = (string) $request->request->get('password');
                $confirm  = (string) $request->request->get('password_confirm');

                if (mb_strlen($password) < 8) {
                    $erreur = 'Le mot de passe doit contenir au moins 8 caractères.';
                } elseif ($password !== $confirm) {
                    $erreur = 'Les deux mots de passe ne correspondent pas.';
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                    $em->flush();

                    // Le client vient de prouver qu'il contrôle sa boîte mail :
                    // on le connecte directement, comme après la confirmation d'email.
                    $security->login($user, 'form_login', 'main');

                    $this->addFlash('success', 'Nouveau mot de passe enregistré — vous êtes connecté. Bon retour parmi nous !');
                    return $this->redirectToRoute('account_index');
                }
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'erreur' => $erreur,
        ]);
    }
}
