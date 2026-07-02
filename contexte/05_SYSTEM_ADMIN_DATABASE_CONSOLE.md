# Console d'administration système des bases de données

## Objectif

my_app doit fournir une console d'administration avancée permettant au GLOBAL_ADMIN (`admin`) d'accéder directement aux bases de données déclarées dans l'application.

Cette fonctionnalité est destinée exclusivement aux opérations de :

- maintenance ;
- diagnostic ;
- correction de données ;
- récupération après incident ;
- support technique avancé.

Elle doit permettre d'effectuer des opérations directement sur les données stockées.

---

## Contrôle d'accès

Cette fonctionnalité est strictement réservée au rôle GLOBAL_ADMIN.

Règles :

- Aucun COMPANY_ADMIN ne peut voir ou accéder à cette fonctionnalité.
- Aucun USER standard ne peut y accéder.
- Les API associées doivent vérifier le rôle côté backend.
- Le masquage du menu côté frontend n'est qu'une mesure ergonomique.

---

## Bases de données supportées

La console doit pouvoir administrer plusieurs types de moteurs :

- MySQL ;
- PostgreSQL ;
- Neo4j ;
- ArangoDB.

L'architecture doit être extensible afin de permettre l'ajout futur d'autres moteurs de base de données.

---

## Gestion des connexions

Le GLOBAL_ADMIN doit pouvoir :

- enregistrer une connexion à une base de données ;
- modifier une connexion existante ;
- supprimer une connexion ;
- tester la connexion ;
- consulter l'état de disponibilité de la base.

Une connexion contient au minimum :

- nom de la connexion ;
- type de moteur ;
- hôte ;
- port ;
- nom de la base ;
- identifiant ;
- mot de passe (stocké de manière sécurisée) ;
- paramètres avancés éventuels.

---

## Exploration de la structure

Le GLOBAL_ADMIN doit pouvoir naviguer dans la structure de chaque base :

### Bases relationnelles

Pour MySQL et PostgreSQL :

- afficher les schémas ;
- afficher les tables ;
- afficher les colonnes ;
- afficher les index ;
- afficher les contraintes ;
- afficher les relations.

### Bases orientées graphes

Pour Neo4j :

- afficher les nœuds ;
- afficher les relations ;
- afficher les labels ;
- afficher les propriétés.

### Bases multi-modèles

Pour ArangoDB :

- afficher les collections ;
- afficher les documents ;
- afficher les graphes ;
- afficher les index.

---

## Manipulation des données

Le GLOBAL_ADMIN doit pouvoir :

### Lecture

- rechercher des données ;
- filtrer ;
- trier ;
- paginer les résultats ;
- visualiser une ligne ou un document complet.

### Création

- créer une ligne ;
- créer un document ;
- créer une relation ;
- créer une table ou collection si autorisé.

### Modification

- modifier une donnée directement ;
- mettre à jour plusieurs données.

### Suppression

- supprimer une ligne ;
- supprimer un document ;
- supprimer une table ;
- supprimer une collection ;
- supprimer une base si autorisé.

---

## Administration des données métier de my_app

Le GLOBAL_ADMIN peut également intervenir directement sur les données métier :

- entreprises ;
- environnements ;
- utilisateurs ;
- applications supervisées ;
- affectations utilisateur/environnement ;
- permissions.

Ces opérations doivent contourner les restrictions applicatives normales car elles sont destinées à la réparation du système.

---

## Interface utilisateur attendue

Le frontend doit fournir :

- une page "Administration système";
- une liste des connexions aux bases ;
- un explorateur de structure ;
- un explorateur de données ;
- des formulaires d'édition ;
- un historique des opérations.

L'interface doit rappeler visuellement qu'il s'agit d'une zone critique.
