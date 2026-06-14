# Migrations de base de données

## État actuel des migrations appliquées

La base PostgreSQL connectée à cette application a déjà appliqué les migrations suivantes :

- `DoctrineMigrations\\Version20240201000002`
- `DoctrineMigrations\\Version20260525000001`
- `DoctrineMigrations\\Version20260525000003`

Ces migrations sont enregistrées dans la table `doctrine_migration_versions` du schéma `public`.

## Fichiers de migration présents

Les migrations sont définies dans le dossier `migrations/` avec le namespace `DoctrineMigrations` :

- `migrations/Version20240201000002.php`
- `migrations/Version20260525000001.php`
- `migrations/Version20260525000003.php`

## Configuration Doctrine Migrations

La configuration se trouve dans `config/packages/doctrine_migrations.yaml` :

```yaml
doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
    enable_profiler: false
```

La configuration Doctrine DBAL est dans `config/packages/doctrine.yaml` et utilise la variable d'environnement `DATABASE_URL`.

## Commandes utiles

- Vérifier le statut des migrations :

```bash
php bin/console doctrine:migrations:status --env=dev
```

- Lister toutes les migrations et leur statut :

```bash
php bin/console doctrine:migrations:list --env=dev
```

- Appliquer les migrations manquantes :

```bash
php bin/console doctrine:migrations:migrate --env=dev
```

- Générer une nouvelle migration vide :

```bash
php bin/console doctrine:migrations:generate
```

- Exécuter une migration spécifique :

```bash
php bin/console doctrine:migrations:execute DoctrineMigrations\\VersionYYYYMMDDHHMMSS --up --env=dev
```

- Annuler une migration spécifique :

```bash
php bin/console doctrine:migrations:execute DoctrineMigrations\\VersionYYYYMMDDHHMMSS --down --env=dev
```

## Production et rollback

1. Valider en staging / préproduction

- Exécuter `doctrine:migrations:status` et `doctrine:migrations:list` pour vérifier les versions disponibles et l'état appliqué.
- Tester l'exécution des mêmes migrations sur un clone de staging ou une base de données de préproduction avant tout déploiement en production.
- Confirmer que les sauvegardes de la base sont opérationnelles et que le plan de restauration est documenté.

2. Déployer en production

- Exécuter :

```bash
php bin/console doctrine:migrations:migrate --env=prod
```

- Si le déploiement inclut des changements de schéma sensibles, préférer une fenêtre de maintenance et surveiller les logs.
- En cas d'erreur, ne pas relancer immédiatement la migration en production sans diagnostic : identifier d'abord la cause, corriger la migration ou l'environnement, puis réessayer.

### Checklist pré-déploiement

| Vérification | Description |
|---|---|
| Migrations à jour | Vérifier que les migrations locales sont à jour et validées en staging. |
| Sauvegardes | Confirmer que les sauvegardes de la base de données sont récentes et testées. |
| Versions disponibles | Exécuter `doctrine:migrations:status` pour confirmer les versions disponibles. |
| Migrations non appliquées | Exécuter `doctrine:migrations:list` en staging ou préprod et noter les migrations non appliquées. |
| Compatibilité Doctrine | Vérifier la compatibilité des entités Doctrine avec la migration prévue. |
| Base `prod` | S'assurer que l'environnement `prod` pointe sur la bonne base `DATABASE_URL`. |
| Plan de rollback | Préparer un plan de rollback ou restauration avant de démarrer la migration. |
| Communication | Annoncer la fenêtre de maintenance si nécessaire et prévenir les équipes concernées. |

3. Rollback contrôlé

- Le rollback ne doit être utilisé qu'après analyse, car certaines migrations ne sont pas réversibles de manière sûre.
- Pour annuler une migration spécifique en production :

```bash
php bin/console doctrine:migrations:execute DoctrineMigrations\\VersionYYYYMMDDHHMMSS --down --env=prod
```

- Si la migration n'est pas réversible (`down()` absent ou destructif), restaurer la base depuis une sauvegarde cohérente.
- Documenter toujours les opérations de rollback : heure, version annulée, utilisateur, raison, et statut final.

## Remarques projet

- Le dossier `migrations/` contient uniquement les migrations actives et suivies par Doctrine.
- Le schéma de base de données est maintenu par ces migrations et par les entités Doctrine de l'application.
- Si le projet utilise un environnement de test, la configuration `when@test` dans `.env` ajoute un suffixe à la base de données.

## Vérifier directement en base

Pour confirmer l'état des migrations appliquées, on peut interroger la table `doctrine_migration_versions` :

```sql
SELECT version, executed_at
FROM doctrine_migration_versions
ORDER BY version;
```

Dans l'environnement actuel, la table contient bien les trois versions listées ci-dessus.
