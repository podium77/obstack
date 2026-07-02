# 🚀 Quick Start - Obstack Admin Console Frontend

## Prerequisites

- Node.js 16+ ([Download](https://nodejs.org/))
- npm or yarn
- Backend API running on `http://localhost:8000`

## Setup (5 minutes)

### 1️⃣ Install Dependencies

```bash
cd frontend
npm install
```

### 2️⃣ Configure Environment

```bash
cp .env.example .env.local
```

Edit `.env.local` (optional - defaults to localhost:8000):
```
VITE_API_URL=http://localhost:8000/api
```

### 3️⃣ Start Development Server

```bash
npm run dev
```

**Open browser:** http://localhost:5173

## Login

```
Email:    admin@obstack.local
Password: TestPassword123
```

## 📚 Available Commands

```bash
npm run dev          # Start dev server
npm run build        # Production build
npm run preview      # Preview production build
npm run type-check   # Check TypeScript
npm run lint         # Lint code
```

## 🏗️ Project Structure

```
frontend/
├── src/
│   ├── components/     # Reusable components
│   ├── views/         # Page components
│   ├── stores/        # Pinia state management
│   ├── services/      # API services
│   ├── router/        # Vue Router
│   ├── types/         # TypeScript types
│   ├── utils/         # Helper functions
│   └── styles/        # Global styles
├── index.html         # HTML entry point
└── package.json       # Dependencies
```

## 📄 Available Pages

| Route | Name | Status |
|-------|------|--------|
| `/login` | 🔐 Login | ✅ Complete |
| `/dashboard` | 📊 Dashboard | ✅ Complete |
| `/connections` | 🔌 DB Connections | ✅ Complete |
| `/connections/:id` | ℹ️ Connection Detail | ⏳ In Progress |
| `/browser/:id` | 🔍 Data Browser | ⏳ Planned |
| `/query/:id` | 🔎 Query Executor | ⏳ Planned |
| `/audit` | 📋 Audit Logs | ✅ Complete |

## 🔗 API Integration

The frontend automatically connects to:
- Backend API: `http://localhost:8000/api`
- Uses JWT tokens for authentication
- Auto-refresh on 401 Unauthorized

## 🎨 Styling

- **Tailwind CSS** - Utility-first CSS
- **Responsive** - Mobile, tablet, desktop
- **Dark mode** - Ready (not implemented yet)

## 🐛 Troubleshooting

### Port 5173 already in use?
```bash
# Use a different port
npm run dev -- --port 5174
```

### CORS errors?
- Ensure backend is running with CORS enabled
- Check `VITE_API_URL` in `.env.local`
- Ensure backend `/api` routes accept requests from frontend origin

### Login fails?
- Backend API must be running
- Ensure database is setup and seeded with admin user
- Create admin user if missing:
  ```bash
  php bin/console app:user:create-admin
  ```

### Node version error?
```bash
node --version  # Should be 16.0.0 or higher
npm --version   # Should be 8.0.0 or higher
```

## 📦 Production Build

```bash
npm run build
```

Output: `frontend/dist/` → Ready to deploy

### Deploy to Nginx:
```bash
cp -r frontend/dist/* /var/www/html/
```

Configure Nginx to proxy `/api/` to backend.

## 🎓 Learn More

- [Vue 3 Guide](https://vuejs.org/guide/)
- [Vite Documentation](https://vitejs.dev/)
- [Pinia Store](https://pinia.vuejs.org/)
- [Tailwind CSS](https://tailwindcss.com/)

## ✅ Checklist

- [ ] Node.js 16+ installed
- [ ] `npm install` completed
- [ ] `.env.local` configured
- [ ] Backend API running on :8000
- [ ] Admin user created in database
- [ ] `npm run dev` started
- [ ] Browser open to :5173
- [ ] Can login with demo credentials

## 📞 Support

For issues, check:
1. [Backend is running](../README.md)
2. [Database is setup](../docs/GETTING_STARTED.md)
3. [Credentials are correct](../docs/GETTING_STARTED.md#demo-credentials)
4. Network tab in browser (check API calls)

---

**Ready to code!** 🚀

Start with these features:
- Explore Database Connections page
- Test database connection
- View Audit Logs
- Check Dashboard statistics
