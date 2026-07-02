# Sécurité et audit de la console d'administration

Cette fonctionnalité est extrêmement critique.

L'IA doit proposer des protections supplémentaires.

---

## Journalisation obligatoire

Chaque action effectuée doit être enregistrée :

- utilisateur ayant effectué l'action ;
- date et heure ;
- adresse IP ;
- base de données concernée ;
- type de moteur ;
- opération réalisée ;
- ancienne valeur ;
- nouvelle valeur ;
- succès ou échec de l'opération.

L'historique doit être consultable par le GLOBAL_ADMIN.

---

## Confirmation des opérations destructrices

Les actions suivantes nécessitent une confirmation renforcée :

- suppression d'une ligne ;
- suppression d'une table ;
- suppression d'une collection ;
- suppression d'une base ;
- modification massive.

L'IA doit proposer un mécanisme adapté :

- double confirmation ;
- saisie du nom de l'objet à supprimer ;
- éventuellement une authentification secondaire.

---

## Sécurisation des identifiants de connexion

Les mots de passe de bases de données ne doivent jamais être stockés en clair.

L'IA doit proposer :

- un mécanisme de chiffrement ;
- une stratégie de rotation des secrets ;
- une gestion sécurisée des clés de chiffrement.

---

## Mode maintenance

L'IA doit étudier la possibilité d'un mode "maintenance" permettant :

- de verrouiller temporairement certaines opérations applicatives ;
- d'effectuer des réparations en sécurité ;
- d'éviter des modifications concurrentes.

---

## Sauvegarde et restauration

L'IA doit proposer une stratégie permettant :

- l'export avant une opération critique ;
- la restauration en cas d'erreur ;
- la gestion des snapshots si le moteur le permet.

---

## Sécurité backend

Aucune action ne doit être exécutée uniquement parce qu'un bouton est affiché.

Chaque API doit vérifier :

- que l'utilisateur est authentifié ;
- qu'il possède le rôle GLOBAL_ADMIN ;
- que l'opération demandée est autorisée.

Les erreurs de sécurité doivent être enregistrées dans les journaux d'audit.
