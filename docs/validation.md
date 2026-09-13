# Tests — alpha.28

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
