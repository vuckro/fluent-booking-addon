# Changelog

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
