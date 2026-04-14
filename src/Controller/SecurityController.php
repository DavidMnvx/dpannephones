<?php
namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\RegistrationEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class SecurityController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        RegistrationEmailService $registrationEmailService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
            $user->setIsVerified(false);
            $user->setStatus('pending');

            $em->persist($user);
            $em->flush();

            try {
                $registrationEmailService->sendVerificationEmail($user, 'app_verify_email');
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas l'inscription
                $this->addFlash('warning', 'Compte créé mais l\'email de confirmation n\'a pas pu être envoyé. Contactez-nous.');
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        VerifyEmailHelperInterface $verifyEmailHelper,
        EntityManagerInterface $em
    ): Response {
        $id = $request->query->get('id');

        if (!$id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->redirectToRoute('app_register');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('verify_email_error', 'Le lien de vérification est invalide ou a expiré. ' . $e->getReason());
            return $this->redirectToRoute('app_register');
        }

        $user->setIsVerified(true);
        $user->setStatus('active');
        $em->flush();

        $this->addFlash('success', '🎉 Votre email a bien été confirmé ! Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/resend', name: 'app_resend_verify')]
    public function resendVerification(
        Request $request,
        EntityManagerInterface $em,
        RegistrationEmailService $registrationEmailService
    ): Response {
        $form = $this->createFormBuilder()
            ->add('email', \Symfony\Component\Form\Extension\Core\Type\EmailType::class, [
                'label' => 'Votre adresse email',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'votre@email.fr'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user  = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            // Toujours afficher le même message (sécurité anti-enumération)
            if ($user && !$user->isVerified()) {
                try {
                    $registrationEmailService->sendVerificationEmail($user, 'app_verify_email');
                } catch (\Exception $e) {
                    // silent
                }
            }

            $this->addFlash('success', 'Si cette adresse est enregistrée et non vérifiée, un email de confirmation a été renvoyé.');
            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('security/resend_verify.html.twig', [
            'resendForm' => $form->createView(),
        ]);
    }

    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('security/check_email.html.twig');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
