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
