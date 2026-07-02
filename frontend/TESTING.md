# 🧪 Testing Guide - Phase 5 Frontend

## 🚀 Quick Start

### Démarrer le Frontend

```bash
cd /home/jaffar/projets/symfony/obstack/frontend
npm run dev
```

**Accès:** http://localhost:5173/

### Démarrer le Backend (optionnel)

```bash
cd /home/jaffar/projets/symfony/obstack
php -S localhost:8000 -t public
```

---

## 🔐 Login Test

### Credentials de Démo (Mock API)

```
Email: admin
   ou: admin@obstack.local
Password: TestPassword123
   ou: test
```

### Test Workflow

1. **Ouvrir le navigateur**
   - Go to: http://localhost:5173/

2. **Devrait voir la page de login**
   - Page titre: "Obstack Admin Console"
   - Form avec email et password
   - Info box avec credentials de démo

3. **Essayer login**
   - Email: `admin@obstack.local`
   - Password: `TestPassword123`
   - Click "Login"

4. **Résultat attendu**
   - Page devrait rediriger vers `/dashboard`
   - Dashboard affiche statistiques
   - User info dans le header: "Admin User"

5. **Contrôler le token**
   - DevTools → Application → LocalStorage
   - Voir clé: `auth-token`
   - Voir clé: `auth-user`

---

## 📊 Dashboard Test

### Features

- [ ] 4 stat cards affichent les numbers
- [ ] Recent connections section
- [ ] System info section
- [ ] Refresh button fonctionne
- [ ] Logout button visible dans le header

### Expected Stats (Mock Data)

```
Database Connections: 0 (mock)
Active Connections: 0 (mock)
Tested Connections: 0 (mock)
Current User: Admin User
```

---

## 🔌 Database Connections Test

### Route
`http://localhost:5173/connections`

### Features à Tester

- [ ] List est vide (pas de données en mock)
- [ ] "Add New Connection" button visible
- [ ] "No connections configured" message affiche
- [ ] Create button dans le message
- [ ] Modal s'ouvre au click

### Add Connection Modal

- [ ] Form affiche tous les champs:
  - Name (requis)
  - Database Type (dropdown)
  - Host
  - Port
  - Database
  - Username
  - Password

- [ ] Validation fonctionne:
  - Name requis
  - Type requis
  - Host requis

- [ ] Submit button disabled si form invalide
- [ ] Cancel button ferme le modal

---

## 📋 Audit Logs Test

### Route
`http://localhost:5173/audit`

### Features

- [ ] Page loads (mock API called)
- [ ] "No audit logs found" message (mock data vide)
- [ ] Filters visible:
  - Action dropdown
  - Status dropdown
  - Limit input
  - Apply button

- [ ] Table columns visible (quand data présente):
  - Timestamp
  - Action
  - User
  - Status
  - Resource
  - Details

---

## 🔄 Navigation Test

### Sidebar Links

- [ ] Dashboard link
- [ ] Connections link
- [ ] Audit link
- [ ] Links highlighted when active

### Header

- [ ] Logo visible
- [ ] Title "Obstack Admin"
- [ ] User name visible: "Admin User"
- [ ] Logout button visible
- [ ] Logout fonctionne (redirect à /login)

### Route Protection

- [ ] Trying `/connections` sans auth → redirect à `/login`
- [ ] After login → can access `/connections`
- [ ] Logout → redirect à `/login`
- [ ] Trying to go directly to `/dashboard` sans token → redirect à `/login`

---

## 🌐 Network Tab Test (DevTools)

### Check API Calls

1. Open DevTools → Network tab
2. Do login action
3. Look for requests:
   - Method: POST
   - URL: http://localhost:8000/api/login (si real API)
   - Headers: Content-Type: application/json
   - Response: JSON avec token/refreshToken

4. Look for other API calls:
   - GET /api/admin/database-connections
   - GET /api/admin/audit/logs

---

## 🎨 UI/UX Tests

### Responsive Design

- [ ] Desktop (1920px) - all features visible
- [ ] Tablet (768px) - sidebar collapses
- [ ] Mobile (375px) - hamburger menu (if implemented)

### Loading States

- [ ] Login button shows spinner while loading
- [ ] Pages show spinner while fetching data
- [ ] Buttons disabled during loading

### Error States

- [ ] Invalid credentials show error message
- [ ] API errors show error alert
- [ ] Network errors handled gracefully
- [ ] Can retry failed requests

---

## 📝 Console Checks (DevTools)

### No Errors

```bash
# Should see:
# - No 404 errors
# - No CORS errors
# - No TypeScript errors
# - No Console errors (red X)
```

### Vue Warnings

- [ ] Should show minimal Vue warnings
- [ ] No unknown components warnings
- [ ] No missing prop warnings

---

## 🔄 Mock API Behaviors

### Login Behavior

```
Credentials:
- admin / TestPassword123 → Success
- admin@obstack.local / test → Success
- other / password → Fail with message

Delay: ~500ms (simulates network)
Response: JWT token + refreshToken
```

### Database Connections

```
GET /api/admin/database-connections
Mock Response: { success: true, data: [] }
Delay: 300ms
```

### Audit Logs

```
GET /api/admin/audit/logs?limit=50
Mock Response: { success: true, data: [], total: 0 }
Delay: 300ms
```

---

## 🚨 Troubleshooting

### Login Fails

- [ ] Check credentials (case-sensitive)
- [ ] Check DevTools Console for errors
- [ ] Verify VITE_USE_MOCK_API=true in .env.local
- [ ] Clear localStorage and try again

### Dashboard Shows No Data

- [ ] Check if mock API is enabled
- [ ] Look at Network tab for failed requests
- [ ] Check browser console for errors
- [ ] Verify API URL correct

### Styles Not Loading

- [ ] Check if Tailwind CSS loaded
- [ ] Check for CSS errors in DevTools
- [ ] Run `npm run build` to regenerate CSS

### 404 on Route

- [ ] Check route spelling in URL
- [ ] Verify route exists in router/index.ts
- [ ] Check for typos in component name

---

## 📊 Current Status

| Feature | Status | Notes |
|---------|--------|-------|
| Login Page | ✅ Complete | Mock API ready |
| Dashboard | ✅ Complete | Shows mock stats |
| Connections List | ✅ Complete | Empty (mock) |
| Add Connection Modal | ✅ Complete | Form validation works |
| Audit Logs | ✅ Complete | Filters implemented |
| Layout/Sidebar | ✅ Complete | Responsive |
| Authentication Flow | ✅ Mock | Real API partially ready |
| Database Browser | ⏳ Placeholder | Needs implementation |
| Query Executor | ⏳ Placeholder | Needs implementation |
| Modals Component | ❌ Missing | Use inline modals for now |
| Notifications | ❌ Missing | No toast system yet |

---

## 🔄 Switching Between Mock and Real API

### Use Mock API (Development)
```
VITE_USE_MOCK_API=true
```

### Use Real API (Production/Testing)
```
VITE_USE_MOCK_API=false
```

**Note:** Backend JWT authentication needs to be completed for real API to work.

---

## 📋 Testing Checklist

- [ ] Frontend builds without errors
- [ ] Dev server starts on :5173
- [ ] Login page loads
- [ ] Can login with mock credentials
- [ ] Dashboard loads after login
- [ ] Navigation works between pages
- [ ] Logout redirects to login
- [ ] localStorage stores token
- [ ] DevTools shows API calls
- [ ] No console errors
- [ ] Responsive design works
- [ ] Styling looks correct

---

## 🎯 Next Steps

1. **Complete Real Authentication**
   - [ ] Implement user authentication in backend
   - [ ] Set VITE_USE_MOCK_API=false
   - [ ] Test full end-to-end flow

2. **Complete Remaining Views**
   - [ ] DatabaseBrowserView - full implementation
   - [ ] QueryExecutorView - full implementation
   - [ ] ConnectionDetailView - edit form

3. **Add Components**
   - [ ] Modal component
   - [ ] Toast notifications
   - [ ] Loading skeleton
   - [ ] Error boundary

4. **Enhancement**
   - [ ] Form validation UX
   - [ ] Loading states
   - [ ] Error messages
   - [ ] Dark mode
