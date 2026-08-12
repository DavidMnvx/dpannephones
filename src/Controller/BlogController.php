<?php

namespace App\Controller;

use App\Repository\BlogPostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Le Blog — rubrique publique « Conseils & Actualités ».
 */
class BlogController extends AbstractController
{
    #[Route('/blog', name: 'blog_index', methods: ['GET'])]
    public function index(Request $request, BlogPostRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort   = $request->query->get('tri'); // recents (défaut) | anciens | alpha

        $posts = $repo->findPublishedFiltered($search, $sort);

        return $this->render('blog/index.html.twig', [
            'posts'       => $posts,
            'searchQuery' => $search,
            'currentSort' => $sort,
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_show', methods: ['GET'])]
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

    /** Anciennes adresses /conseils : redirection permanente vers /blog. */
    #[Route('/conseils', name: 'blog_index_legacy', methods: ['GET'])]
    public function legacyIndex(): Response
    {
        return $this->redirectToRoute('blog_index', [], 301);
    }

    #[Route('/conseils/{slug}', name: 'blog_show_legacy', methods: ['GET'])]
    public function legacyShow(string $slug): Response
    {
        return $this->redirectToRoute('blog_show', ['slug' => $slug], 301);
    }
}
