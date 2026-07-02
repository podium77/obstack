#!/bin/bash

# Obstack Frontend Setup Script
# ==============================
# Ce script configure l'environnement frontend Vue 3

set -e

echo "🚀 Obstack Admin Console - Frontend Setup"
echo "=========================================="
echo ""

# Vérifier Node.js
if ! command -v node &> /dev/null; then
    echo "❌ Node.js n'est pas installé"
    echo "Télécharger depuis: https://nodejs.org/"
    exit 1
fi

echo "✅ Node.js $(node --version) détecté"
echo "✅ npm $(npm --version) détecté"
echo ""

# Naviguer vers le répertoire frontend
cd "$(dirname "$0")/frontend"

echo "📦 Installation des dépendances..."
npm install

echo ""
echo "🔧 Configuration d'environnement..."

# Créer .env.local si n'existe pas
if [ ! -f .env.local ]; then
    cp .env.example .env.local
    echo "✅ .env.local créé (à configurer)"
else
    echo "✅ .env.local existe déjà"
fi

echo ""
echo "✅ Configuration terminée!"
echo ""
echo "📝 Prochaines étapes:"
echo ""
echo "1. Éditer .env.local si nécessaire:"
echo "   VITE_API_URL=http://localhost:8000/api"
echo ""
echo "2. Démarrer le serveur de développement:"
echo "   npm run dev"
echo ""
echo "3. Ouvrir le navigateur:"
echo "   http://localhost:5173"
echo ""
echo "4. Utiliser les credentials de demo:"
echo "   Email: admin@obstack.local"
echo "   Password: TestPassword123"
echo ""
