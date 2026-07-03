# 🖼️ Aperçu Visuel - Interface de Suppression de Company

## 1. Page Principale - Liste des Companies

```
╔════════════════════════════════════════════════════════════════════════════╗
║ 🏢 Gestion des Companies                                                    ║
║ Gérez les entreprises de la plateforme                                      ║
╚════════════════════════════════════════════════════════════════════════════╝

┌────────────────────────────────────────────────────────────────────────────┐
│ Nom              │ Slug         │ Env. │ Utilis. │ Créée le  │ Statut       │
├────────────────────────────────────────────────────────────────────────────┤
│ Test Company     │ test-company │  2   │   5     │ 2026-07-03│ Actif        │
│ [Supprimer]                                                                 │
├────────────────────────────────────────────────────────────────────────────┤
│ Obstack Demo     │ obstack-demo │  3   │   8     │ 2026-06-15│ Actif        │
│ [Supprimer]                                                                 │
├────────────────────────────────────────────────────────────────────────────┤
│ Dev Environment  │ dev-env      │  1   │   3     │ 2026-05-20│ Inactif      │
│ [Supprimer]                                                                 │
└────────────────────────────────────────────────────────────────────────────┘
```

### État des Colonnes:
- **Nom:** Nom complet de la company (cliquable en bleu)
- **Slug:** Identifiant unique (format kebab-case)
- **Env.:** Nombre d'environnements
- **Utilis.:** Nombre d'utilisateurs
- **Créée le:** Date de création formatée
- **Statut:** Badge vert (Actif) ou rouge (Inactif)
- **Actions:** Bouton rouge "🗑️ Supprimer"

---

## 2. Modal de Confirmation - Étape 1

Quand l'utilisateur clique "Supprimer":

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ⚠️ Supprimer une Company                                            [×]   ║
╠════════════════════════════════════════════════════════════════════════════╣
║                                                                             ║
║ 🚨 ATTENTION - Cette action est IRRÉVERSIBLE                              ║
║ Vous êtes sur le point de supprimer :                                      ║
║                                                                             ║
║ ┌──────────────────────────────────────────────────────────────────────┐  ║
║ │ Company :      Test Company                                           │  ║
║ │ Slug :         test-company                                           │  ║
║ │ Environnements : 2                                                    │  ║
║ │ Utilisateurs :   5                                                    │  ║
║ └──────────────────────────────────────────────────────────────────────┘  ║
║                                                                             ║
║ Conséquences de la suppression :                                           ║
║ • ✗ Tous les environnements seront supprimés                              ║
║ • ✗ Toutes les applications seront supprimées                            ║
║ • ✗ Tous les utilisateurs de cette company seront supprimés              ║
║ • ✗ Tous les tokens d'agent seront supprimés                            ║
║ • ✗ Tous les logs et métriques seront supprimés                         ║
║                                                                             ║
║ ┌──────────────────────────────────────────────────────────────────────┐  ║
║ │ ☐ Je comprends que tous les données de cette company seront        │  ║
║ │   supprimées définitivement                                         │  ║
║ └──────────────────────────────────────────────────────────────────────┘  ║
║                                                                             ║
╠════════════════════════════════════════════════════════════════════════════╣
║  [Annuler]                   [🗑️ Supprimer Définitivement (DISABLED)]    ║
╚════════════════════════════════════════════════════════════════════════════╝
```

### États possibles:
- **Avant de cocher:** Bouton "Supprimer" grisé (disabled)
- **Après avoir coché:** Bouton "Supprimer" rouge et actif

---

## 3. Modal de Confirmation - Étape 2 (Après Cocher)

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ⚠️ Supprimer une Company                                            [×]   ║
╠════════════════════════════════════════════════════════════════════════════╣
║                                                                             ║
║ 🚨 ATTENTION - Cette action est IRRÉVERSIBLE                              ║
║ Vous êtes sur le point de supprimer :                                      ║
║                                                                             ║
║ ┌──────────────────────────────────────────────────────────────────────┐  ║
║ │ Company :      Test Company                                           │  ║
║ │ Slug :         test-company                                           │  ║
║ │ Environnements : 2                                                    │  ║
║ │ Utilisateurs :   5                                                    │  ║
║ └──────────────────────────────────────────────────────────────────────┘  ║
║                                                                             ║
║ Conséquences de la suppression :                                           ║
║ • ✗ Tous les environnements seront supprimés                              ║
║ • ✗ Toutes les applications seront supprimées                            ║
║ • ✗ Tous les utilisateurs de cette company seront supprimés              ║
║ • ✗ Tous les tokens d'agent seront supprimés                            ║
║ • ✗ Tous les logs et métriques seront supprimés                         ║
║                                                                             ║
║ ┌──────────────────────────────────────────────────────────────────────┐  ║
║ │ ☑ Je comprends que tous les données de cette company seront        │  ║
║ │   supprimées définitivement                                         │  ║
║ └──────────────────────────────────────────────────────────────────────┘  ║
║                                                                             ║
╠════════════════════════════════════════════════════════════════════════════╣
║  [Annuler]                        [🗑️ Supprimer Définitivement]          ║
╚════════════════════════════════════════════════════════════════════════════╝
```

### Changements:
- La checkbox est maintenant ☑️ cochée
- Le bouton "Supprimer" est actif (rouge, pas grisé)
- Hover: Le bouton change de couleur (plus foncé) avec une petite ombre

---

## 4. État de Suppression en Cours

Pendant que la suppression s'effectue:

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ⚠️ Supprimer une Company                                            [×]   ║
╠════════════════════════════════════════════════════════════════════════════╣
║                                                                             ║
║ [... modal content ...]                                                    ║
║                                                                             ║
╠════════════════════════════════════════════════════════════════════════════╣
║  [Annuler]                  [⏳ Suppression en cours...]                   ║
╚════════════════════════════════════════════════════════════════════════════╝
```

---

## 5. Page Après Suppression Réussie

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ✓ Company 'Test Company' (ID: 1) supprimée avec succès.                   ║  ← Toast
╚════════════════════════════════════════════════════════════════════════════╝

╔════════════════════════════════════════════════════════════════════════════╗
║ 🏢 Gestion des Companies                                                    ║
║ Gérez les entreprises de la plateforme                                      ║
╚════════════════════════════════════════════════════════════════════════════╝

┌────────────────────────────────────────────────────────────────────────────┐
│ Nom              │ Slug         │ Env. │ Utilis. │ Créée le  │ Statut       │
├────────────────────────────────────────────────────────────────────────────┤
│ Obstack Demo     │ obstack-demo │  3   │   8     │ 2026-06-15│ Actif        │
│ [Supprimer]                                                                 │
├────────────────────────────────────────────────────────────────────────────┤
│ Dev Environment  │ dev-env      │  1   │   3     │ 2026-05-20│ Inactif      │
│ [Supprimer]                                                                 │
└────────────────────────────────────────────────────────────────────────────┘
```

### Changements:
- ✅ Notification verte "✓ Company supprimée" en haut à droite
- ✅ "Test Company" n'est plus dans le tableau
- ✅ Modal fermée automatiquement
- ✅ Tableau mis à jour automatiquement

---

## 6. Erreur - Pas d'Authentification

Si l'utilisateur n'est pas connecté:

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ⚠️ Erreur d'authentification                                               ║
║ Vous devez être connecté pour accéder à cette page.                        ║
╚════════════════════════════════════════════════════════════════════════════╝
```

**Redirection:** Vers la page de login

---

## 7. Erreur - Rôle Insuffisant

Si l'utilisateur n'est pas SUPERADMIN:

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ⚠️ Accès refusé                                                            ║
║ Seuls les administrateurs peuvent accéder à cette page.                    ║
╚════════════════════════════════════════════════════════════════════════════╝
```

**Redirection:** Vers le dashboard

---

## 8. Erreur - Dernière Company

Si l'utilisateur essaie de supprimer la dernière company:

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ⚠️ Erreur lors de la suppression                                           ║
║ Impossible de supprimer la dernière company du système.                    ║
╚════════════════════════════════════════════════════════════════════════════╝
```

**Comportement:** Le modal reste ouvert

---

## 9. Erreur Réseau

Si la connexion échoue:

```
╔════════════════════════════════════════════════════════════════════════════╗
║ ⚠️ Erreur réseau                                                            ║
║ Impossible de se connecter au serveur. Veuillez réessayer.                ║
╚════════════════════════════════════════════════════════════════════════════╝
```

---

## 10. État de Chargement Initial

Quand la page se charge:

```
╔════════════════════════════════════════════════════════════════════════════╗
║ 🏢 Gestion des Companies                                                    ║
║ Gérez les entreprises de la plateforme                                      ║
╚════════════════════════════════════════════════════════════════════════════╝

┌────────────────────────────────────────────────────────────────────────────┐
│                                                                             │
│                     ⏳ Chargement des companies...                         │
│                          (spinning animation)                              │
│                                                                             │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## Couleurs Utilisées

| Élément | Couleur | Code |
|---------|---------|------|
| **Gradient Header** | Purple | #667eea → #764ba2 |
| **Bouton Supprimer** | Red | #f56565 |
| **Hover Supprimer** | Dark Red | #e53e3e |
| **Company Name** | Blue | #667eea 67eea |
| **Status Actif** | Green | #d4edda / #155724 |
| **Status Inactif** | Red | #f8d7da / #721c24 |
| **Modal Warning** | Light Red | #fff5f5 / #feb2b2 |
| **Toast Success** | Green | #d4edda / #155724 |
| **Toast Error** | Light Red | #fee / #742a2a |

---

## Animations

| Animation | Durée | Utilisation |
|-----------|-------|-------------|
| **slideIn** | 0.3s | Toast success |
| **spin** | 0.8s | Spinner loading |
| **scale** | 0.2s | Hover buttons |
| **translateY** | 0.2s | Hover actions |

---

## Responsive Behavior

### Desktop (> 768px)
- Table complète avec toutes les colonnes
- Modal centré
- Toast en haut à droite

### Tablet (768px)
- Même que desktop
- Légèrement réduit

### Mobile (< 768px)
- Table en scroll horizontal
- Modal 95% de la largeur
- Font sizes réduites
- Padding réduit

---

## Interactions Clés

### Hover sur un bouton Supprimer
```
Avant: Background rouge #f56565
Après: Background rouge plus foncé #e53e3e + scale(1.05) + shadow
```

### Clic sur Supprimer
```
Modal s'affiche avec animation fade
```

### Clic sur Annuler
```
Modal se ferme avec animation
Checkbox est réinitialisée
```

### Changement de la checkbox
```
Bouton Supprimer devient actif/inactif en temps réel
```

### Après suppression réussie
```
1. Modal se ferme
2. Toast apparaît (haut droit)
3. Tableau se met à jour
4. Company disparaît de la liste
```

---

## Accessibilité

- Tous les boutons ont des `title` (tooltips)
- Tous les inputs ont des labels
- Contraste suffisant (WCAG AA)
- Navigation au clavier supportée
- States visuels clairs

---

**Voilà à quoi ressemblera votre interface! 🎨**
