# Spécification fonctionnelle de my_app

## Administrateur global

Un utilisateur spécial nommé `admin` existe dès le déploiement de l'application.

Règles :

- Son identifiant est réservé.
- Il est unique.
- Il ne peut pas être supprimé.
- Il possède un accès complet au système.

Il peut :
- créer, modifier et supprimer toutes les entreprises ;
- accéder à tous les environnements ;
- gérer tous les utilisateurs ;
- intervenir pour la maintenance, le debug et la réparation de l'application.


## Entreprises

L'application est multi-entreprises.

Une entreprise possède :

- des environnements ;
- des utilisateurs ;
- un administrateur principal.


## Administrateur d'entreprise (COMPANY_ADMIN)

Lors de la création d'une entreprise, un administrateur d'entreprise est obligatoirement créé.

Dans sa propre entreprise, il peut :

- lire ;
- créer ;
- modifier ;
- supprimer ;
- administrer les utilisateurs ;
- administrer les environnements.

Concernant les autres entreprises :

- lecture uniquement ;
- aucune modification possible.


## Environnements

Une entreprise peut posséder plusieurs environnements.

Exemples :

- Production
- Préproduction
- Test
- Développement


L'administrateur d'entreprise peut :

- créer un environnement ;
- modifier un environnement ;
- supprimer un environnement ;
- attribuer des utilisateurs à un environnement.


## Utilisateurs standards (USER)

Les utilisateurs standards sont créés par un administrateur d'entreprise.

Un utilisateur peut être affecté :

- à aucun environnement ;
- à un environnement ;
- à plusieurs environnements.

Sans affectation, il ne peut accéder à aucune application supervisée.


## Applications supervisées

Dans les environnements autorisés :

Un utilisateur peut :

- voir toutes les applications supervisées ;
- créer une application supervisée ;
- modifier ses propres applications ;
- supprimer ses propres applications.

Pour une application créée par un autre utilisateur :

- lecture autorisée ;
- modification interdite ;
- suppression interdite.
