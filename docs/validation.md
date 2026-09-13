# Tests — alpha.30

Exécuter depuis la racine du plugin avec PHP 8.1+ ; les intégrations refusent un site autre que localhost/127.0.0.1. Configurer le socket MySQL de PHP si Local le nécessite.

```sh
php tests/unit.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-options.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-pricing.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/native-tariffs.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/migration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/nonparticipating.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/public-booking.php
```

Les tests d’intégration utilisent l’événement local 2, des transactions annulées et vérifient la restauration des données. Les tests de réservation neutralisent les envois et HTTP externes. Utiliser une base locale jetable et InnoDB.

Tests de formulaire avec jsdom installé séparément (aucune dépendance JS embarquée dans le plugin) :

```sh
NODE_PATH=/chemin/jsdom/node_modules node tests/guest-admin-dom.cjs
NODE_PATH=/chemin/jsdom/node_modules node tests/guests-dom.cjs
NODE_PATH=/chemin/jsdom/node_modules node tests/guests-attached-dom.cjs
NODE_PATH=/chemin/jsdom/node_modules node tests/native-tariffs-dom.cjs
NODE_PATH=/chemin/jsdom/node_modules node tests/guest-information-dom.cjs
NODE_PATH=/chemin/jsdom/node_modules node tests/nonparticipating-dom.cjs
```

Le scénario `tests/native-page-dom.cjs` demande `WAASKIT_WP_PATH`, `FBA_PAGE_HTML` (fixture HTML de l’événement 2 avec personnalisation et tarifs 70/55) et `FBA_SLOTS_JSON` (réponse de disponibilités). Il charge le vrai bundle natif local, intercepte tous les appels réseau et ne soumet aucune réservation au site. Les fixtures ne sont pas distribuées.

```sh
python3 scripts/package.py
```

Voir le [bilan de recette et les contrôles encore manuels](recette-finale.md). Les tests DOM ne prouvent pas un rendu visuel navigateur ni un paiement Stripe complet.

Avec `FBA_NONPARTICIPATING=1`, le parcours natif vérifie aussi le contact non participant. Alpha.27 : 167 contrôles PHP et 8 scénarios DOM (dont les deux variantes du parcours natif). Voir [les limites de représentation native](reserver-pour-autrui.md).

## Régression AJAX — alpha.28

La recette précédente ne traversait pas le gestionnaire public PHP : les tests serveur appelaient BookingService directement et le parcours DOM interceptait la soumission. Ils ne reproduisaient donc pas les antislashs ajoutés par WordPress à `$_REQUEST`.

`tests/public-booking.php` reproduisait « Syntax error » avant le correctif. Il appelle maintenant les deux actions natives de réservation (connecté et anonyme), avec `wp_slash` comme WordPress, et intercepte uniquement la sortie JSON. Le gestionnaire réel vérifie un créneau disponible, crée la réservation, ses participants et sa confirmation. Le test contrôle ensuite les commandes hors ligne via OrderHelper. Il couvre également le contact non participant, les bornes d’âge, le nom obligatoire, les tarifs falsifiés, la capacité, le JSON invalide et le mode informations seules. Le Request natif déjà nettoyé est contrôlé séparément pour prévenir un double déséchappement.

Total actuel : **189 contrôles PHP et 8 scénarios DOM réussis**. Les 22 nouveaux contrôles s’exécutent en processus PHP local, pas via un navigateur ou une requête HTTP complète. Emails, synchronisations externes et actions de notification sont neutralisés ; toutes les écritures sont annulées. Cela ne constitue pas un paiement Stripe ni une vérification de réception des emails.

## Présentation des réservations — alpha.29

**200 contrôles PHP réussis.** Le test AJAX couvre maintenant les données transmises aux vues natives, les réponses de chaque invité, le lien enfant → réservant, la confirmation et son titre de facturation. Les contacts, tarifs et réponses historiques ne sont pas réécrits. Les réservations locales existantes 417, 418 et 419 ont aussi été vérifiées en lecture seule : Marie, 8 ans ; Jean, 10 ans ; Papa non participant.

La liste native conserve le nom du contact de la réservation principale (Papa). Déplier sa ligne affiche « Réservé par : Papa — ne participe pas » puis « Participant : Jean », son tarif et son âge. Cela préserve les coordonnées utilisées pour la facturation et les communications. Les intégrations externes continuent d’utiliser ce contact natif. Aucun nouveau test visuel navigateur ni test de réception des emails n’est revendiqué.

Alpha.30 : ajout de `php tests/calendar-presentation.php` (24 contrôles), et de 4 contrôles des hooks connectés dans le parcours AJAX. Voir [le périmètre des agendas connectés](agendas-connectes.md).

## Audit alpha.31 — 13 septembre 2026

266 contrôles PHP passent, dont 39 nouveaux contrôles `tests/calendar-contacts.php` (WordPress local requis). Les tests de notifications incluent les anciennes tâches indexées par ID. Exécuter ce test avec le même `WAASKIT_WP_PATH` et socket MySQL que les autres tests d’intégration. Les 8 scénarios DOM passent. Voir `recette-finale.md` pour les limites et conditions de production ; les tests ne certifient pas les services externes.

## Apparence des emails — alpha.32

`tests/email-appearance.php`, avec `WAASKIT_WP_PATH` et le socket local : 10 contrôles réussis sur le modèle email natif après Emogrifier. Le hook `pre_wp_mail` bloque le transport ; les réglages sont restaurés par rollback. Vérifie la couleur par défaut/personnalisée, le retour au bleu, les valeurs invalides et l’absence de modification des emails hors mailer FluentBooking. Aucun email réellement envoyé.

## Recette de déploiement — alpha.33

281 contrôles PHP réussis sur la suite complète, dont 5 contrôles de protection des opérations de groupe dans `nonparticipating.php`. Les 8 scénarios DOM ont également passé. Les 926 fichiers du cœur FluentBooking 2.4.0 ont été comparés aux SHA-256 de `https://downloads.wordpress.org/plugin-checksums/fluent-booking/2.4.0.json` : aucune différence, aucun PHP ajouté. Cette preuve concerne la partie gratuite, pas Pro. Voir `recette-finale.md` pour les validations externes encore nécessaires.

## Paiement Stripe — alpha.36

La préparation des commandes est vérifiée jusqu'au véritable adaptateur Stripe natif,
avec HTTP intercepté. `tests/stripe-native-tariffs.php` utilise une réservation de
source `web` : les hooks créent la commande puis appellent Stripe. Les cinq scénarios
70 / 140 / 125 / 195 / 55 EUR vérifient l'égalité des lignes de commande, du total,
de la requête `payment_intents` et de la réponse retournée au formulaire. Une quantité
forgée à 999 ne modifie pas le tarif. Les communications sortantes sont bloquées et
les données sont annulées par transaction. Aucune carte n'est débitée.

`tests/native-stripe-dom.cjs` charge les vrais scripts locaux `app.js` et
`stripe-checkout.js` dans jsdom ; seuls HTTP et Stripe.js sont simulés. Même environnement
que `native-page-dom.cjs`, avec une fixture HTML actuelle de l'événement 2 (Stripe
activé, tarifs Adulte 70 / Enfant 55, âge 8 accepté). Variantes :

- sans variable : contact adulte + enfant, 125 EUR ;
- `FBA_ALL_ADULT=1` : deux adultes, 140 EUR ;
- `FBA_NONPARTICIPATING=1` : contact absent + enfant, 55 EUR ;
- `FBA_MISMATCH=1` : réponse à 70 EUR pour une demande à 125 EUR, ouverture de Stripe bloquée.

Le test vérifie le verrouillage pendant la requête, avant la réponse, le maintien du
récapitulatif figé et l'affichage du montant serveur. `stripe-checkout-lock-dom.cjs`
vérifie également la restauration des contrôles si la soumission native échoue avant
le paiement. Le test AJAX `public-booking.php` et les tests unitaires de calcul,
champs obligatoires, capacité et contact non participant passent aussi.

Ces tests ne valident pas un débit bancaire, 3-D Secure, le webhook réel, le remboursement
ou la réception d'un email. Ces étapes restent à tester en mode test Stripe avant de
qualifier une installation de production. Le cœur Pro installé localement contient des
adaptations ; une autre version Pro doit repasser cette recette.
