# Plan de tests

Produis une stratégie de tests complète.

Inclure :

## Tests fonctionnels

Vérifier tous les cas d'usage des différents rôles.


## Tests de sécurité

Vérifier notamment :

- qu'un USER ne peut pas accéder à un environnement non attribué ;
- qu'un USER ne peut pas modifier l'application d'un autre utilisateur ;
- qu'un COMPANY_ADMIN ne peut pas modifier une autre entreprise ;
- que le compte admin possède toujours tous les accès ;
- qu'aucun utilisateur ne peut devenir administrateur sans autorisation.


## Tests de régression

Définir les scénarios permettant de garantir que les futures évolutions ne cassent pas le système de permissions.
