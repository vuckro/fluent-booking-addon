## 4.0.0-alpha.29

- Les fiches natives affichent le réservant, sa participation, les participants, leurs tarifs et leurs réponses conservées.
- Les places invitées renvoient vers leur réservation principale ; les réservations existantes bénéficient du nouvel affichage sans migration.
- Confirmation : remplacement du bloc hors mise en page par des sections natives, rôles explicites et titre « Informations de facturation ».
- Le contact non participant n’est plus présenté comme participant dans la confirmation ; le bouton personnel d’ajout au calendrier y est retiré.
- Présentation isolée du calcul et du stockage dans BookingPresentation ; 200 contrôles PHP réussis.

## 4.0.0-alpha.28

- Correction de « Syntax error » à la réservation : déséchappement des données JSON uniquement à l’entrée AJAX publique WordPress, sans double traitement du contrôleur REST.
- Préservation des accents, apostrophes, guillemets et antislashs dans les identités des invités.
- Message compréhensible en cas de JSON invalide ; les données restent refusées.
- Régression reproduite avant correction dans le vrai gestionnaire public, puis 22 contrôles dédiés : confirmation, tarifs, places, champs et restauration des données.

## 4.0.0-alpha.27

- Option désactivée par défaut : réserver pour autrui sans participer.
- Contact et participants distingués dans le snapshot et le détail ; une place native par participant réel.
- En tarif par personne, seuls les participants paient ; les prix natifs par réservation restent inchangés.
- Garde-fous : participant obligatoire, capacité, autorisation native des invités, choix falsifiés et anciens moteurs incompatibles.
- Recette dédiée : serveur, champs, paiements, annulation et parcours natif simulé.

## 4.0.0-alpha.26

- Vérification avant publication sur main : suite serveur, formulaires et archive.
- Correction : le démarrage d’un paiement en mode informations seules ne réinjecte plus les tarifs du snapshot.
- Documentation de la version courante, du périmètre testé et du passage depuis la v3.

## 4.0.0-alpha.25

- Deux activations indépendantes : tarification par personne et informations des invités (nom, courriel, champs supplémentaires).
- Sans tarification personnalisée, le récapitulatif et les lignes de paiement natifs sont conservés.
- Champs numériques : minimum et maximum facultatifs, validés dans le navigateur et sur le serveur ; bornes inclusives.
- Exemples de choix génériques « Option 1 / Option 2 ». Les configurations précédentes conservent leur comportement.

## 4.0.0-alpha.24

- Bloc invités public aligné sur la palette native, labels lisibles en sombre, champs homogènes, ajout discret et suppression compacte.
- Un seul titre de récapitulatif ; champs personnalisés sortis du label de paiement natif.
- « Un tarif par personne » devient la case d’activation du mode et de ses options ; décochée, elle conserve le fonctionnement natif.

## 4.0.0-alpha.23

- Recette finale : prix, capacité, commandes, annulation, compatibilité et formulaires.
- Le moteur de tarifs refuse explicitement tout invité sans choix, même appelé directement.
- Formulaire : astérisque sur l’identité obligatoire ; résumé enregistré plus clair.
- Test reproductible du vrai bundle FluentBooking : navigation retour, conservation des invités, absence de doublon et charge utile transmise à un serveur simulé.

## 4.0.0-alpha.22

- Tarifs natifs exclusifs pour le réservant et chaque invité ; récapitulatif et commande par personne.
- Chargement des ressources corrigé sur la landing page FluentBooking : suppression du champ technique apparent.
- Nom invité configurable et courriel masqué conservés ; champs informatifs séparés des prix.
- Résumé : capacité effective, état vert/rouge ; lien Réglages enregistré dès le chargement du plugin.
- Compatibilité des anciens calculs conservée ; validation de choix périmés et tests de capacité/commande/DOM.

# 4.0.0-alpha.18

## 4.0.0-alpha.21

- Suppression complète du module de limite de participants, du registre de règles et de l’héritage site/calendrier/événement.
- Configuration uniquement par événement de groupe ; valeurs natives comme source unique des limites.
- API REST dédiée et ancienne option de décompte supprimées.
- Titre Modules, bloc de fonctionnalité indépendant et résumé des réglages enregistrés.
- Migration CLI avec sauvegarde, contrôle des anciennes limites et conservation des options invités.
- Documentation réécrite et tests actualisés.

## 4.0.0-alpha.20

- Un seul formulaire et parcours pour les invités personnalisés, indépendamment des identités demandées.
- Décompte automatique : une personne = une place ; suppression de la case ambiguë.
- Même plafond au formulaire et au serveur, incluant la capacité native.
- Suppression des manipulations des lignes Svelte et restauration de l’observation après retour navigateur.
- Effacement des noms dans les places natives rattachées, en plus des réponses enregistrées.
- Limites historiques conservées en attendant leur migration vers FluentBooking.

## 4.0.0-alpha.19

- Options invités affichées uniquement après activation.
- Identités obligatoires, facultatives ou masquées ; places rattachées au réservant sans faux contacts.
- Listes, radios et cases avec suppléments ou remplacement du tarif par invité.
- Calcul serveur en centimes, contrôle des remplacements concurrents, annulation et suppression des places rattachées.
- Tests de prix, identité, confidentialité et affichage conditionnel.

- Options propres à chaque événement de groupe : contrôle des places par personne et multiplication du prix indépendants.
- Questions supplémentaires par invité : liste de choix, texte et nombre, avec caractère obligatoire configurable.
- Récapitulatif public du nombre de personnes et du prix dès l’ajout d’un invité.
- Validation serveur, réponses figées en métadonnées, outils de confidentialité et prix conservés avec la réservation.
- Contrôle d’admission sérialisé par créneau sur les réservations natives ; aucune nouvelle table de stock.
- Prise en charge limitée à Stripe/hors ligne et tarif unique à deux décimales ; coupons et reports non pris en charge dans le mode personnalisé.

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
