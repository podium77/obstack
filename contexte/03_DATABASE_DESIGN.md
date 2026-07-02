# Conception base de données

À partir de l'architecture validée :

Conçois le schéma relationnel complet.

Produis :

- la liste des tables ;
- les colonnes ;
- les types de données ;
- les clés primaires ;
- les clés étrangères ;
- les index ;
- les contraintes d'unicité ;
- les contraintes de suppression.

Les règles importantes :

- Le login `admin` doit être impossible à recréer.
- Une entreprise doit toujours avoir un COMPANY_ADMIN.
- Une application supervisée doit conserver son auteur.
- Les affectations utilisateur/environnement doivent être gérées via une relation plusieurs-à-plusieurs.

Ne génère pas encore les migrations.
