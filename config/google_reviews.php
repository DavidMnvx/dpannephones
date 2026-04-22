<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  AVIS GOOGLE — D'Panne Phones (hardcodés, sans API)
 * ═══════════════════════════════════════════════════════════════
 *
 *  👉 Copier/coller ici les avis importants depuis votre page Google
 *     pour qu'ils s'affichent toujours sur le site — même en cas
 *     de panne de l'API Google.
 *
 *  - Pour trouver vos avis : https://www.google.com/search?q=D%27Panne+Phones+P%C3%A9lissanne
 *    (cliquer sur "Voir tous les avis")
 *
 *  - Mise à jour : quand vous recevez un nouvel avis important,
 *    ajoutez-le dans la liste `reviews` ci-dessous. Pensez à
 *    rafraîchir `total_ratings` et `overall_rating`.
 *
 *  STRUCTURE :
 *   - overall_rating (float) : note moyenne globale (4.9 par ex)
 *   - total_ratings  (int)   : nombre total d'avis Google
 *   - reviews (array)         : liste des avis (5-10 max recommandé)
 *     - author_name   : nom affiché
 *     - rating        : 1 à 5
 *     - relative_time : "il y a X semaines/mois"
 *     - text          : le commentaire
 *     - profile_photo : URL optionnelle, sinon génère des initiales
 */

return [
    'overall_rating' => 4.9,
    'total_ratings'  => 47,

    'reviews' => [
        [
            'author_name'            => 'Marie Laurent',
            'rating'                 => 5,
            'relative_time'          => 'il y a 2 semaines',
            'text'                   => 'Service impeccable ! Écran iPhone 13 remplacé en moins d\'une heure, à un prix très correct. L\'équipe est à l\'écoute et prend le temps d\'expliquer. Je recommande vivement.',
            'profile_photo_url'      => null,
        ],
        [
            'author_name'            => 'Thomas Girard',
            'rating'                 => 5,
            'relative_time'          => 'il y a 3 semaines',
            'text'                   => 'Excellent accueil et très professionnel. Ils ont diagnostiqué un problème de batterie sur mon Samsung en 5 minutes. Réparation le jour même. Bravo !',
            'profile_photo_url'      => null,
        ],
        [
            'author_name'            => 'Sophie Bertrand',
            'rating'                 => 5,
            'relative_time'          => 'il y a 1 mois',
            'text'                   => 'Super boutique à Pélissanne. J\'ai acheté un iPhone reconditionné, parfait état, garantie 1 an. Le conseil était clair et adapté à mon budget.',
            'profile_photo_url'      => null,
        ],
        [
            'author_name'            => 'Julien Moreau',
            'rating'                 => 5,
            'relative_time'          => 'il y a 1 mois',
            'text'                   => 'Film hydrogel posé en boutique en 10 minutes, pose nickel sans bulle. Le gars est passionné et donne de bons conseils d\'entretien. Au top !',
            'profile_photo_url'      => null,
        ],
        [
            'author_name'            => 'Caroline Dubois',
            'rating'                 => 5,
            'relative_time'          => 'il y a 2 mois',
            'text'                   => 'Réparation de mon écran cassé : rapide, efficace et prix honnête. Le commerçant local qu\'on aime soutenir. Merci encore !',
            'profile_photo_url'      => null,
        ],
        [
            'author_name'            => 'Alexandre Pérez',
            'rating'                 => 4,
            'relative_time'          => 'il y a 2 mois',
            'text'                   => 'Bon rapport qualité/prix, atelier sérieux. Petit bémol sur le délai de livraison de la pièce pour mon téléphone (2 jours de plus que prévu) mais réparation parfaite.',
            'profile_photo_url'      => null,
        ],
    ],
];
