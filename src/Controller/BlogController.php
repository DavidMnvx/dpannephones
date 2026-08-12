<?php

namespace App\Controller;

use App\Repository\BlogPostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Rubrique publique « Conseils & Actualités ».
 */
class BlogController extends AbstractController
{
    #[Route('/conseils', name: 'blog_index', methods: ['GET'])]
    public function index(BlogPostRepository $repo): Response
    {
        return $this->render('blog/index.html.twig', [
            'posts' => $repo->findPublished(),
        ]);
    }

    #[Route('/conseils/{slug}', name: 'blog_show', methods: ['GET'])]
    public function show(string $slug, BlogPostRepository $repo): Response
    {
        $post = $repo->findOnePublishedBySlug($slug);

        if (!$post) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        return $this->render('blog/show.html.twig', [
            'post'        => $post,
            'otherPosts'  => array_values(array_filter(
                $repo->findPublished(4),
                fn ($p) => $p->getId() !== $post->getId()
            )),
        ]);
    }
}
