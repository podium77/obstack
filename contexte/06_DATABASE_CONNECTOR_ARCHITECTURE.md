# Architecture des connecteurs de bases de données

Concevoir une architecture permettant à my_app de communiquer avec plusieurs moteurs de bases de données.

## Objectifs

Créer une couche d'abstraction permettant d'ajouter facilement de nouveaux moteurs.

Les moteurs initialement supportés sont :

- MySQL ;
- PostgreSQL ;
- Neo4j ;
- ArangoDB.

---

## Architecture attendue

Proposer une architecture de type "Driver / Adapter / Provider".

Exemple :

DatabaseConnector (interface)
|
|--- MysqlConnector
|
|--- PostgreSQLConnector
|
|--- Neo4jConnector
|
|--- ArangoDBConnector


Chaque connecteur doit fournir les méthodes suivantes :

- connect()
- disconnect()
- testConnection()
- listStructures()
- listData()
- insert()
- update()
- delete()
- executeQuery() (uniquement pour le GLOBAL_ADMIN)

---

## Extensibilité

L'ajout d'un nouveau moteur ne doit pas nécessiter de modifier le code existant.

Le système doit respecter le principe Open/Closed.
