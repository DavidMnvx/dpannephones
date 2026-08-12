<?php

namespace App\Controller;

use App\Entity\AppSetting;
use App\Entity\BlogPost;
use App\Form\BlogPostType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Back-office de la rubrique « Conseils & Actualités ».
 */
#[Route('/admin/conseils')]
class BlogAdminController extends AbstractController
{
    #[Route('/', name: 'admin_blog_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $posts = $em->getRepository(BlogPost::class)->findBy([], ['id' => 'DESC']);

        // Jeton de l'API de dépôt de brouillons (agents IA) — affiché dans l'encart d'aide.
        // Généré paresseusement à la première visite (le seed le crée vide).
        $tokenSetting = $em->getRepository(AppSetting::class)->findOneBy(['key' => 'blog_api_token']);
        if ($tokenSetting && !$tokenSetting->getRawValue()) {
            $tokenSetting->setRawValue(bin2hex(random_bytes(20)));
            $em->flush();
        }
        $apiToken = $tokenSetting?->getRawValue();

        // Prompt de l'agent IA — initialisé depuis docs/AGENT-BLOG.md (bloc central
        // entre les deux "---") à la première visite, puis éditable dans le BO
        $promptSetting = $em->getRepository(AppSetting::class)->findOneBy(['key' => 'blog_agent_prompt']);
        if ($promptSetting && !$promptSetting->getRawValue()) {
            $file = $this->getParameter('kernel.project_dir') . '/docs/AGENT-BLOG.md';
            if (is_file($file)) {
                $parts = explode("\n---\n", (string) file_get_contents($file));
                $promptSetting->setRawValue(trim($parts[1] ?? $parts[0]));
                $em->flush();
            }
        }

        return $this->render('admin/blog/index.html.twig', [
            'posts'       => $posts,
            'apiToken'    => $apiToken,
            'agentPrompt' => $promptSetting?->getRawValue() ?? '',
        ]);
    }

    #[Route('/prompt', name: 'admin_blog_prompt_save', methods: ['POST'])]
    public function savePrompt(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('blog-prompt', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $setting = $em->getRepository(AppSetting::class)->findOneBy(['key' => 'blog_agent_prompt']);
        if ($setting) {
            $setting->setRawValue(trim((string) $request->request->get('prompt')));
            $em->flush();
            $this->addFlash('success', "Prompt de l'agent IA enregistré.");
        }

        return $this->redirectToRoute('admin_blog_index');
    }

    #[Route('/nouveau', name: 'admin_blog_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $post = new BlogPost();
        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->finalize($post, $form, $em, $slugger);
            $em->persist($post);
            $em->flush();
            $this->addFlash('success', $post->isPublished()
                ? 'Article publié — il est en ligne sur /conseils.'
                : 'Brouillon enregistré (invisible sur le site).');

            return $this->redirectToRoute('admin_blog_index');
        }

        return $this->render('admin/blog/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'admin_blog_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(BlogPost $post, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->finalize($post, $form, $em, $slugger);
            $em->flush();
            $this->addFlash('success', 'Article mis à jour.');

            return $this->redirectToRoute('admin_blog_index');
        }

        return $this->render('admin/blog/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    /**
     * Aperçu d'un article (même en brouillon) : rendu exactement comme sur le
     * site public, avec un bandeau d'avertissement. Réservé aux admins.
     */
    #[Route('/{id}/apercu', name: 'admin_blog_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function preview(BlogPost $post): Response
    {
        return $this->render('blog/show.html.twig', [
            'post'       => $post,
            'otherPosts' => [],
            'preview'    => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_blog_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(BlogPost $post, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-blog-' . $post->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $em->remove($post);
        $em->flush();
        $this->addFlash('success', 'Article supprimé.');

        return $this->redirectToRoute('admin_blog_index');
    }

    // ─────────────────────────────────────────────────────────────────────

    /** Slug, unicité, image uploadée, horodatage. */
    private function finalize(BlogPost $post, $form, EntityManagerInterface $em, SluggerInterface $slugger): void
    {
        $slug = trim((string) $post->getSlug());
        if ($slug === '') {
            $slug = (string) $post->getTitle();
        }
        $slug = strtolower((string) $slugger->slug($slug));

        // Unicité : suffixe -2, -3… si le slug est déjà pris par un autre article
        $base = $slug;
        $i = 2;
        while (true) {
            $existing = $em->getRepository(BlogPost::class)->findOneBy(['slug' => $slug]);
            if (!$existing || $existing->getId() === $post->getId()) {
                break;
            }
            $slug = $base . '-' . $i++;
        }
        $post->setSlug($slug);

        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile instanceof UploadedFile) {
            $safe = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
            $name = $safe . '-' . uniqid() . '.' . $imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('images_directory'), $name);
                $post->setImage($name);
            } catch (FileException) {
                $this->addFlash('warning', "L'image n'a pas pu être enregistrée — article sauvegardé sans image.");
            }
        }

        $post->touch();
    }
}
