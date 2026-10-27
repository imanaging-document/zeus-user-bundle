# SSO v2 - bundle client et autorité

## Périmètre de cette étape

Les classes Sso/ ajoutent le protocole 2 sans remplacer les authentificateurs historiques.
Zeus expose ce protocole et journalise aussi ses retours historiques. Core et les satellites
ne sont pas migrés dans cette étape. Une mise à jour du bundle sur une autorité ne doit
jamais basculer ses propres connexions sortantes implicitement.

Lors de la migration d'un satellite, son contrôleur et son authentificateur locaux doivent
utiliser Client::begin()/complete() et refuser tous les callbacks historiques. Protocol::VERSION
est la version du nouveau client ; aucun paramètre de protocole à ajouter au .env.
Ne jamais choisir un authentificateur historique après un refus v2.

## Identité du satellite et configuration existante

Le client utilise OWN_URL et APP_SECRET. L'autorité retrouve une unique application active
par son URL enregistrée et utilise application.token comme credential. Les IDs Core et Zeus
ne sont pas supposés identiques. Vérifier hors logs que token correspond à APP_SECRET ;
un secret d'au moins 32 octets est exigé. Le secret n'est envoyé ni dans les URLs ni dans
le corps de l'échange ; il sert à une signature HMAC avec clé dérivée et contexte dédié.
La rotation d'APP_SECRET implique une synchronisation du registre des applications.

CORE_API_URL et ZEUS_API_URL existants désignent les autorités de confiance : retirer
uniquement le suffixe /api pour passer leur URL de base au client. Jamais de sélection
d'autorité, de credential ou de tenant depuis un paramètre de callback navigateur.

## Échanges

GET /sso-login/v2/check accepte application_url, callback, state, challenge PKCE S256,
correlation, issued_at, protocol=2 et signature. L'autorité vérifie l'application, le callback
exact, le HMAC et la date avant de produire une redirection. Les callbacks permis sont
OWN_URL/sso-login/return-validation/v2 ou OWN_URL/administration/sso-login/return-validation/v2.
OWN_URL doit correspondre à une URL enregistrée unique. HTTPS obligatoire hors loopback local.

L'absence de session produit error=no_session signé. Le refus de droits produit access_denied
signé. Le succès produit un code aléatoire de 256 bits, valable 60 secondes. Le code est
lié au satellite, à son callback et au challenge PKCE. Seule son empreinte est stockée.

POST /api/sso/v2/exchange reçoit application_url, callback, code, verifier, issued_at,
protocol=2 et signature (contexte différent). Consommation par UPDATE conditionnel : un seul
échange gagne, même en cas de requêtes concurrentes. Les droits du compte sont revérifiés.
La réponse serveur contient subject, login, source. Le client vérifie la source attendue.

Client::complete() consomme le state de session, ne suit aucune redirection HTTP et retourne
authenticated ou no_session. Une exception signifie un refus définitif de cette tentative.
Le demandeur doit conserver la cascade Zeus -> Core -> connexion Core. L'endpoint interactif
/sso-login/v2/authorize est une convention préparée par Client::begin(interactive: true) ;
son implémentation Core appartient à la prochaine étape et n'existe pas encore ici.

Le résultat ne constitue pas une session Symfony : l'authentificateur du satellite doit
résoudre l'identité locale, vérifier actif/provenance/droits puis laisser Symfony ouvrir
et renouveler la session. Aucune fusion automatique par e-mail. Le mapping durable
(autorité, subject) doit être traité lors de cette intégration ; login seul reste mutable.

## Historique et déploiement

Les deux tables sont définies par les entités Doctrine SsoAuthorizationCode et SsoConnectionEvent.
Sur Zeus, leur mapping est déclaré dans config/packages/doctrine.yaml. Utiliser le workflow
habituel doctrine:schema:update --dump-sql puis doctrine:schema:update --force avec un
environnement valide. Aucun fichier SQL ni migration versionnée n'est nécessaire.
Installer cette version du bundle dans Zeus avant d'activer les services ajoutés côté Zeus.
Le dépôt vendor installé ne se met pas à jour automatiquement avec les sources du bundle.
Déployer ensuite les autorités Core compatibles avant de migrer les satellites.

L'historique stocke des événements minimaux, sans codes, state, signatures ou vérificateurs.
exchange_accepted signifie que Zeus a validé le code, pas que le satellite a ouvert sa session.
Les anciens callbacks sont classés protocole 1, sans corrélation fiable inter-applications.
La requête SQL de recherche est paginée (100 événements), filtrée et réservée aux droits
du module historique des connexions. Pas de nouvelle permission globale ROLE_ADMIN sur Zeus.

Purge à programmer via sso:history:purge --days=90, avec un environnement Zeus valide.
Une panne d'écriture de l'historique émet un message technique sans preuve sensible et
ne coupe pas les anciens satellites ; sans table de codes, le protocole 2 échoue fermé.

## Vérification isolée

vendor/bin/phpunit --configuration phpunit.xml.dist

Les tests utilisent SQLite en mémoire, des doubles Doctrine/HTTP et aucune base applicative.
SSO_TEST_AUTOLOAD permet d'utiliser l'autoload d'une installation de dépendances existante.
Le bootstrap ne charge aucun .env. Tester également le parcours HTTP réel avant déploiement,
notamment les sessions, le MFA et la cascade une fois Core et un satellite migrés.

