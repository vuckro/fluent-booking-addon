# Changelog

## 4.0.0-alpha.2

- Nom affiché : Fluent Booking Addon ; sous-menu « Modules et réglages ».
- Tableau de bord natif avec navigation globale, agendas repliables et événements.
- Panneau Participants, provenance des valeurs et aide contextuelle.
- Diagnostics séparés des réglages courants.
- Mention discrète « Version alpha par WaasKit » liée au dépôt de l’extension.
- Styles responsives limités à cet écran ; champs hérités désactivés visuellement.
- Aucun changement des règles métier, du stockage ou des permissions.

## 4.0.0-alpha.1

- Nouveau socle PSR-4 avec configuration globale/agenda/événement.
- Héritage, provenance, validation stricte, révisions et verrouillage des écritures.
- Administration contextuelle avec permissions FluentBooking et nonces WordPress.
- Registre extensible et exemple de limite de participants via le service natif.
- Diagnostics et simulation de lecture des réglages historiques.
- Retrait des interceptions XHR et des réécritures de prix dans le DOM.
- Aucune migration financière ni prise en charge des paiements avancés.
- Tests unitaires, intégration locale et workflow GitHub Actions.

Cette alpha constitue une fondation et ne revendique pas la parité fonctionnelle avec 3.3.6.
