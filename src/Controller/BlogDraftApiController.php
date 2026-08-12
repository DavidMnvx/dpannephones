<?php

namespace App\Controller;

use App\Entity\AppSetting;
use App\Entity\BlogPost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * API de dépôt de brouillons — le point de branchement des agents IA.
 *
 * Un agent (script, cron, assistant…) POSTe un article : il arrive TOUJOURS
 * en brouillon et n'est publié qu'après relecture humaine dans le back-office.
 *
 *   POST /api/blog/drafts
 *   Header : X-Blog-Token: <jeton depuis Admin > Conseils & Actualités>
 *   JSON   : { "title": "...", "content": "...",            // requis
 *              "excerpt": "...", "topic": "reparation",     // optionnels
 *              "seo_title": "...", "meta_description": "...",
 *              "slug": "...", "source": "nom-de-l-agent" }
 */
class BlogDraftApiController extends AbstractController
{
    #[Route('/api/blog/drafts', name: 'api_blog_draft_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $expected = $em->getRepository(AppSetting::class)->findOneBy(['key' => 'blog_api_token'])?->getRawValue();
        $provided = (string) $request->headers->get('X-Blog-Token', '');

        if (!$expected || !hash_equals($expected, $provided)) {
            return $this->json(['error' => 'Jeton invalide ou manquant (header X-Blog-Token).'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Corps JSON invalide.'], 400);
        }

        $title   = trim((string) ($data['title'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        if ($title === '' || $content === '') {
            return $this->json(['error' => 'Champs requis : title, content.'], 422);
        }

        $post = new BlogPost();
        $post->setTitle(mb_substr($title, 0, 255));
        $post->setContent($content);
        $post->setExcerpt(isset($data['excerpt']) ? trim((string) $data['excerpt']) : null);
        $post->setSeoTitle(isset($data['seo_title']) ? mb_substr(trim((string) $data['seo_title']), 0, 255) : null);
        $post->setMetaDescription(isset($data['meta_description']) ? mb_substr(trim((string) $data['meta_description']), 0, 300) : null);
        $post->setSource(mb_substr(trim((string) ($data['source'] ?? 'agent-ia')), 0, 100));

        $topic = $data['topic'] ?? null;
        if ($topic !== null && array_key_exists($topic, BlogPost::TOPICS)) {
            $post->setTopic($topic);
        }

        // Slug unique (fourni ou dérivé du titre) — jamais publié automatiquement
        $slug = strtolower((string) $slugger->slug(trim((string) ($data['slug'] ?? '')) ?: $title));
        $base = $slug;
        $i = 2;
        while ($em->getRepository(BlogPost::class)->findOneBy(['slug' => $slug])) {
            $slug = $base . '-' . $i++;
        }
        $post->setSlug($slug);
        $post->setIsPublished(false);

        $em->persist($post);
        $em->flush();

        return $this->json([
            'ok'      => true,
            'id'      => $post->getId(),
            'slug'    => $post->getSlug(),
            'status'  => 'draft',
            'message' => 'Brouillon déposé — à relire et publier depuis le back-office.',
        ], 201);
    }
}
