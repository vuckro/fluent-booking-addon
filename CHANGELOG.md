# 4.0.0-alpha.17

- Limite transmise au composant public natif des invités, sans modifier les questions enregistrées.
- Contrôle de la demande publique avant filtrage/troncature des invités : dépassement, identité incomplète, doublon d’e-mail.
- Refus d’une demande tronquée au lieu de supprimer silencieusement des participants.
- Guide contextuel avec liens directs aux questions et paiements ; explication du comptage natif des événements de groupe.
- Tests ciblés sur l’événement 2 : places, maximum, quantité/prix natifs et restauration des données utilisateur.

# 4.0.0-alpha.16

- Une seule décision en langage courant remplace les deux menus d’héritage.
- Champ du maximum conditionnel, exemple concret et aperçu du réglage commun.
- Conservation des anciennes configurations si le formulaire est enregistré sans changement.

# 4.0.0-alpha.15

- Retrait des moteurs expérimentaux de participants, tarifs et capacités et de leurs formulaires/APIs.
- Réglages réduits à deux options explicites, résumé de l’héritage et accès aux calendriers natifs/publics.
- Séparation header, formulaire et adaptateur de limite ; retrait des assets inutilisés.
- Conservation des anciennes données et protection contre une reprise silencieuse des réservations expérimentales.
- Correction de la colonne utilisée pour la suppression volontaire des métadonnées historiques à la désinstallation.

# 4.0.0-alpha.14 — couleurs de repli WordPress

- Variables de couleur WordPress neutralisées uniquement sur les boutons du module, y compris les classes hover/focus.
- Version du CSS liée à sa date de modification pour renouveler le cache à chaque retouche.

# 4.0.0-alpha.13 — états et alignement des boutons

- Couleurs neutres au survol, au focus et au clic, prioritaires sur les styles WordPress.
- Repère clavier fin et neutre, couleurs clair/sombre conservées.
- Centrage du contenu des boutons et alignement du sélecteur de contexte avec son bouton.

# 4.0.0-alpha.12 — conteneur allégé

- Fond du conteneur principal retiré ; cartes conservées sur le fond de page.
- Padding supérieur retiré sur ordinateur et mobile pour rapprocher le contenu du header.

# 4.0.0-alpha.11 — administration plus lisible

- Regroupement en Participants, Catégories et tarifs, Places disponibles.
- Cases à cocher alignées sur le style FluentBooking, thèmes clair/sombre et navigation clavier.
- Réglages de groupe sur toute la largeur, options avancées repliées, prix masqués quand non utilisés.
- Sélection des accompagnateurs par catégorie, génération des identifiants des nouvelles catégories.
- Réglages hérités non modifiables tant que la personnalisation n’est pas sélectionnée.
- Aucun changement du moteur de réservation ou du stockage.

# 4.0.0-alpha.10 — modules en cours de développement

- Profil de participants typés, règles d’âge/accompagnement et champs configurables.
- Formulaire frontend et adaptateur au service natif, métadonnées de groupe.
- Capacités par événement et partagées, retenues persistantes et protection concurrente.
- Prix serveur en centimes, commandes natives, arrondis et arguments Stripe Checkout.
- Copie/import de profil, exports REST autorisés, confidentialité WordPress.
- Tests locaux de création, prix, annulation et concurrence.
- Périmètre encore incomplet et non validé en navigateur/Stripe : voir docs/implementation-modules.md.

# Changelog

## 4.0.0-alpha.9

- Texte, bordures, flèches et options des sélecteurs lisibles en mode sombre, y compris au survol et au focus.
- Lien WaasKit dans un nouvel onglet et diagnostics aérés.
- Synthèse du fonctionnement et du périmètre actuel.

## 4.0.0-alpha.8

- Titre Modules lisible en mode sombre.
- Thème chargé dans le head avant le contenu pour éviter le flash clair.
- Marges du logo alignées sur les 30 px natifs, retrait du fond au survol.
- Espace de défilement stable sans barre forcée.

## 4.0.0-alpha.7

- Header aligné sur les dimensions, icônes SVG et palette FluentBooking 2.4.0.
- Bouton clair/sombre fonctionnel et préférence partagée avec FluentBooking.
- Couleurs du formulaire adaptées au thème sombre.
- Suppression du double contour de focus ; une seule bordure neutre.

## 4.0.0-alpha.6

- Bandeau de navigation sur Modules avec logo FluentBooking et liens vers ses écrans.
- Lien Modules ajouté au header natif via le filtre admin_menu_items.
- Aucun montage de l’application FluentBooking sur la sous-page.

## 4.0.0-alpha.5

- Présentation légère inspirée de FluentBooking : fond clair, carte blanche, contrôles alignés et bouton sombre.
- Une seule feuille CSS isolée, responsive et sans framework ni JavaScript.
- Fonctionnement, stockage et protections inchangés.

## 4.0.0-alpha.4

- Retrait de l’intégration aux paramètres FluentBooking et du prototype contextuel non livré.
- Une seule page Modules : sélecteur, formulaire WordPress, diagnostics repliés.
- Suppression des styles et scripts de présentation propres à l’extension.
- Conservation des configurations, permissions, nonces et protections de concurrence.
- Compte rendu du périmètre et de ses limites.

## 4.0.0-alpha.3

- Réglages globaux intégrés à Paramètres → Modules via le formulaire générique natif FluentBooking.
- Champs, boutons, styles et sauvegarde natifs, sans JavaScript ou CSS supplémentaire sur cet écran.
- Même stockage et contrôle de révision que la page contextuelle.
- Raccourci Modules vers l'écran natif avec cœur/Pro 2.4.x ; repli conservé.
- Liens vers les surcharges par calendrier/événement et diagnostics.
- Tests de lecture, sauvegarde, héritage, permissions et erreurs via les hooks et endpoints natifs.

## 4.0.0-alpha.2

- Nom affiché : Fluent Booking Addon ; sous-menu « Modules ».
- Tableau de bord natif avec navigation globale, calendriers repliables et événements.
- Panneau Participants, provenance des valeurs et aide contextuelle.
- Diagnostics séparés des réglages courants.
- Mention discrète « Version alpha par WaasKit » liée au dépôt de l’extension.
- Styles responsives limités à cet écran ; champs hérités désactivés visuellement.
- Aucun changement des règles métier, du stockage ou des permissions.

## 4.0.0-alpha.1

- Nouveau socle PSR-4 avec configuration globale/calendrier/événement.
- Héritage, provenance, validation stricte, révisions et verrouillage des écritures.
- Administration contextuelle avec permissions FluentBooking et nonces WordPress.
- Registre extensible et exemple de limite de participants via le service natif.
- Diagnostics et simulation de lecture des réglages historiques.
- Retrait des interceptions XHR et des réécritures de prix dans le DOM.
- Aucune migration financière ni prise en charge des paiements avancés.
- Tests unitaires, intégration locale et workflow GitHub Actions.

Cette alpha constitue une fondation et ne revendique pas la parité fonctionnelle avec 3.3.6.
